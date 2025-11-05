<?php

namespace App\Http\Services\Chatbot\Steps;

use App\Models\User;
use App\Http\Controllers\MeetingRoomController;
use Illuminate\Support\Facades\Log;
use App\Http\Services\Chatbot\ChatbotStateManager;

class ParticipantsCountStep extends StepHandler
{
    private MeetingRoomController $roomController;

    public function __construct(
        ChatbotStateManager $stateManager,
        MeetingRoomController $roomController
    ) {
        parent::__construct($stateManager);
        $this->roomController = $roomController;
    }

    public function handle(string $userId, string $text, User $user): string
    {
        $count = trim($text);

        // Validate numeric input
        if (!is_numeric($count) || intval($count) < 1) {
            return "❌ Jumlah peserta tidak valid.\n\n"
                . "Masukkan angka yang valid (minimal 1)";
        }

        $participantsCount = intval($count);

        // Store participants count
        $this->storeData($userId, 'participants_count', $participantsCount);
        $this->transitionTo($userId, 'show_rooms');

        // Fetch available rooms
        $date = $this->getData($userId, 'date');

        try {
            $request = new \Illuminate\Http\Request([
                'date' => $date,
                'participants_count' => $participantsCount
            ]);

            $response = $this->roomController->searchAvailableRooms($request);
            $data = $response->getData();

            $rooms = $data->data->rooms ?? [];

            // Filter rooms by user's company
            $companyRooms = collect($rooms)->filter(function ($room) use ($user) {
                return ($room->company_id ?? null) == $user->company_id;
            })->values()->all();

            // Store rooms for later reference
            $this->stateManager->setTempData($userId, 'available_rooms', $companyRooms);

            if (empty($companyRooms)) {
                $this->stateManager->resetUser($userId);
                return "❌ Tidak ada ruangan tersedia untuk tanggal {$date} dengan kapasitas {$participantsCount} orang.\n\n"
                    . "Silakan ketik '1' untuk mencoba tanggal atau kapasitas lain.";
            }

            // Format room list
            $message = "🏢 *Ruangan Tersedia*\n\n"
                . "📅 Tanggal: {$date}\n"
                . "👥 Peserta: {$participantsCount} orang\n\n";

            foreach ($companyRooms as $room) {
                $slots = collect($room->free_slots ?? [])->map(function ($s) {
                    return "{$s->start_time}-{$s->end_time}";
                })->implode(', ');

                $slotsText = $slots ?: 'Tidak ada slot';

                $message .= "🔹 ID: *{$room->id}* - {$room->name}\n"
                    . "   Kapasitas: {$room->capacity} orang\n"
                    . "   Jadwal: {$slotsText}\n\n";
            }

            $message .= "💡 Balas dengan ID ruangan (contoh: 3)";

            return $message;
        } catch (\Exception $e) {
            Log::error('Error fetching available rooms', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            $this->stateManager->resetUser($userId);
            return "❌ Terjadi kesalahan saat mengambil data ruangan.\n\n"
                . "Silakan coba lagi dengan mengetik '1'.";
        }
    }
}
