<?php

namespace App\Http\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappService
{
    protected string $phoneNumberId;
    protected string $accessToken;

    public function __construct()
    {
        $this->phoneNumberId = config('services.whatsapp.phone_number_id');
        $this->accessToken   = config('services.whatsapp.access_token');
    }

    /**
     * Kirim pesan WhatsApp menggunakan Meta Cloud API.
     */
    public function send(string $phone, string $message): bool
    {
        try {
            // Pastikan format nomor (hilangkan 0 depan, tambahkan 62)
            $phone = preg_replace('/[^0-9]/', '', $phone);
            if (str_starts_with($phone, '0')) {
                $phone = '62' . substr($phone, 1);
            }

            $response = Http::withToken($this->accessToken)
                ->post("https://graph.facebook.com/v19.0/{$this->phoneNumberId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to'                => $phone,
                    'type'              => 'text',
                    'text'              => [
                        'preview_url' => false,
                        'body'        => $message,
                    ],
                ]);

            if ($response->failed()) {
                Log::error('WhatsApp send failed', [
                    'phone'    => $phone,
                    'message'  => $message,
                    'response' => $response->body(),
                ]);
                return false;
            }

            Log::info('WhatsApp message sent', [
                'phone'   => $phone,
                'message' => $message,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('WhatsApp send exception', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
