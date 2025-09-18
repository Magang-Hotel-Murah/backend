<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'division_id')) {
                // $table->dropForeign(['division_id']);
                $table->dropColumn('division_id');
            }

            if (Schema::hasColumn('users', 'position_id')) {
                // $table->dropForeign(['position_id']);
                $table->dropColumn('position_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // rollback → tambahkan lagi kolom kalau perlu
            $table->unsignedBigInteger('division_id')->nullable()->after('email');
            // $table->foreign('division_id')->references('id')->on('divisions')->onDelete('set null');

            $table->unsignedBigInteger('position_id')->nullable()->after('division_id');
            // $table->foreign('position_id')->references('id')->on('positions')->onDelete('set null');
        });
    }
};
