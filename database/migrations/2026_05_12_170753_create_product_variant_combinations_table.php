<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_variant_combinations', function (Blueprint $table) {

            $table->id();

            $table->foreignId('product_variant_item_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('product_option_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('product_option_value_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variant_combinations');
    }
};
