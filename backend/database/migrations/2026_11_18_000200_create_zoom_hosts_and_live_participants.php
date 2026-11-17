<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - zoom_hosts: dars o'tkaziladigan Zoom foydalanuvchilari (litsenziyalar). O'qituvchi
 *   dars yaratishda shulardan bo'shini tanlaydi; guruh endi bitta hostga bog'lanmaydi
 *   (guruhdagi host — faqat standart tanlov).
 * - zoom_accounts.webhook_secret: Zoom webhook'larini tekshirish kaliti (Secret Token).
 * - live_participants: efirdagi darsning hozirgi qatnashchilari (webhook orqali).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zoom_hosts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zoom_account_id')->constrained()->cascadeOnDelete();
            $table->string('zoom_user_id', 64)->nullable();
            $table->string('email', 191);
            $table->string('name', 191);
            // 1 — Basic (uchrashuv 40 daqiqa bilan cheklangan), 2 — Licensed.
            $table->unsignedTinyInteger('type')->default(2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['zoom_account_id', 'email']);
        });

        Schema::table('zoom_accounts', function (Blueprint $table) {
            $table->text('webhook_secret')->nullable()->after('client_secret');
            $table->timestamp('webhook_received_at')->nullable()->after('webhook_secret');
        });

        Schema::table('groups', function (Blueprint $table) {
            $table->unsignedBigInteger('zoom_account_id')->nullable()->change();
            $table->string('zoom_user_email')->nullable()->change();
        });

        Schema::table('lessons', function (Blueprint $table) {
            // Har haftalik takroriy darsdan yaratilgan bo'lsa (lesson_series).
            $table->unsignedBigInteger('lesson_series_id')->nullable()->after('lesson_slot_id')->index();
        });

        Schema::create('live_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->string('participant_key', 64);
            $table->string('name', 120);
            $table->string('email', 191)->nullable();
            $table->boolean('is_host')->default(false);
            $table->timestamp('joined_at');
            $table->timestamp('left_at')->nullable();

            $table->index(['lesson_id', 'participant_key']);
        });

        // Mavjud guruhlardagi hostlar ro'yxatga avtomatik qo'shiladi.
        $now = now();
        DB::table('groups')->whereNotNull('zoom_account_id')->whereNotNull('zoom_user_email')
            ->select(['zoom_account_id', 'zoom_user_email'])->distinct()->get()
            ->each(fn (object $group) => DB::table('zoom_hosts')->insertOrIgnore([
                'zoom_account_id' => $group->zoom_account_id,
                'email' => mb_strtolower(trim($group->zoom_user_email)),
                'name' => mb_strtolower(trim($group->zoom_user_email)),
                'type' => 2,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
    }

    public function down(): void
    {
        Schema::dropIfExists('live_participants');
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropIndex(['lesson_series_id']);
            $table->dropColumn('lesson_series_id');
        });
        Schema::table('zoom_accounts', function (Blueprint $table) {
            $table->dropColumn(['webhook_secret', 'webhook_received_at']);
        });
        Schema::dropIfExists('zoom_hosts');
    }
};
