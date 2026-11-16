<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('middle_name')->nullable();

            // HEMIS ma'lumotlari. hemis_id — HEMIS'dagi xodim identifikatori.
            $table->string('hemis_id', 64)->nullable()->unique();
            $table->string('hemis_uuid', 64)->nullable()->unique();
            $table->string('hemis_login', 64)->nullable();
            $table->string('department')->nullable();
            $table->string('picture_url', 500)->nullable();

            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();

            // Zaxira (lokal) kirish faqat administratorlar uchun: login + parol.
            $table->string('login', 64)->nullable()->unique();
            $table->string('password')->nullable();

            $table->json('roles');
            $table->string('status', 16)->default('pending')->index();
            $table->foreignId('managed_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('telegram_chat_id', 32)->nullable()->unique();
            $table->string('telegram_username', 64)->nullable();

            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
    }
};
