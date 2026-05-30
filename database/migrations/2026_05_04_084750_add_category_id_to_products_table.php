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
        Schema::table('products', function (Blueprint $table) {

            // tambah column jika belum ada
            if (!Schema::hasColumn('products', 'category_id')) {

                $table->foreignId('category_id')
                    ->nullable()
                    ->constrained('categories')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {

            if (Schema::hasColumn('products', 'category_id')) {

                $table->dropForeign(['category_id']);
                $table->dropColumn('category_id');
            }
        });
    }
};
