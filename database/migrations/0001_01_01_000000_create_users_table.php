<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensajero_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->dateTime('email_verified_at', 6)->nullable();
            $table->string('password');
            $table->string('role', 24)->default('viewer');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('mensajero_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->dateTime('created_at', 6)->nullable();
        });

        Schema::create('mensajero_sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->bigInteger('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensajero_sessions');
        Schema::dropIfExists('mensajero_password_reset_tokens');
        Schema::dropIfExists('mensajero_users');
    }
};
