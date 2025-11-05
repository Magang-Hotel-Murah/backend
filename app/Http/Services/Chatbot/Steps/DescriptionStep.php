<?php

namespace App\Http\Services\Chatbot\Steps;

use App\Models\User;

class DescriptionStep extends StepHandler
{
    public function handle(string $userId, string $text, User $user): string
    {
        $description = trim($text);

        // Store description (null if user types '-')
        $this->storeData($userId, 'description', $description === '-' ? null : $description);
        $this->transitionTo($userId, 'awaiting_participants_detail');

        return "👥 Masukkan peserta tambahan (opsional).\n\n"
            . "Format per peserta:\n"
            . "• ID User, atau\n"
            . "• Nama,Email,WhatsApp\n\n"
            . "Pisahkan dengan enter untuk multiple peserta\n\n"
            . "Contoh:\n"
            . "123 (user ID)\n"
            . "John Doe,john@email.com,081234567890\n"
            . "atau: -  (skip)";
    }
}
