<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Http\Services\WhatsappService;


class WebHookController extends Controller
{
    protected $whatsappService;

    public function __construct(WhatsappService $whatsappService)
    {
        $this->whatsappService = $whatsappService;
    }

    public function verify(Request $request)
    {
        $verifyToken = env('WHATSAPP_VERIFY_TOKEN');

        $mode = $request->get('hub_mode');
        $token = $request->get('hub_verify_token');
        $challenge = $request->get('hub_challenge');

        if ($mode && $token && $mode === 'subscribe' && $token === $verifyToken) {
            return response($challenge, 200);
        }

        return response('Forbidden', 403);
    }

    public function handle(Request $request)
    {
        $data = $request->all();

        $message = $data['entry'][0]['changes'][0]['value']['messages'][0] ?? null;
        if ($message) {
            $from = str_replace('@c.us', '', $message['from']);
            $text = $message['text']['body'] ?? '';
            // Log::info("Incoming text message from $from: $text");

            $reply = $this->handleMessage($from, $text);
            $this->sendMessage($from, $reply);
        }

        return response()->json(['status' => 'ok']);
    }

    private function sendMessage($to, $text)
    {
        $response = $this->whatsappService->send($to, $text);

        if (!$response) {
            Log::error("Failed to send message to $to: $text");
        }
    }

    private function normalizePhone($number)
    {
        $number = preg_replace('/[^0-9]/', '', $number);

        if (str_starts_with($number, '0')) {
            $number = '62' . substr($number, 1);
        } elseif (!str_starts_with($number, '62')) {
            $number = '62' . $number;
        }

        return $number;
    }

    private function handleMessage($from, $text)
    {
        $from = str_replace('@c.us', '', $from);

        $waNumberNormalized = $this->normalizePhone($from);
        $user = \App\Models\User::whereHas('profile', function ($q) use ($waNumberNormalized) {
            $q->whereRaw("REPLACE(REPLACE(REPLACE(phone, '+', ''), '-', ''), ' ', '') = ?", [$waNumberNormalized]);
        })->first() ?? null;

        if (!$user) {
            return "Anda belum terdaftar di sistem. Hubungi admin.";
        }

        if (strtolower($text) === 'reset') {
            cache()->forget("wa_user_$from");
            return "Chat sudah direset. Ketik '1' untuk mulai reservasi ulang.";
        }
        $state = cache("wa_user_$from") ?? ['step' => 'menu'];


        $companyId = $user ? $user->company_id : null;

        switch ($state['step']) {
            case 'menu':
                if (strtolower($text) === '1' || strtolower($text) === 'reservasi') {
                    $state['step'] = 'awaiting_date';
                    cache()->put("wa_user_$from", $state, now()->addMinutes(30));
                    return "Silakan masukkan tanggal meeting (YYYY-MM-DD):";
                }
                return "Halo {$user->name}! 👋,\nSelamat datang di Sistem Reservasi Meeting Room. \nKetik '1' untuk mulai reservasi.";

            case 'awaiting_date':
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $text)) {
                    return "Format tanggal salah, gunakan format YYYY-MM-DD (misalnya 2025-10-30).";
                }

                $inputDate = \Carbon\Carbon::parse($text)->startOfDay();
                $today = now()->startOfDay();

                if ($inputDate->lt($today)) {
                    return "Tanggal tidak valid. Silakan masukkan tanggal hari ini atau setelahnya.";
                }

                $state['date'] = $text;
                $state['step'] = 'awaiting_participants';
                cache()->put("wa_user_$from", $state, now()->addMinutes(30));

                return "Berapa jumlah peserta meeting?";

            case 'awaiting_participants':
                if (!is_numeric($text) || intval($text) < 1) {
                    return "Jumlah peserta tidak valid.";
                }
                $state['participants_count'] = intval($text);
                $state['step'] = 'show_rooms';
                cache()->put("wa_user_$from", $state, now()->addMinutes(30));

                $request = new \Illuminate\Http\Request([
                    'date' => $state['date'],
                    'participants_count' => $state['participants_count']
                ]);

