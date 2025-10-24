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
        Schema::create('meeting_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('meeting_rooms')->nullOnDelete();
            $table->string('name');
            $table->integer('capacity');
            $table->json('facilities')->nullable();
            $table->string('location')->nullable();
            $table->enum('type', ['main', 'sub'])->default('main');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meeting_rooms');
    }
};
