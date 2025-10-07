<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meeting_room_reservations', function (Blueprint $table) {
            $table->foreignId('company_id')->after('id')->constrained('companies')->cascadeOnDelete();
        });

        Schema::table('meeting_requests', function (Blueprint $table) {
            $table->foreignId('company_id')->after('id')->constrained('companies')->cascadeOnDelete();
        });

        Schema::table('meeting_participants', function (Blueprint $table) {
            $table->foreignId('company_id')->after('id')->constrained('companies')->cascadeOnDelete();
        });

        Schema::table('meeting_rooms', function (Blueprint $table) {
            $table->foreignId('company_id')->after('id')->constrained('companies')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('meeting_room_reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
        });

        Schema::table('meeting_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
        });

        Schema::table('meeting_participants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
        });

        Schema::table('meeting_rooms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
        });
    }
};
