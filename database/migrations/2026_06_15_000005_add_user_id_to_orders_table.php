<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'user_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->foreignId('user_id')
                    ->nullable()
                    ->after('id')
                    ->constrained()
                    ->nullOnDelete();
            });
        }

        DB::table('orders')
            ->whereNull('user_id')
            ->orderBy('id')
            ->eachById(function ($order) {
                $userId = DB::table('users')
                    ->where('phone', $order->customer_phone)
                    ->value('id');

                if ($userId) {
                    DB::table('orders')
                        ->where('id', $order->id)
                        ->update(['user_id' => $userId]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
