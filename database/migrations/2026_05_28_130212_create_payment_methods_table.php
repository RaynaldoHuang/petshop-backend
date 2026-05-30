<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {

            $table->id();

            /*
            =========================================
            BASIC
            =========================================
            */
            $table->string('name');
            $table->string('code')->unique();

            /*
            =========================================
            TYPE
            qris
            bank_transfer
            ewallet
            =========================================
            */
            $table->string('type');

            /*
            =========================================
            ADMIN FEE
            =========================================
            */
            $table->integer('fee')->default(0);

            /*
            =========================================
            ICON
            =========================================
            */
            $table->string('icon')->nullable();

            /*
            =========================================
            ACTIVE
            =========================================
            */
            $table->boolean('is_active')
                ->default(true);

            /*
            =========================================
            SORT
            =========================================
            */
            $table->integer('sort_order')
                ->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
