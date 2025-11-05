<?php

namespace App\Http\Services\Chatbot\Steps;

use App\Models\User;

class TitleStep extends StepHandler
{
    public function handle(string $userId, string $text, User $user): string
    {
        $title = trim($text);

        if (empty($title)) {
            return "❌ Judul tidak boleh kosong.\n\n"
                . "Silakan masukkan judul meeting.";
        }

        if (strlen($title) > 200) {
            return "❌ Judul terlalu panjang (maksimal 200 karakter).\n\n"
                . "Silakan masukkan judul yang lebih singkat.";
        }

        $this->storeData($userId, 'title', $title);
        $this->transitionTo($userId, 'awaiting_description');

        return "📄 Masukkan deskripsi meeting (opsional).\n\n"
            . "Ketik '-' jika tidak perlu deskripsi.";
    }
}