                $controller = new \App\Http\Controllers\MeetingRoomController();
                $available = $controller->searchAvailableRooms($request);
                $response = $available->getData();

                $messageText = $response->message ?? 'Tidak ada pesan.';
                $rooms = $response->data->rooms ?? [];

                if ($rooms instanceof \Illuminate\Support\Collection) {
                    $rooms = $rooms->toArray();
                } elseif ($rooms instanceof \stdClass) {
                    $rooms = (array) $rooms;
                }

                Log::info("Rooms available for {$from}", [
                    'message' => $messageText,
                    'rooms' => $rooms,
                ]);

                $rooms = array_filter($rooms, function ($room) use ($companyId) {
                    $companyIdRoom = is_object($room) ? $room->company_id : ($room['company_id'] ?? null);
                    return $companyIdRoom == $companyId;
                });

                cache()->put("wa_user_{$from}_available_rooms", $rooms, now()->addMinutes(30));

                $message = "{$messageText}\n\n";
                if (empty($rooms)) {
                    $message .= "Tidak ada ruangan yang cocok untuk perusahaan Anda.";
                } else {
                    $message .= "Daftar ruangan:\n";
                    foreach ($rooms as $room) {
                        $slots = array_map(fn($s) => "{$s->start_time}-{$s->end_time}", $room->free_slots ?? []);
                        $slotsText = !empty($slots) ? implode(', ', $slots) : 'Tidak ada slot tersedia';
                        $message .= "ID: {$room->id} | {$room->name} | Kapasitas: {$room->capacity}\nJadwal: {$slotsText}\n\n";
                    }
                    $message .= "Ketik ID ruangan yang ingin dipesan, misal: 3";
                }

                return $message;


            case 'show_rooms':
                if (!is_numeric($text)) return "Masukkan ID ruangan valid.";

                $rooms = cache("wa_user_{$from}_available_rooms") ?? [];
                $selectedRoom = null;
                foreach ($rooms as $room) {
                    if ($room->id == intval($text)) {
                        $selectedRoom = $room;
                        break;
                    }
                }

                if (!$selectedRoom) return "ID ruangan tidak valid. Silakan pilih dari daftar yang tersedia.";

                $freeSlots = $selectedRoom->free_slots ?? [];
                if (empty($freeSlots)) return "Ruangan '{$selectedRoom->name}' saat ini tidak memiliki slot tersedia. Silakan pilih ruangan lain.";

                $state['meeting_room_id'] = $selectedRoom->id;
                $state['available_slots'] = $freeSlots;
                $state['step'] = 'awaiting_room_slot';
                cache()->put("wa_user_$from", $state, now()->addMinutes(30));

                $slotsText = implode(', ', array_map(fn($s) => "{$s->start_time}-{$s->end_time}", $freeSlots));
                return "Slot yang tersedia untuk '{$selectedRoom->name}':\n{$slotsText}\nPilih slot dengan format HH:MM-HH:MM, misal: 09:00-10:00";

            case 'awaiting_room_slot':
                if (!preg_match('/^(\d{2}:\d{2})-(\d{2}:\d{2})$/', $text, $matches)) {
                    return "Format salah, gunakan HH:MM-HH:MM";
                }

                $startTime = $matches[1];
                $endTime = $matches[2];

                $slots = $state['available_slots'] ?? [];
                $validSlot = false;
                foreach ($slots as $slot) {
                    if ($startTime >= $slot->start_time && $endTime <= $slot->end_time) {
                        $validSlot = true;
                        break;
                    }
                }

                if (!$validSlot) {
                    $slotsText = implode(', ', array_map(fn($s) => "{$s->start_time}-{$s->end_time}", $slots));
                    return "Waktu tidak sesuai slot yang tersedia. Slot yang tersedia: {$slotsText}";
                }

                $state['start_time'] = $startTime;
                $state['end_time'] = $endTime;
                $state['step'] = 'awaiting_title';
                cache()->put("wa_user_$from", $state, now()->addMinutes(30));

                return "Masukkan judul meeting:";

