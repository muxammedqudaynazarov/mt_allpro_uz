<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Foydalanuvchilar onlayn/oflayn holati va kirish tarixi: har bir "tashrif" — tizimga
 * kirgan (yoki tanaffusdan keyin qaytgan) va chiqqan (yoki faolligi to'xtagan) vaqt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_seen_at')->nullable()->after('last_login_at');
            // Shu vaqtgacha onlayn hisoblanadi (har faollikda suriladi, chiqishda — hozir).
            $table->timestamp('online_until')->nullable()->after('last_seen_at');
        });

        Schema::create('user_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('end_reason', 16)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->index(['user_id', 'started_at']);
            $table->index(['user_id', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_visits');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['last_seen_at', 'online_until']));
    }
};
