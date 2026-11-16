<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            // Tarix saqlanishi uchun o'qituvchi/guruh o'chirilganda dars o'chmaydi
            // (foydalanuvchi va guruhlar soft-delete qilinadi).
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('group_id')->constrained()->restrictOnDelete();
            $table->foreignId('lesson_slot_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('zoom_account_id')->nullable()->constrained()->nullOnDelete();

            $table->string('zoom_meeting_id', 32)->nullable()->index();
            $table->string('zoom_host_email');
            $table->string('topic', 255);
            $table->text('join_url')->nullable();
            $table->text('start_url')->nullable(); // shifrlangan (ZAK kalitini o'z ichiga oladi)
            $table->string('passcode', 16)->nullable();

            $table->string('status', 16)->default('scheduled')->index();
            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at')->index();
            $table->timestamp('ended_at')->nullable();
            $table->foreignId('ended_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['group_id', 'starts_at']);
            $table->index(['zoom_account_id', 'zoom_host_email', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
