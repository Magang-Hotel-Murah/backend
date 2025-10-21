<?php

namespace App\Http\Services;

use Illuminate\Support\Facades\Log;

class WhatsappService
{
    /**
     * Kirim pesan WhatsApp menggunakan Fonnte API.
     */
    public function send($phone, $message)
    {
        try {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => "https://api.fonnte.com/send",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => [
                    'target' => $phone,
                    'message' => $message,
                ],
                CURLOPT_HTTPHEADER => [
                    "Authorization: " . env('FONNTE_TOKEN'),
                ],
            ]);

            $response = curl_exec($curl);

            if (curl_errno($curl)) {
                Log::error('Fonnte error: ' . curl_error($curl));
            }

            curl_close($curl);
            return $response;
        } catch (\Throwable $th) {
            Log::error('sendWhatsapp() gagal: ' . $th->getMessage());
            return false;
        }
    }
}
