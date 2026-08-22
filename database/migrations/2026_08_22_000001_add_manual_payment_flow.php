<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_settings', function (Blueprint $table) {
            $table->id();
            $table->string('mode')->default('realtime');
            $table->string('manual_qris_path')->nullable();
            $table->string('manual_qris_mime')->nullable();
            $table->string('whatsapp_number', 30)->nullable();
            $table->timestamps();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('payment_mode')->default('realtime')->after('type');
            $table->string('proof_path')->nullable()->after('bank');
            $table->string('proof_original_name')->nullable()->after('proof_path');
            $table->timestamp('proof_submitted_at')->nullable()->after('proof_original_name');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn([
                'payment_mode',
                'proof_path',
                'proof_original_name',
                'proof_submitted_at',
            ]);
        });

        Schema::dropIfExists('payment_settings');
    }
};
