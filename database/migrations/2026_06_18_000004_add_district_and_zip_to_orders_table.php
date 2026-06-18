<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('shipping_destination_id')->nullable()->after('shipping_city');
            $table->string('shipping_district_id')->nullable()->after('shipping_destination_id');
            $table->string('shipping_district')->nullable()->after('shipping_district_id');
            $table->string('shipping_subdistrict_id')->nullable()->after('shipping_district');
            $table->string('shipping_subdistrict')->nullable()->after('shipping_subdistrict_id');
            $table->string('shipping_zip_code')->nullable()->after('shipping_subdistrict');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'shipping_destination_id',
                'shipping_district_id',
                'shipping_district',
                'shipping_subdistrict_id',
                'shipping_subdistrict',
                'shipping_zip_code',
            ]);
        });
    }
};
