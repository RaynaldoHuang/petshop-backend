<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->string('meta_title')->nullable()->after('content');
            $table->text('meta_description')->nullable()->after('meta_title');
            $table->string('category')->nullable()->after('meta_description');
            $table->string('tags')->nullable()->after('category');
            $table->unsignedInteger('reading_time')->default(1)->after('tags');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn([
                'meta_title',
                'meta_description',
                'category',
                'tags',
                'reading_time',
            ]);
        });
    }
};
