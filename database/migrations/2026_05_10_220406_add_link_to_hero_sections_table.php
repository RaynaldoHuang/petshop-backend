<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hero_sections', function (Blueprint $table) {

            if (!Schema::hasColumn('hero_sections', 'link')) {
                $table->string('link')->nullable()->after('image');
            }
        });
    }

    public function down(): void
    {
        Schema::table('hero_sections', function (Blueprint $table) {

            if (Schema::hasColumn('hero_sections', 'link')) {
                $table->dropColumn('link');
            }
        });
    }
};
