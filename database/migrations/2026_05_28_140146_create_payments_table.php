<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {

            $table->id();

            $table->foreignId('order_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('transaction_id')
                ->unique();

            $table->string('midtrans_order_id')
                ->unique();

            $table->string('payment_method');

            $table->string('type');

            $table->bigInteger('gross_amount');

            /*
            =========================================
            QRIS
            =========================================
            */
            $table->text('qr_url')
                ->nullable();

            /*
            =========================================
            VA
            =========================================
            */
            $table->string('va_number')
                ->nullable();

            $table->string('bank')
                ->nullable();

            /*
            =========================================
            STATUS
            =========================================
            */
            $table->string('status')
                ->default('pending');

            $table->timestamp('paid_at')
                ->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
