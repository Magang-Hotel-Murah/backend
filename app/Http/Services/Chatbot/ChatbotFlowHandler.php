<?php

namespace App\Http\Services\Chatbot;

use App\Models\User;
use App\Http\Services\Chatbot\Steps\StepHandler;
use App\Http\Services\Chatbot\Steps\MenuStep;
use App\Http\Services\Chatbot\Steps\DateStep;
use App\Http\Services\Chatbot\Steps\ParticipantsCountStep;
use App\Http\Services\Chatbot\Steps\RoomSelectionStep;
use App\Http\Services\Chatbot\Steps\TimeSlotStep;
use App\Http\Services\Chatbot\Steps\TitleStep;
use App\Http\Services\Chatbot\Steps\DescriptionStep;
use App\Http\Services\Chatbot\Steps\ParticipantsDetailStep;
use App\Http\Services\Chatbot\Steps\RequestStep;
use App\Http\Services\Chatbot\Parsers\QuickFormParser;
use App\Http\Services\MeetingRoomReservationService;
use Illuminate\Support\Facades\Log;

/**
 * ChatbotFlowHandler - Main orchestrator for chatbot conversation flow
 *
 * Routes messages to appropriate step handlers and manages conversation flow.
 * Uses the Chain of Responsibility pattern for flexible step handling.
 */
class ChatbotFlowHandler
{
    private ChatbotStateManager $stateManager;
    private UserValidator $userValidator;
    private QuickFormParser $quickFormParser;
    private array $stepHandlers = [];

    public function __construct(
        ChatbotStateManager $stateManager,
        UserValidator $userValidator,
        QuickFormParser $quickFormParser
    ) {
        $this->stateManager = $stateManager;
        $this->userValidator = $userValidator;
        $this->quickFormParser = $quickFormParser;
        $this->registerStepHandlers();
    }

    /**
     * Register all step handlers
     */
    private function registerStepHandlers(): void
    {
        $this->stepHandlers = [
            'menu' => app(MenuStep::class),
            'awaiting_date' => app(DateStep::class),
            'awaiting_participants' => app(ParticipantsCountStep::class),
            'show_rooms' => app(RoomSelectionStep::class),
            'awaiting_room_slot' => app(TimeSlotStep::class),
            'awaiting_title' => app(TitleStep::class),
            'awaiting_description' => app(DescriptionStep::class),
            'awaiting_participants_detail' => app(ParticipantsDetailStep::class),
            'awaiting_request' => app(RequestStep::class),
        ];
    }

