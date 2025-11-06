<?php

namespace App\Http\Services\Chatbot\Steps;

use App\Models\User;
use App\Http\Services\Chatbot\Parsers\RequestParser;
use App\Http\Services\MeetingRoomReservationService;
use Illuminate\Support\Facades\Log;
use App\Http\Services\Chatbot\ChatbotStateManager;

class RequestStep extends StepHandler
{
    private RequestParser $requestParser;
    private MeetingRoomReservationService $reservationService;

    public function __construct(
        ChatbotStateManager $stateManager,
        RequestParser $requestParser,
        MeetingRoomReservationService $reservationService
    ) {
        parent::__construct($stateManager);
        $this->requestParser = $requestParser;
        $this->reservationService = $reservationService;
    }

    public function handle(string $userId, string $text, User $user): string
    {
        $input = trim($text);

        // Parse request data
        if ($input === '-') {
            $request = [];
        } else {
            try {
                $request = $this->requestParser->parse($input);
            } catch (\Exception $e) {
                Log::warning('Failed to parse request', [
                    'user_id' => $userId,
                    'error' => $e->getMessage()
                ]);

                return "❌ Format request tidak valid.\n\n"
                    . "Gunakan format:\n"
                    . "dana,alasan;snack1,snack2;alat1,alat2\n\n"
                    . "atau ketik '-' untuk skip";
            }
        }

        // Get all collected data
        $data = $this->getData($userId);

        // Build reservation request
        $reservationData = [
            'meeting_room_id' => $data['meeting_room_id'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'start_time' => $data['date'] . ' ' . $data['start_time'],
            'end_time' => $data['date'] . ' ' . $data['end_time'],
            'participants' => $data['participants'] ?? [],
            'request' => $request,
        ];

        Log::info('Creating reservation from chatbot', [
            'user_id' => $userId,
            'room_id' => $data['meeting_room_id']
        ]);

        try {
            // Create reservation
            $reservation = $this->reservationService->createReservation(
                $reservationData,
                $userId
            );

            if (!$reservation) {
                return "❌ Gagal membuat reservasi.\n\n"
                    . "Silakan coba lagi atau hubungi administrator.";
            }

            // Clear user state after successful reservation
            $this->stateManager->resetUser($userId);

            // Return success message with reservation details
            return "✅ *Reservasi Berhasil Dibuat!*\n\n"
                . "📋 ID Reservasi: {$reservation->id}\n"
                . "🏢 Ruangan: {$data['room_name']}\n"
                . "📅 Tanggal: {$data['date']}\n"
                . "🕐 Waktu: {$data['start_time']} - {$data['end_time']}\n"
                . "📝 Judul: {$data['title']}\n\n"
                . "Terima kasih! Ketik 'menu' untuk kembali ke menu utama.";
        } catch (\Exception $e) {
            Log::error('Failed to create reservation', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return "❌ Gagal membuat reservasi.\n\n"
                . "Error: " . $e->getMessage() . "\n\n"
                . "Silakan coba lagi atau ketik 'reset' untuk memulai ulang.";
        }
    }
}
