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
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_requests');
    }
};
