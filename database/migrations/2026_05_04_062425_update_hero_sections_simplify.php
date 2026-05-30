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
        Schema::table('hero_sections', function (Blueprint $table) {

            $columns = [
                'main_title',
                'main_description',
                'main_button_text',
                'top_title',
                'top_description',
                'top_image',
                'bottom_left_title',
                'bottom_left_image',
                'bottom_right_title',
                'bottom_right_image',
            ];

            foreach ($columns as $column) {

                if (Schema::hasColumn('hero_sections', $column)) {

                    $table->dropColumn($column);
                }
            }

            // tambah column baru jika belum ada
            if (!Schema::hasColumn('hero_sections', 'title')) {
                $table->string('title')->nullable();
            }

            if (!Schema::hasColumn('hero_sections', 'description')) {
                $table->text('description')->nullable();
            }

            if (!Schema::hasColumn('hero_sections', 'button_text')) {
                $table->string('button_text')->nullable();
            }

            if (!Schema::hasColumn('hero_sections', 'button_link')) {
                $table->string('button_link')->nullable();
            }

            if (!Schema::hasColumn('hero_sections', 'image')) {
                $table->string('image')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
