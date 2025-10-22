<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('meeting_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained('meeting_room_reservations')->cascadeOnDelete();
            $table->decimal('funds_amount', 12, 2)->nullable();
            $table->text('funds_reason')->nullable();
            $table->json('snacks')->nullable();
            $table->json('equipment')->nullable();
            $table->enum('status', [
                'pending',          // default setelah dibuat
                'waiting_finance',  // menunggu approval finance (jika ada cost)
                'approved',         // sudah disetujui (admin/finance)
                'rejected',
                'cancelled'
            ])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_requests');
    }
};
