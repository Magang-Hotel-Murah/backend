<?php

namespace App\Http\Services\Chatbot\Steps;

use App\Models\User;
use App\Http\Services\Chatbot\Parsers\ParticipantParser;
use Illuminate\Support\Facades\Log;
use App\Http\Services\Chatbot\ChatbotStateManager;

class ParticipantsDetailStep extends StepHandler
{
    private ParticipantParser $participantParser;

    public function __construct(
        ChatbotStateManager $stateManager,
        ParticipantParser $participantParser
    ) {
        parent::__construct($stateManager);
        $this->participantParser = $participantParser;
    }

    public function handle(string $userId, string $text, User $user): string
    {
        $input = trim($text);

        if ($input === '-') {
            $this->storeData($userId, 'participants', []);
        } else {
            try {
                $participants = $this->participantParser->parse($input);
                $this->storeData($userId, 'participants', $participants);

                Log::info('Participants parsed successfully', [
                    'user_id' => $userId,
                    'count' => count($participants)
                ]);
            } catch (\Exception $e) {
                Log::warning('Failed to parse participants', [
                    'user_id' => $userId,
                    'error' => $e->getMessage()
                ]);

                return "❌ Format peserta tidak valid.\n\n"
                    . "Gunakan format:\n"
                    . "• ID User (contoh: 123)\n"
                    . "• Nama,Email,WhatsApp\n\n"
                    . "Pisahkan dengan enter untuk multiple peserta\n\n"
                    . "atau ketik '-' untuk skip";
            }
        }

        $this->transitionTo($userId, 'awaiting_request');

        return "🍽️ Masukkan request tambahan (opsional).\n\n"
            . "Format: dana,alasan;snack1,snack2;alat1,alat2\n\n"
            . "Contoh:\n"
            . "50000,Snack peserta;Kopi,Teh,Snack;Proyektor,Mic\n\n"
            . "atau ketik '-' untuk skip";
    }
}
