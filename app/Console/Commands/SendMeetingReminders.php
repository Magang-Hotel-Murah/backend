<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MeetingRoomReservation;
use Carbon\Carbon;
use App\Http\Services\WhatsappService;
use App\Mail\MeetingNotificationMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendMeetingReminders extends Command
{
    protected $signature = 'meetings:send-reminders';
    protected $description = 'Kirim notifikasi sebelum meeting dimulai';

    public function handle()
    {
        $now = Carbon::now();
        $targetTime = $now->copy()->addMinutes(30);

        $meetings = MeetingRoomReservation::with(['user', 'participants', 'request'])
            ->whereBetween('start_time', [$now->toDateTimeString(), $targetTime->toDateTimeString()])
            ->where('reminder_sent', false)
            ->where('status', 'approved')
            ->whereHas('request', function ($query) {
                $query->where('status', 'approved');
            })
            ->get();

        Log::info("Scheduler run at {$now->toDateTimeString()}. Found {$meetings->count()} meetings.");

        if ($meetings->isEmpty()) {
            Log::info('Tidak ada meeting yang akan dimulai dalam 30 menit.');
            return 0;
        }

        $waService = new WhatsappService();

        foreach ($meetings as $meeting) {
            $title = $meeting->title ?? 'Meeting Tanpa Judul';
            $start = Carbon::parse($meeting->start_time)->format('H:i');
            $message = "📅 Reminder: Meeting *{$title}* akan dimulai pada *{$start}*. Mohon persiapkan diri.";

            if ($meeting->user) {
                $phone = $meeting->user->profile->phone ?? null;
                $email = $meeting->user->email ?? null;

                try {
                    if ($phone) {
                        $waService->send($phone, $message);
                        Log::info("WA dikirim ke {$phone} untuk meeting ID: {$meeting->id}");
                    }
                } catch (\Exception $e) {
                    Log::error("Gagal kirim WA ke {$phone}: " . $e->getMessage());
                }

                try {
                    if ($email) {
                        Mail::to($email)->send(new MeetingNotificationMail('Meeting Reminder', $message));
                        Log::info("Email dikirim ke {$email} untuk meeting ID: {$meeting->id}");
                    }
                } catch (\Exception $e) {
                    Log::error("Gagal kirim email ke {$email}: " . $e->getMessage());
                }
            }

            foreach ($meeting->participants as $participant) {
                $email = optional($participant->user)->email ?? $participant->email ?? null;
                $phone = optional(optional($participant->user)->profile)->phone ?? $participant->whatsapp_number ?? null;

                try {
                    if ($phone) {
                        $waService->send($phone, $message);
                        Log::info("WA dikirim ke participant {$phone} untuk meeting ID: {$meeting->id}");
                    }
                } catch (\Exception $e) {
                    Log::error("Gagal kirim WA ke participant {$phone}: " . $e->getMessage());
                }

                try {
                    if ($email) {
                        Mail::to($email)->send(new MeetingNotificationMail('Meeting Reminder', $message));
                        Log::info("Email dikirim ke participant {$email} untuk meeting ID: {$meeting->id}");
                    }
                } catch (\Exception $e) {
                    Log::error("Gagal kirim email ke participant {$email}: " . $e->getMessage());
                }
            }

            $meeting->reminder_sent = true;
            $meeting->save();
        }

        Log::info('Semua notifikasi meeting berhasil diproses.');
        return 0;
    }
}
