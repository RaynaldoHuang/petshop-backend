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
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // BASIC
            $table->string('name');

            // LOGIN PHONE
            $table->string('phone', 20)->unique();

            // OPTIONAL
            $table->string('email')->nullable()->unique();

            // VERIFICATION
            $table->timestamp('phone_verified_at')->nullable();

            // PROFILE
            $table->string('avatar')->nullable();

            // STATUS
            $table->boolean('is_active')->default(true);

            // AUTH
            $table->string('password');

            $table->rememberToken();

            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | PASSWORD RESET TOKENS
        |--------------------------------------------------------------------------
        */

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('phone')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        /*
        |--------------------------------------------------------------------------
        | SESSIONS
        |--------------------------------------------------------------------------
        */

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();

            $table->foreignId('user_id')
                ->nullable()
                ->index();

            $table->string('ip_address', 45)->nullable();

            $table->text('user_agent')->nullable();

            $table->longText('payload');

            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');

        Schema::dropIfExists('password_reset_tokens');

        Schema::dropIfExists('sessions');
    }
};
