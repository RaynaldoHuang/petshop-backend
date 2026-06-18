<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('shipping_province_id')->nullable()->after('shipping_address');
            $table->string('shipping_province')->nullable()->after('shipping_province_id');
            $table->string('shipping_city_id')->nullable()->after('shipping_province');
            $table->string('shipping_city')->nullable()->after('shipping_city_id');
            $table->string('shipping_courier')->nullable()->after('shipping_city');
            $table->string('shipping_service')->nullable()->after('shipping_courier');
            $table->integer('shipping_cost')->default(0)->after('shipping_service');
            $table->string('shipping_etd')->nullable()->after('shipping_cost');
            $table->integer('shipping_weight')->default(0)->after('shipping_etd');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'shipping_province_id',
                'shipping_province',
                'shipping_city_id',
                'shipping_city',
                'shipping_courier',
                'shipping_service',
                'shipping_cost',
                'shipping_etd',
                'shipping_weight',
            ]);
        });
    }
};
