<?php

namespace App\Http\Services\Chatbot\Steps;

use App\Models\User;

class RoomSelectionStep extends StepHandler
{
    public function handle(string $userId, string $text, User $user): string
    {
        $roomId = trim($text);

        if (!is_numeric($roomId)) {
            return "❌ ID ruangan tidak valid.\n\n"
                . "Masukkan angka ID ruangan (contoh: 3)";
        }

        // Get available rooms from cache
        $rooms = $this->stateManager->getTempData($userId, 'available_rooms', []);

        // Find selected room
        $selectedRoom = collect($rooms)->firstWhere('id', intval($roomId));

        if (!$selectedRoom) {
            return "❌ ID ruangan tidak ditemukan.\n\n"
                . "Silakan pilih dari daftar yang tersedia.";
        }

        $freeSlots = $selectedRoom->free_slots ?? [];

        if (empty($freeSlots)) {
            return "❌ Ruangan '{$selectedRoom->name}' tidak memiliki slot tersedia.\n\n"
                . "Silakan pilih ruangan lain.";
        }

        // Store room selection
        $this->storeData($userId, 'meeting_room_id', $selectedRoom->id);
        $this->storeData($userId, 'room_name', $selectedRoom->name);
        $this->stateManager->setTempData($userId, 'available_slots', $freeSlots);
        $this->transitionTo($userId, 'awaiting_room_slot');

        // Format available time slots
        $slotsText = collect($freeSlots)->map(function ($s) {
            return "• {$s->start_time} - {$s->end_time}";
        })->implode("\n");

        return "🕐 *Slot Waktu Tersedia*\n\n"
            . "Ruangan: {$selectedRoom->name}\n\n"
            . "{$slotsText}\n\n"
            . "💡 Pilih slot dengan format HH:MM-HH:MM\n"
            . "Contoh: 09:00-11:00";
    }
}
