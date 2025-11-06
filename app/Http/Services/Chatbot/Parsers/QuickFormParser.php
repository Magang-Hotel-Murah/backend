<?php

namespace App\Http\Services\Chatbot\Parsers;

use App\Models\User;
use App\Http\Services\MeetingRoomReservationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * QuickFormParser - Parses user-friendly quick form submission format
 *
 * User-friendly format:
 * Tanggal: 2025-11-10
 * Peserta: 3
 * Ruangan: 2
 * Waktu: 09:00-11:00
 * Judul: Meeting Tim
 * Deskripsi: Diskusi Q4
 * Daftar Peserta:
 * - John Doe (john@example.com)
 * - Jane Smith (jane@example.com)
 * Request: Proyektor, Snack
 */
class QuickFormParser
{
    private ParticipantParser $participantParser;
    private RequestParser $requestParser;
    private MeetingRoomReservationService $reservationService;

    public function __construct(
        ParticipantParser $participantParser,
        RequestParser $requestParser,
        MeetingRoomReservationService $reservationService
    ) {
        $this->participantParser = $participantParser;
        $this->requestParser = $requestParser;
        $this->reservationService = $reservationService;
    }

    /**
     * Check if input matches quick form pattern
     */
    public function isQuickForm(string $input): bool
    {
        $input = trim($input);

        // Check for user-friendly format (multi-line with labels)
        if ($this->isUserFriendlyFormat($input)) {
            return true;
        }

        // Check for semicolon-separated format (legacy)
        return $this->isSemicolonFormat($input);
    }

