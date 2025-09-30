<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ppob_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('service_type'); // listrik, pulsa, air, dll
            $table->string('customer_number');
            $table->decimal('amount', 12, 2);
            $table->string('status')->default('pending'); // pending, success, failed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ppob_transactions');
    }
};
