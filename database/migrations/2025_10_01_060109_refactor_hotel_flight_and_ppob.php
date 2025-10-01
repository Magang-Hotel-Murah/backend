<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabel Pusat untuk Pembayaran
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transactionable_id');
            $table->string('transactionable_type');
            $table->string('external_id')->nullable()->comment('ID dari payment gateway');
            $table->decimal('amount', 15, 2);
            $table->string('currency', 5)->default('IDR');
            $table->string('payment_method')->nullable();
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'expired'])->default('pending');
            $table->timestamp('paid_at')->nullable()->comment('Waktu transaksi berhasil dibayar');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['transactionable_id', 'transactionable_type']);
        });

        // 2. Tabel Reservasi Hotel
        Schema::create('hotel_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('booking_code')->unique();
            $table->string('hotel_id')->comment('ID hotel dari eksternal API atau tabel lain');
            $table->string('hotel_name');
            $table->date('check_in_date');
            $table->date('check_out_date');
            $table->json('guest_details')->nullable()->comment('Detail tamu, jumlah dewasa & anak');
            $table->decimal('total_price', 15, 2);
            $table->string('currency', 5)->default('IDR');
            $table->enum('status', ['pending', 'confirmed', 'cancelled'])->default('pending');
            $table->timestamps();
        });

        // 3. Tabel Reservasi Penerbangan
        Schema::create('flight_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('booking_code')->unique();
            $table->string('flight_number');
            $table->string('origin');
            $table->string('destination');
            $table->timestamp('departure_time');
            $table->timestamp('arrival_time');
            $table->json('passenger_details')->nullable()->comment('Detail semua penumpang');
            $table->decimal('total_price', 15, 2);
            $table->string('currency', 5)->default('IDR');
            $table->enum('status', ['pending', 'ticketed', 'cancelled'])->default('pending');
            $table->timestamps();
        });

        // 4. Tabel Tagihan PPOB
        Schema::create('ppob_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('invoice_number')->unique();
            $table->string('service_type')->comment('Contoh: PLN, PULSA_TELKOMSEL, PDAM');
            $table->string('customer_number')->comment('Nomor pelanggan/HP');
            $table->decimal('total_price', 15, 2);
            $table->string('currency', 5)->default('IDR');
            $table->enum('status', ['pending', 'success', 'failed'])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ppob_bills');
        Schema::dropIfExists('flight_reservations');
        Schema::dropIfExists('hotel_reservations');
        Schema::dropIfExists('transactions');
    }
};
