<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('transactionable_id');
            $table->string('transactionable_type');
            $table->string('external_id')->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('currency', 5)->default('USD');
            $table->string('payment_method')->nullable();
            $table->enum('payment_status', ['unpaid', 'paid', 'failed'])->default('unpaid');
            $table->dateTime('transaction_date')->nullable(); // lebih konsisten dengan ERD
            $table->timestamps();
            $table->softDeletes();

            $table->index(['transactionable_id', 'transactionable_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