    /**
     * Check if input is user-friendly multi-line format
     */
    private function isUserFriendlyFormat(string $input): bool
    {
        $requiredFields = ['Tanggal:', 'Peserta:', 'Ruangan:', 'Waktu:', 'Judul:', 'Deskripsi:', 'Daftar Peserta:', 'Request:'];

        foreach ($requiredFields as $field) {
            if (stripos($input, $field) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if input is semicolon-separated format
     */
    private function isSemicolonFormat(string $input): bool
    {
        $fields = explode(';', $input);

        // Must have at least 6 fields
        if (count($fields) < 6) {
            return false;
        }

        // First field should be date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($fields[0]))) {
            return false;
        }

        // Second field should be numeric (participants count)
        if (!is_numeric(trim($fields[1]))) {
            return false;
        }

        // Third field should be numeric (room ID)
        if (!is_numeric(trim($fields[2]))) {
            return false;
        }

        // Fourth field should be time slot format
        if (!preg_match('/^\d{2}:\d{2}-\d{2}:\d{2}$/', trim($fields[3]))) {
            return false;
        }

        return true;
    }

    /**
     * Parse and process quick form submission
     */
    public function parse(string $input, User $user): array
    {
        try {
            $input = trim($input);

            // Determine format and parse accordingly
            if ($this->isUserFriendlyFormat($input)) {
                return $this->parseUserFriendlyFormat($input, $user);
            } else {
                return $this->parseSemicolonFormat($input, $user);
            }
        } catch (\Exception $e) {
            Log::error('Error parsing quick form', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'Terjadi kesalahan: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Parse user-friendly multi-line format
     */
    private function parseUserFriendlyFormat(string $input, User $user): array
    {
        $lines = explode("\n", $input);
        $data = [];
        $participantLines = [];
        $collectingParticipants = false;

        foreach ($lines as $line) {
            $line = trim($line);

            if (empty($line)) {
                continue;
            }

            // Check if we're in participant collection mode
            if (stripos($line, 'Daftar Peserta:') !== false) {
                $collectingParticipants = true;
                continue;
            }

            // Check if we've moved past participants section
            if ($collectingParticipants && stripos($line, 'Request:') !== false) {
                $collectingParticipants = false;
                // Extract request value
                $data['request'] = $this->extractValue($line, 'Request:');
                continue;
            }

            // Collect participant lines
            if ($collectingParticipants) {
                if (str_starts_with($line, '-')) {
                    $participantLines[] = ltrim($line, '- ');
                }
                continue;
            }

            // Parse labeled fields
            if (stripos($line, 'Tanggal:') !== false) {
                $data['date'] = $this->extractValue($line, 'Tanggal:');
            } elseif (stripos($line, 'Peserta:') !== false) {
                $data['participants_count'] = (int)$this->extractValue($line, 'Peserta:');
            } elseif (stripos($line, 'Ruangan:') !== false) {
                $data['room_id'] = (int)$this->extractValue($line, 'Ruangan:');
            } elseif (stripos($line, 'Waktu:') !== false) {
                $data['time_slot'] = $this->extractValue($line, 'Waktu:');
            } elseif (stripos($line, 'Judul:') !== false) {
                $data['title'] = $this->extractValue($line, 'Judul:');
            } elseif (stripos($line, 'Deskripsi:') !== false) {
                $data['description'] = $this->extractValue($line, 'Deskripsi:');
            }
        }

        // Process participant lines
        $data['participant_list'] = $participantLines;

        return $this->processQuickFormData($data, $user);
    }

    /**
     * Extract value after label
     */
    private function extractValue(string $line, string $label): string
    {
        $pos = stripos($line, $label);
        if ($pos !== false) {
            return trim(substr($line, $pos + strlen($label)));
        }
        return '';
    }

    /**
     * Parse semicolon-separated format (legacy support)
     */
    private function parseSemicolonFormat(string $input, User $user): array
    {
        $fields = array_map('trim', explode(';', $input));

        if (count($fields) < 6) {
            return [
                'success' => false,
                'error' => 'Format tidak lengkap. Minimal 6 field diperlukan.'
            ];
        }

        $data = [
            'date' => $fields[0],
            'participants_count' => (int)$fields[1],
            'room_id' => (int)$fields[2],
            'time_slot' => $fields[3],
            'title' => $fields[4],
            'description' => $fields[5] === '-' ? null : $fields[5],
            'participants_raw' => $fields[6] ?? '-',
            'request' => $fields[7] ?? '-',
        ];

        return $this->processQuickFormData($data, $user);
    }

    /**
     * Process extracted data and create reservation
     */
    private function processQuickFormData(array $data, User $user): array
    {
        // Validate required fields
        $required = ['date', 'participants_count', 'room_id', 'time_slot', 'title'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return [
                    'success' => false,
                    'error' => "Field '{$field}' wajib diisi."
                ];
            }
        }

        // Validate date
        try {
            $inputDate = Carbon::parse($data['date'])->startOfDay();
            $today = Carbon::now()->startOfDay();

            if ($inputDate->lt($today)) {
                return [
                    'success' => false,
                    'error' => 'Tanggal tidak valid. Gunakan tanggal hari ini atau setelahnya.'
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Format tanggal tidak valid. Gunakan format YYYY-MM-DD'
            ];
        }

        // Validate time slot
        if (!preg_match('/^(\d{2}:\d{2})-(\d{2}:\d{2})$/', $data['time_slot'], $matches)) {
            return [
                'success' => false,
                'error' => 'Format waktu tidak valid. Gunakan format HH:MM-HH:MM'
            ];
        }

        $startTime = $matches[1];
        $endTime = $matches[2];

        // Parse participants from list
        $participants = $this->parseParticipantsList($data);

        // Parse request/facilities
        $request = $this->parseRequestData($data['request'] ?? '-');

        // Build reservation data
        $reservationData = [
            'meeting_room_id' => $data['room_id'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'start_time' => $data['date'] . ' ' . $startTime,
            'end_time' => $data['date'] . ' ' . $endTime,
            'participants' => $participants,
            'request' => $request,
        ];

        Log::info('Creating reservation from quick form', $reservationData);

        Log::info('Creating reservation from quick form', [
            'user_id' => $user->id,
            'room_id' => $data['room_id'],
            'format' => isset($data['participant_list']) ? 'user_friendly' : 'semicolon'
        ]);

        try {
            // Create reservation
            $reservation = $this->reservationService->createReservation(
                $reservationData,
                $user->profile->phone ?? $user->id
            );

            if (!$reservation) {
                return [
                    'success' => false,
                    'error' => 'Gagal membuat reservasi. Silakan coba lagi.'
                ];
            }

            // Success response
            return [
                'success' => true,
                'message' => $this->formatSuccessMessage($reservation, $data, $startTime, $endTime)
            ];
        } catch (\Exception $e) {
            Log::error('Failed to create reservation from quick form', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'Gagal membuat reservasi: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Parse participants from different formats
     */
    private function parseParticipantsList(array $data): array
    {
        $participants = [];

        // User-friendly format (participant_list)
        if (isset($data['participant_list']) && is_array($data['participant_list'])) {
            foreach ($data['participant_list'] as $participantLine) {
                $participant = $this->parseParticipantLine($participantLine);
                if ($participant) {
                    $participants[] = $participant;
                }
            }
        }
        // Semicolon format (participants_raw)
        elseif (isset($data['participants_raw']) && $data['participants_raw'] !== '-') {
            try {
                $participantsRaw = str_replace('|', ';', $data['participants_raw']);
                $participants = $this->participantParser->parse($participantsRaw);
            } catch (\Exception $e) {
                Log::warning('Failed to parse participants', ['error' => $e->getMessage()]);
            }
        }

        return $participants;
    }

    /**
     * Parse single participant line from user-friendly format
     * Format: "John Doe (john@example.com)" or "John Doe" or "123"
     */
    private function parseParticipantLine(string $line): ?array
    {
        $line = trim($line);

        if (empty($line)) {
            return null;
        }

        // Check if it's a user ID (numeric only)
        if (is_numeric($line)) {
            $userExists = User::where('id', (int)$line)->exists();

            if ($userExists) {
                return [
                    'user_id' => (int)$line,
                    'name' => null,
                    'email' => null,
                    'whatsapp_number' => null,
                ];
            }
            return null;
        }

        // Parse format: "Name (email)" or "Name (phone)"
        if (preg_match('/^(.+?)\s*\((.+?)\)$/', $line, $matches)) {
            $name = trim($matches[1]);
            $contact = trim($matches[2]);

            $participant = [
                'user_id' => null,
                'name' => $name,
                'email' => null,
                'whatsapp_number' => null,
            ];

            // Determine if contact is email or phone
            if (filter_var($contact, FILTER_VALIDATE_EMAIL)) {
                $participant['email'] = $contact;
            } else {
                $participant['whatsapp_number'] = preg_replace('/[^0-9]/', '', $contact);
            }

            return $participant;
        }

        // Just a name without contact
        return [
            'user_id' => null,
            'name' => $line,
            'email' => null,
            'whatsapp_number' => null,
        ];
    }

    /**
     * Parse request/facilities data
     */
    private function parseRequestData(string $requestStr): array
    {
        if (empty($requestStr) || $requestStr === '-') {
            return [];
        }

        // Simple comma-separated list (e.g., "Proyektor, Snack, Kopi")
        $items = array_filter(array_map('trim', explode(',', $requestStr)));

        // Try to categorize items
        $snackKeywords = ['snack', 'kopi', 'teh', 'makanan', 'minuman', 'coffee', 'tea'];
        $equipmentKeywords = ['proyektor', 'projector', 'mic', 'speaker', 'laptop', 'whiteboard', 'marker'];

        $request = [
            'funds_amount' => null,
            'funds_reason' => null,
            'snacks' => [],
            'equipment' => [],
        ];

        foreach ($items as $item) {
            $itemLower = strtolower($item);
            $isSnack = false;
            $isEquipment = false;

            // Check if it's a snack
            foreach ($snackKeywords as $keyword) {
                if (stripos($itemLower, $keyword) !== false) {
                    $request['snacks'][] = $item;
                    $isSnack = true;
                    break;
                }
            }

            // Check if it's equipment
            if (!$isSnack) {
                foreach ($equipmentKeywords as $keyword) {
                    if (stripos($itemLower, $keyword) !== false) {
                        $request['equipment'][] = $item;
                        $isEquipment = true;
                        break;
                    }
                }
            }

            // If not categorized, add to equipment by default
            if (!$isSnack && !$isEquipment) {
                $request['equipment'][] = $item;
            }
        }

        return $request;
    }

    /**
     * Format success message
     */
    private function formatSuccessMessage($reservation, array $data, string $startTime, string $endTime): string
    {
        return "✅ *Reservasi Berhasil Dibuat!*\n\n"
            . "📋 ID Reservasi: {$reservation->id}\n"
            . "📝 Judul: {$data['title']}\n\n"
            . "🏢 Ruangan : {$reservation->room->name}\n"
            . "📅 Tanggal: {$data['date']}\n"
            . "🕐 Waktu: {$startTime} - {$endTime}\n"
            . "Terima kasih ketik 'menu' untuk kembali ke menu utama.";
    }
}
