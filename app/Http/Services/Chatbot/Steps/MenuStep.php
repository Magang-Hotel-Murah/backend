<?php

namespace App\Http\Services\Chatbot\Steps;

use App\Models\User;

class MenuStep extends StepHandler
{
    public function handle(string $userId, string $text, User $user): string
    {
        $input = strtolower(trim($text));

        // Step-by-step reservation flow
        if (in_array($input, ['1', 'reservasi', 'booking'])) {
            $this->transitionTo($userId, 'awaiting_date');
            return "📅 Silakan masukkan tanggal meeting.\n\n"
                . "Format: YYYY-MM-DD\n"
                . "Contoh: 2025-11-10";
        }

        // Quick form template
        if (in_array($input, ['2', 'form', 'quick'])) {
            return $this->getQuickFormTemplate($user);
        }

        // Welcome message
        return "👋 Halo *{$user->name}*!\n\n"
            . "Selamat datang di Sistem Reservasi Meeting Room.\n\n"
            . "🔹 Ketik '1' untuk mulai reservasi (step by step)\n"
            . "🔹 Ketik '2' untuk template form cepat\n"
            . "🔹 Ketik 'help' untuk panduan lengkap";
    }

    /**
     * Get quick form template for user to copy and fill
     */
    private function getQuickFormTemplate(User $user): string
    {
        $today = now()->format('Y-m-d');

        return "📋 *Template Form Reservasi Cepat*\n\n"
            . "Copy template di bawah, isi datanya, lalu kirim kembali:\n\n"
            . "```\n"
            . "Tanggal: {$today}\n"
            . "Peserta: [jumlah peserta]\n"
            . "Ruangan: [ID ruangan]\n"
            . "Waktu: [HH:MM-HH:MM]\n"
            . "Judul: [judul meeting]\n"
            . "Deskripsi: [deskripsi meeting]\n"
            . "Daftar Peserta:\n"
            . "- [Nama] ([email/telp])\n"
            . "- [Nama] ([email/telp])\n"
            . "Request: [item1, item2, ...]\n"
            . "```\n\n"
            . "📝 *Contoh Pengisian:*\n\n"
            . "```\n"
            . "Tanggal: 2025-11-10\n"
            . "Peserta: 5\n"
            . "Ruangan: 2\n"
            . "Waktu: 09:00-11:00\n"
            . "Judul: Meeting Tim Marketing\n"
            . "Deskripsi: Evaluasi campaign Q4\n"
            . "Daftar Peserta:\n"
            . "- John Doe (john@example.com)\n"
            . "- Jane Smith (081234567890)\n"
            . "- Ahmad Rizky\n"
            . "Request: Proyektor, Whiteboard, Snack, Kopi\n"
            . "```\n\n"
            . "💡 *Tips:*\n"
            . "• Field wajib: Tanggal, Peserta, Ruangan, Waktu, Judul\n"
            . "• Deskripsi, Daftar Peserta & Request bisa dikosongkan\n"
            . "• Gunakan tanda '-' di depan nama peserta\n"
            . "• Request pisahkan dengan koma\n\n"
            . "Atau ketik '1' untuk reservasi step by step.";
    }
}