    /**
     * Process incoming message and return appropriate response
     */
    public function processMessage(string $userId, string $text): string
    {
        try {
            // Validate user
            $user = $this->userValidator->validateUser($userId);
            if (!$user) {
                Log::warning('Unregistered user attempted to use chatbot', ['user_id' => $userId]);
                return "❌ Anda belum terdaftar di sistem. Silakan hubungi administrator untuk mendaftar.";
            }

            // Handle global commands
            if ($globalResponse = $this->handleGlobalCommands($userId, $text)) {
                return $globalResponse;
            }

            // Check for quick form submission (semicolon-separated format)
            if ($quickFormResponse = $this->tryQuickFormSubmission($userId, $text, $user)) {
                return $quickFormResponse;
            }

            // Get current step and handle message
            $currentStep = $this->stateManager->getCurrentStep($userId);

            $stepHandler = $this->getStepHandler($currentStep);
            if (!$stepHandler) {
                Log::error('Unknown step in chatbot flow', [
                    'user_id' => $userId,
                    'step' => $currentStep
                ]);
                $this->stateManager->resetUser($userId);
                return "❌ Terjadi kesalahan. Silakan mulai ulang dengan mengetik '1' atau 'menu'.";
            }

            // Process message through step handler
            return $stepHandler->handle($userId, $text, $user);
        } catch (\Exception $e) {
            Log::error('Error processing chatbot message', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return "❌ Terjadi kesalahan sistem. Silakan coba lagi atau hubungi administrator.\n\nKetik 'reset' untuk memulai ulang.";
        }
    }

    /**
     * Handle global commands (reset, menu, help, etc.)
     */
    private function handleGlobalCommands(string $userId, string $text): ?string
    {
        $command = strtolower(trim($text));

        switch ($command) {
            case 'reset':
            case 'cancel':
            case 'batal':
                $this->stateManager->resetUser($userId);
                Log::info('User reset conversation', ['user_id' => $userId]);
                return "✅ Chat berhasil direset.\n\n"
                    . "🔹 Ketik '1' untuk reservasi step by step\n"
                    . "🔹 Ketik '2' untuk template form cepat\n"
                    . "🔹 Ketik 'list [filter]' untuk cek reservasi Anda\n"
                    . "🔹 Ketik 'help' untuk panduan lengkap";

            case 'menu':
                $this->stateManager->resetUser($userId);
                $user = $this->userValidator->validateUser($userId);
                return "👋 Halo *{$user->name}*!\n\n"
                    . "Selamat datang di Sistem Reservasi Meeting Room.\n\n"
                    . "🔹 Ketik '1' untuk mulai reservasi (step by step)\n"
                    . "🔹 Ketik '2' untuk template form cepat\n"
                    . "🔹 Ketik 'list [filter]' untuk cek reservasi Anda\n"
                    . "🔹 Ketik 'help' untuk panduan lengkap";

            case 'help':
            case 'panduan':
            case 'tolong':
            case 'bantuan':
                return $this->getHelpMessage();

            case 'status':
                return $this->getUserStatus($userId);

            case str_starts_with($command, 'list'):
            case str_starts_with($command, 'reservasi'):
            case str_starts_with($command, 'reservasi saya'):
                Log::info('User checking reservation list', ['user_id' => $userId, 'command' => $command]);

                // Misal input: "list approved"
                $parts = explode(' ', strtolower($command));
                $filter = $parts[1] ?? 'all';

                return $this->getUserReservations($userId, $filter);

            default:
                return null;
        }
    }

    /**
     * Try to process message as quick form submission
     */
    private function tryQuickFormSubmission(string $userId, string $text, User $user): ?string
    {
        if (!$this->quickFormParser->isQuickForm($text)) {
            return null;
        }

        Log::info('Processing quick form submission', ['user_id' => $userId]);

        try {
            $result = $this->quickFormParser->parse($text, $user);
            Log::info('Chatbot step transition', [
                'user_id' => $userId,
                'from_step' => $this->stateManager->getCurrentStep($userId),
                'to_step' => 'confirmation'
            ]);
            if ($result['success']) {
                $this->stateManager->resetUser($userId);
                return $result['message'];
            } else {
                return "❌ " . $result['error'] . "\n\n💡 Gunakan format: YYYY-MM-DD;jumlah_peserta;room_id;HH:MM-HH:MM;judul;deskripsi;peserta;request";
            }
        } catch (\Exception $e) {
            Log::error('Quick form parsing failed', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return null; // Fall through to normal flow
        }
    }

    /**
     * Get step handler by step name
     */
    private function getStepHandler(string $step): ?StepHandler
    {
        return $this->stepHandlers[$step] ?? null;
    }

    /**
     * Get help message
     */
    private function getHelpMessage(): string
    {
        return "📖 *Panduan Penggunaan Bot Reservasi*\n\n"
            . "🎯 *Perintah Utama:*\n"
            . "• Ketik '1' - Reservasi step by step\n"
            . "• Ketik '2' - Tampilkan template form cepat\n"
            . "• Ketik 'list [filter]' - Cek reservasi Anda\n"
            . "• Ketik 'menu' - Kembali ke menu utama\n"
            . "• Ketik 'reset' - Batalkan & mulai ulang\n"
            . "• Ketik 'status' - Cek status percakapan\n\n"
            . "📋 *Cara 1: Step by Step*\n"
            . "Ketik '1' dan bot akan memandu Anda langkah demi langkah.\n\n"
            . "📝 *Cara 2: Form Cepat*\n"
            . "Ketik '2' untuk mendapat template, isi, dan kirim kembali.\n\n"
            . "Format:\n"
            . "Tanggal: YYYY-MM-DD\n"
            . "Peserta: [jumlah]\n"
            . "Ruangan: [ID]\n"
            . "Waktu: HH:MM-HH:MM\n"
            . "Judul: [judul]\n"
            . "Deskripsi: [deskripsi]\n"
            . "Daftar Peserta:\n"
            . "- Nama (email/telp)\n"
            . "Request: item1, item2\n\n"
            . "💡 *Tips:*\n"
            . "• Deskripsi, Daftar Peserta & Request boleh dikosongkan\n"
            . "• Gunakan '-' di depan nama peserta\n"
            . "• Request pisahkan dengan koma\n"
            . "• filter: 'all', 'approved', 'rejected', 'pending', 'upcoming', 'finished', 'today'";
    }

    /**
     * Get user's current conversation status
     */
    private function getUserStatus(string $userId): string
    {
        if (!$this->stateManager->hasActiveConversation($userId)) {
            return "ℹ️ Tidak ada percakapan aktif.\n\n"
                . "🔹 Ketik '1' untuk reservasi step by step\n"
                . "🔹 Ketik '2' untuk template form cepat";
        }

        $step = $this->stateManager->getCurrentStep($userId);
        $data = $this->stateManager->getData($userId);

        $stepNames = [
            'menu' => 'Menu Utama',
            'awaiting_date' => 'Menunggu Tanggal',
            'awaiting_participants' => 'Menunggu Jumlah Peserta',
            'show_rooms' => 'Pilih Ruangan',
            'awaiting_room_slot' => 'Pilih Waktu',
            'awaiting_title' => 'Menunggu Judul',
            'awaiting_description' => 'Menunggu Deskripsi',
            'awaiting_participants_detail' => 'Menunggu Detail Peserta',
            'awaiting_request' => 'Menunggu Request',
        ];

        $stepName = $stepNames[$step] ?? $step;
        $progress = array_filter($data);

        return "📊 *Status Percakapan*\n\n"
            . "📍 Langkah: {$stepName}\n"
            . "📝 Data tersimpan: " . count($progress) . " field\n\n"
            . "💡 Ketik 'reset' untuk membatalkan dan mulai ulang\n"
            . "💡 Ketik 'menu' untuk kembali ke menu utama";
    }

    /**
     * Get user's active reservation list
     */
    private function getUserReservations(string $userId, string $filter = 'all'): string
    {
        $user = $this->userValidator->validateUser($userId);
        if (!$user) {
            return "❌ Anda belum terdaftar.";
        }

        Log::info('Getting user reservations', [
            'user_id' => $user->id,
            'filter' => $filter,
        ]);

        $query = \App\Models\MeetingRoomReservation::where('user_id', $user->id)
            ->with('room')
            ->orderBy('start_time', 'asc');

        // 🔹 Filter berdasarkan status
        switch (strtolower($filter)) {
            case 'approved':
                $query->where('status', 'approved');
                break;
            case 'rejected':
                $query->where('status', 'rejected');
                break;
            case 'pending':
                $query->where('status', 'pending');
                break;
            case 'today':
                $query->whereDate('start_time', now()->toDateString())->where('status', 'approved');
                break;
            case 'upcoming':
                $query->where('start_time', '>=', now()->startOfDay())->where('status', 'approved');
                break;
            case 'finished':
                $query->where('end_time', '<=', now()->startOfDay())->where('status', 'approved');
                break;
            case 'all':
            default:
                // tidak ada tambahan filter
                break;
        }

        $reservations = $query->limit(15)->get();

        if ($reservations->isEmpty()) {
            return "ℹ️ Tidak ada reservasi dengan filter *{$filter}*.";
        }

        $responseMessage = "🗓️ *Daftar Reservasi ({$filter}):*\n\n";

        foreach ($reservations as $res) {
            $responseMessage .=
                "• *{$res->title}*\n" .
                "📋 ID Reservasi: {$res->id}\n" .
                "🏢 Ruangan: {$res->room->name}\n" .
                "📍 Lokasi: {$res->room->location}\n" .
                "📅 Tanggal: {$res->start_time->translatedFormat('l, d F Y')}\n" .
                "🕐 Waktu: {$res->start_time->format('H:i')} - {$res->end_time->format('H:i')}\n" .
                "📊 Status: *{$res->status}*\n\n";
        }

        $responseMessage .= "💡 Ketik 'menu' untuk kembali ke menu utama.";

        return $responseMessage;
    }
}
