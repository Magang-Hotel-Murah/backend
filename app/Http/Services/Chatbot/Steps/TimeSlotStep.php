<?php

namespace App\Http\Services\Chatbot\Steps;

use App\Models\User;

class TimeSlotStep extends StepHandler
{
    public function handle(string $userId, string $text, User $user): string
    {
        // Validate time format
        if (!preg_match('/^(\d{2}:\d{2})-(\d{2}:\d{2})$/', trim($text), $matches)) {
            return "❌ Format waktu salah.\n\n"
                . "Gunakan format: HH:MM-HH:MM\n"
                . "Contoh: 09:00-11:00";
        }

        $startTime = $matches[1];
        $endTime = $matches[2];

        // Validate time slot is within available slots
        $slots = $this->stateManager->getTempData($userId, 'available_slots', []);
        $validSlot = false;

        foreach ($slots as $slot) {
            if ($startTime >= $slot->start_time && $endTime <= $slot->end_time) {
                $validSlot = true;
                break;
            }
        }

        if (!$validSlot) {
            $slotsText = collect($slots)->map(
                fn($s) =>
                "{$s->start_time}-{$s->end_time}"
            )->implode(', ');

            return "❌ Waktu tidak sesuai dengan slot yang tersedia.\n\n"
                . "Slot tersedia: {$slotsText}";
        }

        // Store time selection
        $this->storeData($userId, 'start_time', $startTime);
        $this->storeData($userId, 'end_time', $endTime);
        $this->transitionTo($userId, 'awaiting_title');

        return "📝 Masukkan judul meeting.\n\n"
            . "Contoh: Rapat Evaluasi Q4";
    }
}
