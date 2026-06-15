<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->decimal('fee_percentage', 8, 4)
                ->default(0)
                ->after('fee');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->bigInteger('admin_fee_amount')
                ->default(0)
                ->after('gross_amount');
            $table->bigInteger('admin_fee_tax')
                ->default(0)
                ->after('admin_fee_amount');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['admin_fee_amount', 'admin_fee_tax']);
        });

        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn('fee_percentage');
        });
    }
};
