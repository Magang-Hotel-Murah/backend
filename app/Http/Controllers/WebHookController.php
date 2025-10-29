<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebHookController extends Controller
{
    // Verifikasi dari Meta
    public function verify(Request $request)
    {
        $verifyToken = env('WHATSAPP_VERIFY_TOKEN'); // harus sama kayak yang kamu set di Meta Webhook config

        $mode = $request->get('hub_mode');
        $token = $request->get('hub_verify_token');
        $challenge = $request->get('hub_challenge');

        if ($mode && $token && $mode === 'subscribe' && $token === $verifyToken) {
            return response($challenge, 200);
        }

        return response('Forbidden', 403);
    }

    public function handle(Request $request)
    {
        $data = $request->all();

        // Log cuma body pesan biar gak kepotong
        $message = $data['entry'][0]['changes'][0]['value']['messages'][0] ?? null;
        if ($message) {
            $from = $message['from'];
            $text = $message['text']['body'] ?? '';
            Log::info("Incoming text message from $from: $text");

            $reply = $this->handleMessage($from, $text);
            $this->sendMessage($from, $reply);
        }

        return response()->json(['status' => 'ok']);
    }

    private function handleMessage($from, $text)
    {
        if ($text === 'halo') {
            return 'Halo juga';
        } elseif (strtolower($text) === 'help') {
            return 'Perintah yang tersedia: halo, help';
        } else {
            return 'Maaf, saya tidak mengerti pesan Anda. Ketik "help" untuk daftar perintah.';
        }
    }

    private function sendMessage($to, $text)
    {
        $url = "https://graph.facebook.com/v22.0/" . env('WA_PHONE_ID') . "/messages";
        Log::info("Sending message to $to: $text");

        $response = Http::withToken(env('WHATSAPP_TOKEN'))
            ->post($url, [
                "messaging_product" => "whatsapp",
                "to" => $to,
                "type" => "text",
                "text" => ["body" => $text],
            ]);

        Log::info('WhatsApp API response: ' . $response->body());

        return $response->json();
    }
}
