<?php

namespace App\Http\Services;

use App\Mail\MeetingNotificationMail;
use App\Models\MeetingRoomReservation;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class NotificationService
{
    protected $whatsappService;

    public function __construct(WhatsappService $whatsappService)
    {
        $this->whatsappService = $whatsappService;
    }

    public function notifyUser(MeetingRoomReservation $reservation, string $subject, string $message)
    {
        $user = $reservation->user;

        if ($user?->email) {
            Mail::to($user->email)->send(new MeetingNotificationMail($subject, $message));
        }

        if ($user?->profile?->phone) {
            $this->whatsappService->send($user->profile->phone, $message);
        }
    }

    public function sendApprovalNotification(MeetingRoomReservation $reservation)
    {
        $room  = $reservation->room;
        $title = $reservation->title;

        Carbon::setLocale('id');

        $start = Carbon::parse($reservation->start_time)->timezone('Asia/Jakarta');
        $end   = Carbon::parse($reservation->end_time)->timezone('Asia/Jakarta');

        $tanggal = $start->translatedFormat('l, d F Y');
        $waktu   = "{$start->format('H:i')} - {$end->format('H:i')} WIB";

        $isFinance = $reservation->request && $reservation->request->funds_amount > 0 && $reservation->request->status === 'waiting_finance';

        $messageOwner = $isFinance
            ? "Halo {$reservation->user->name},\nReservasi ruang meeting *{$title}* di *{$room->name}* telah *disetujui oleh admin* dan *menunggu persetujuan keuangan*."
            : "Halo {$reservation->user->name},\nReservasi ruang meeting *{$title}* telah *disetujui*.\n\n📍 *Lokasi:* {$room->name}, {$room->location}\n📅 *Tanggal:* {$tanggal}\n🕒 *Waktu:* {$waktu}";

        $this->notifyUser($reservation, 'Meeting Disetujui', $messageOwner);

        if (
            $reservation->status === 'approved' &&
            (!$reservation->request || $reservation->request->status === 'approved')
        ) {
            foreach ($reservation->participants as $participant) {
                $name = $participant->name ?? ($participant->user->name ?? 'Peserta');
                $messageParticipant = "Halo {$name},\nAnda dijadwalkan untuk meeting *{$title}*.\n📍 *Lokasi:* {$room->name}, {$room->location}\n📅 *Tanggal:* {$tanggal}\n🕒 *Waktu:* {$waktu}";

                $email = $participant->user->email ?? $participant->email ?? null;
                $phone = $participant->user->profile->phone ?? $participant->whatsapp_number ?? null;

                if ($email) {
                    Mail::to($email)->send(new MeetingNotificationMail('Meeting Dijadwalkan', $messageParticipant));
                }

                if ($phone) {
                    $this->whatsappService->send($phone, $messageParticipant);
                }
            }
        }
    }

    public function sendRejectionNotification(MeetingRoomReservation $reservation, string $reason)
    {
        $message = "Halo {$reservation->user->name},\nReservasi ruang meeting *{$reservation->title}* telah *ditolak*.\n\n❌ *Alasan:* {$reason}";
        $this->notifyUser($reservation, 'Reservasi Ditolak', $message);
    }

    public function sendConflictRejectionNotification(MeetingRoomReservation $conflict)
    {
        $reason = $conflict->rejection_reason ?: 'bentrok dengan jadwal yang telah disetujui otomatis oleh sistem.';
        $message = "Halo {$conflict->user->name}, reservasi ruang meeting *{$conflict->title}* telah *ditolak*.\n\n❌ *Alasan:* {$reason}";
        $this->notifyUser($conflict, 'Reservasi Ditolak', $message);
    }

    public function sendReservationCreated(MeetingRoomReservation $reservation)
    {
        $user = $reservation->user;
        $message = "Halo {$user->name},\nReservasi ruang meeting *{$reservation->title}* telah *diajukan* dan menunggu persetujuan.";

        $this->notifyUser($reservation, 'Reservasi Diajukan', $message);
    }
}
