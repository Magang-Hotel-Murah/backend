<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('meeting_room_reservations', function (Blueprint $table) {
            $table->boolean('reminder_sent')->default(false)->after('start_time');
        });
    }

    public function down(): void
    {
        Schema::table('meeting_room_reservations', function (Blueprint $table) {
            $table->dropColumn('reminder_sent');
        });
    }
};
