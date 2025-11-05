<?php

namespace App\Http\Services\Chatbot\Steps;

use App\Models\User;
use Carbon\Carbon;

class DateStep extends StepHandler
{
    public function handle(string $userId, string $text, User $user): string
    {
        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($text))) {
            return "❌ Format tanggal salah.\n\n"
                . "Gunakan format: YYYY-MM-DD\n"
                . "Contoh: 2025-11-10";
        }

        try {
            $inputDate = Carbon::parse(trim($text))->startOfDay();
            $today = Carbon::now()->startOfDay();

            // Validate date is not in the past
            if ($inputDate->lt($today)) {
                return "❌ Tanggal tidak valid.\n\n"
                    . "Silakan masukkan tanggal hari ini atau setelahnya.";
            }

            // Store date and move to next step
            $this->storeData($userId, 'date', $inputDate->format('Y-m-d'));
            $this->transitionTo($userId, 'awaiting_participants');

            return "👥 Berapa jumlah peserta meeting?\n\n"
                . "Masukkan angka (contoh: 5)";
        } catch (\Exception $e) {
            return "❌ Tanggal tidak valid.\n\n"
                . "Pastikan tanggal sesuai format dan valid.";
        }
    }
}
