<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('raja_ongkir_settings', function (Blueprint $table) {
            $table->id();
            $table->string('origin_destination_id')->nullable();
            $table->string('origin_province')->nullable();
            $table->string('origin_city')->nullable();
            $table->string('origin_district')->nullable();
            $table->string('origin_subdistrict')->nullable();
            $table->string('origin_zip_code')->nullable();
            $table->integer('default_item_weight')->default(1000);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raja_ongkir_settings');
    }
};
