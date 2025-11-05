<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Services\WhatsappService;
use App\Http\Services\Chatbot\ChatbotStateManager;
use App\Http\Services\Chatbot\ChatbotFlowHandler;

/**
 * WebHookController - Handles WhatsApp webhook verification and incoming messages
 *
 * This controller delegates conversation flow to ChatbotFlowHandler and
 * state management to ChatbotStateManager for better separation of concerns.
 */
class WebHookController extends Controller
{
    protected WhatsappService $whatsappService;
    protected ChatbotStateManager $stateManager;
    protected ChatbotFlowHandler $flowHandler;

    public function __construct(
        WhatsappService $whatsappService,
        ChatbotStateManager $stateManager,
        ChatbotFlowHandler $flowHandler
    ) {
        $this->whatsappService = $whatsappService;
        $this->stateManager = $stateManager;
        $this->flowHandler = $flowHandler;
    }

    /**
     * Verify webhook subscription from WhatsApp
     */
    public function verify(Request $request)
    {
        $verifyToken = config('services.whatsapp.verify_token');

        $mode = $request->get('hub_mode');
        $token = $request->get('hub_verify_token');
        $challenge = $request->get('hub_challenge');

        if ($mode && $token && $mode === 'subscribe' && $token === $verifyToken) {
            Log::info('WhatsApp webhook verified successfully');
            return response($challenge, 200);
        }

        Log::warning('WhatsApp webhook verification failed', [
            'mode' => $mode,
            'token_match' => $token === $verifyToken
        ]);

        return response('Forbidden', 403);
    }

    /**
     * Handle incoming WhatsApp messages
     */
    public function handle(Request $request)
    {
        try {
            $data = $request->all();

            // Extract message from webhook payload
            $message = $data['entry'][0]['changes'][0]['value']['messages'][0] ?? null;

            if (!$message) {
                Log::info('Webhook received without message');
                return response()->json(['status' => 'ok']);
            }

            // Normalize phone number and extract message text
            $from = $this->normalizePhoneNumber($message['from']);
            $text = $message['text']['body'] ?? '';

            Log::info('Incoming WhatsApp message', [
                'from' => $from,
                'text' => substr($text, 0, 100) // Log first 100 chars only
            ]);

            // Process message and get reply
            $reply = $this->flowHandler->processMessage($from, $text);

            // Send reply
            $this->sendMessage($from, $reply);

            return response()->json(['status' => 'ok']);
        } catch (\Exception $e) {
            Log::error('Error handling WhatsApp webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Still return 200 to acknowledge receipt
            return response()->json(['status' => 'error'], 200);
        }
    }

    /**
     * Send message via WhatsApp service
     */
    private function sendMessage(string $to, string $text): void
    {
        try {
            $response = $this->whatsappService->send($to, $text);

            if (!$response) {
                Log::error('Failed to send WhatsApp message', [
                    'to' => $to,
                    'message_preview' => substr($text, 0, 50)
                ]);
            } else {
                Log::info('WhatsApp message sent successfully', ['to' => $to]);
            }
        } catch (\Exception $e) {
            Log::error('Exception sending WhatsApp message', [
                'to' => $to,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Normalize phone number to Indonesian format (62xxx)
     */
    private function normalizePhoneNumber(string $number): string
    {
        // Remove @c.us suffix if present
        $number = str_replace('@c.us', '', $number);

        // Remove all non-numeric characters
        $number = preg_replace('/[^0-9]/', '', $number);

        // Convert to Indonesian format (62xxx)
        if (str_starts_with($number, '0')) {
            $number = '62' . substr($number, 1);
        } elseif (!str_starts_with($number, '62')) {
            $number = '62' . $number;
        }

        return $number;
    }
}
