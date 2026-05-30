<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('midtrans_order_id')->nullable()->unique()->after('id');
            $table->string('midtrans_transaction_id')->nullable()->after('midtrans_order_id');
            $table->string('payment_type')->nullable()->after('payment_status');
            $table->json('payment_response')->nullable()->after('payment_type');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'midtrans_order_id',
                'midtrans_transaction_id',
                'payment_type',
                'payment_response',
            ]);
        });
    }
};