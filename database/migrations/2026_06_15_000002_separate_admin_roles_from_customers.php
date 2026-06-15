<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->change();
            $table->string('role', 20)->nullable()->default(null)->change();
        });

        DB::table('users')
            ->where('role', 'customer')
            ->update(['role' => null]);

        DB::table('users')
            ->where('role', 'admin')
            ->update(['role' => 'super_admin']);
    }

    public function down(): void
    {
        DB::table('users')
            ->whereNull('role')
            ->update(['role' => 'customer']);

        DB::table('users')
            ->where('role', 'super_admin')
            ->update(['role' => 'admin']);

        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable(false)->change();
            $table->string('role', 20)->default('customer')->nullable(false)->change();
        });
    }
};
