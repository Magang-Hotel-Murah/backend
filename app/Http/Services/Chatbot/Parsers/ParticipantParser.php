<?php

namespace App\Http\Services\Chatbot\Parsers;

use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * ParticipantParser - Parses participant input from user messages
 *
 * Supports two formats:
 * 1. User ID only (e.g., "123")
 * 2. Full details (e.g., "John Doe,john@email.com,081234567890")
 */
class ParticipantParser
{
    /**
     * Parse participant string into structured array
     *
     * @param string $input Raw input from user
     * @return array Array of participant data
     * @throws \InvalidArgumentException If input is invalid
     */
    public function parse(string $input): array
    {
        if (empty(trim($input))) {
            return [];
        }

        // Split by semicolon for multiple participants
        $participantStrings = array_map('trim', preg_split('/\R/', $input));
        $participants = [];

        foreach ($participantStrings as $participantString) {
            if (empty($participantString)) {
                continue;
            }

            $participant = $this->parseParticipant($participantString);
            if ($participant) {
                $participants[] = $participant;
            }
        }

        return $participants;
    }

    /**
     * Parse single participant string
     */
    private function parseParticipant(string $input): ?array
    {
        $input = trim($input);

        // Check if input is just a user ID (numeric)
        if (is_numeric($input)) {
            return $this->parseByUserId($input);
        }

        // Parse as comma-separated fields: name,email,phone
        return $this->parseByFields($input);
    }

    /**
     * Parse participant by user ID
     */
    private function parseByUserId(string $userId): ?array
    {
        $userExists = User::where('id', (int)$userId)->exists();

        if (!$userExists) {
            Log::warning('User not found for participant', ['user_id' => $userId]);
            return null;
        }

        return [
            'user_id' => (int)$userId,
            'name' => null,
            'email' => null,
            'whatsapp_number' => null,
        ];
    }

    /**
     * Parse participant by comma-separated fields
     */
    private function parseByFields(string $input): array
    {
        $fields = array_map('trim', explode(',', $input));

        $participant = [
            'user_id' => null,
            'name' => $fields[0] ?? null,
            'email' => null,
            'whatsapp_number' => null,
        ];

        // Parse second field (could be email or phone)
        if (isset($fields[1]) && !empty($fields[1])) {
            if (filter_var($fields[1], FILTER_VALIDATE_EMAIL)) {
                $participant['email'] = $fields[1];
            } else {
                $participant['whatsapp_number'] = $this->normalizePhone($fields[1]);
            }
        }

        // Parse third field (phone number)
        if (isset($fields[2]) && !empty($fields[2])) {
            $participant['whatsapp_number'] = $this->normalizePhone($fields[2]);
        }

        return $participant;
    }

    /**
     * Normalize phone number
     */
    private function normalizePhone(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Convert to Indonesian format (62xxx)
        if (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        } elseif (!str_starts_with($phone, '62')) {
            $phone = '62' . $phone;
        }

        return $phone;
    }
}