            case 'awaiting_title':
                $state['title'] = $text;
                $state['step'] = 'awaiting_description';
                cache()->put("wa_user_$from", $state, now()->addMinutes(30));
                return "Masukkan deskripsi meeting (opsional), ketik '-' jika kosong:";

            case 'awaiting_description':
                $state['description'] = $text === '-' ? null : $text;
                $state['step'] = 'awaiting_participants_detail';
                cache()->put("wa_user_$from", $state, now()->addMinutes(30));

                return "Masukkan peserta tambahan (opsional), format: nama,email,whatsapp; pisahkan pakai titik koma, ketik '-' jika tidak ada:";

            case 'awaiting_participants_detail':
                if ($text === '-') {
                    $state['participants'] = [];
                } else {
                    $participants = array_map(function ($p) {
                        $fields = array_map('trim', explode(',', $p));

                        $userId = $fields[0] ?: null;

                        if ($userId && is_numeric($userId)) {
                            $user = \App\Models\User::find($userId);
                            return [
                                'user_id' => $user?->id,
                                'name' => $user?->name,
                                'email' => $user?->email,
                                'whatsapp_number' => $user?->profile?->phone ?? null,
                            ];
                        } else {
                            $participant = [
                                'user_id' => null,
                                'name' => $fields[0] ?? null,
                                'email' => null,
                                'whatsapp_number' => null,
                            ];

                            if (isset($fields[1])) {

                                if (filter_var($fields[1], FILTER_VALIDATE_EMAIL)) {
                                    $participant['email'] = $fields[1];
                                } else {
                                    $participant['whatsapp_number'] = preg_replace('/\D/', '', $fields[1]);
                                }
                            }

                            if (isset($fields[2])) {
                                $participant['whatsapp_number'] = preg_replace('/\D/', '', $fields[2]);
                            }

                            return $participant;
                        }
                    }, explode(';', $text));
                    $state['participants'] = $participants;
                }
                $state['step'] = 'awaiting_request';
                cache()->put("wa_user_$from", $state, now()->addMinutes(30));
                return "Masukkan request opsional (funds_amount,funds_reason;snacks;equipment), format contoh: 50000,Snack untuk meeting;Kopi,Teh;Projector,Mic; ketik '-' jika tidak ada:";

            case 'awaiting_request':
                if ($text === '-') {
                    $state['request'] = [];
                } else {
                    $parts = explode(';', $text);
                    $funds = explode(',', $parts[0] ?? '');
                    $snacks = !empty($parts[1]) ? explode(',', $parts[1]) : [];
                    $equipment = !empty($parts[2]) ? explode(',', $parts[2]) : [];
                    $state['request'] = [
                        'funds_amount' => $funds[0] ?? null,
                        'funds_reason' => $funds[1] ?? null,
                        'snacks' => $snacks,
                        'equipment' => $equipment,
                    ];
                }

                $request = new \Illuminate\Http\Request([
                    'meeting_room_id' => $state['meeting_room_id'],
                    'title' => $state['title'],
                    'description' => $state['description'],
                    'start_time' => $state['date'] . ' ' . $state['start_time'],
                    'end_time' => $state['date'] . ' ' . $state['end_time'],
                    'participants' => $state['participants'] ?? [],
                    'request' => $state['request'] ?? [],
                ]);

                try {
                    $service = new \App\Http\Services\MeetingRoomReservationService();
                    $reservation = $service->createReservation($request->all(), $from);

                    if (!$reservation) {
                        return "Gagal membuat reservasi.";
                    }

                    cache()->put("wa_user_{$from}_just_finished", true, now()->addMinutes(5));

                    cache()->forget("wa_user_$from");
                    return "Detail reservasi!\nID: {$reservation->id}\nRoom: {$state['meeting_room_id']}\nTanggal: {$state['date']}\nWaktu: {$state['start_time']}-{$state['end_time']}";
                } catch (\Exception $e) {
                    return "Gagal membuat reservasi: " . $e->getMessage();
                }

            default:
                cache()->forget("wa_user_$from");
                return "Terjadi kesalahan, silakan mulai ulang dengan mengetik '1'";
        }
    }
}
