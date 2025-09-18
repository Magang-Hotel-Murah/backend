<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // hapus kolom lama kalau masih ada
            if (Schema::hasColumn('users', 'position')) {
                $table->dropColumn('position');
            }

            $table->unsignedBigInteger('position_id')->nullable()->after('division_id');
            $table->foreign('position_id')->references('id')->on('positions')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['position_id']);
            $table->dropColumn('position_id');
        });
    }
};
