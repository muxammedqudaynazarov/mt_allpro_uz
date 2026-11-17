<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zoom'dagi haqiqiy holatni kuzatish va davomat:
 *  - lessons: Zoom'da qachon boshlangan/tugagani, hisobot olinganligi;
 *  - students: guruh talabalari (Zoom'dagi ism bo'yicha aniqlanadi);
 *  - student_aliases: talabani tanish kalitlari (normallashtirilgan ism, Zoom ID, email);
 *  - zoom_participations: Zoom hisobotidagi har bir kirish-chiqish (xom ma'lumot);
 *  - lesson_attendances: dars bo'yicha talabaning yakuniy qatnashuvi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            // null — hali kuzatilmagan, waiting — boshlanmagan, started — efirda, ended — tugagan, missed — o'tkazilmagan
            $table->string('zoom_state', 16)->nullable()->after('status')->index();
            $table->timestamp('zoom_started_at')->nullable()->after('zoom_state');
            $table->timestamp('zoom_ended_at')->nullable()->after('zoom_started_at');
            $table->unsignedInteger('zoom_minutes')->nullable()->after('zoom_ended_at');
            $table->timestamp('zoom_checked_at')->nullable()->after('zoom_minutes');
            $table->timestamp('attendance_due_at')->nullable()->after('zoom_checked_at')->index();
            $table->timestamp('attendance_synced_at')->nullable()->after('attendance_due_at');
            $table->unsignedTinyInteger('attendance_attempts')->default(0)->after('attendance_synced_at');
            $table->string('attendance_error', 255)->nullable()->after('attendance_attempts');
        });

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            // "Talaba emas" (o'qituvchining shaxsiy hisobi, mehmon) — hisobotlarda hisobga olinmaydi.
            $table->boolean('is_ignored')->default(false);
            $table->timestamps();

            $table->index(['group_id', 'name']);
        });

        Schema::create('student_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16); // name | zoom_id | email
            $table->string('value', 191);
            $table->timestamps();

            $table->unique(['group_id', 'type', 'value']);
        });

        Schema::create('zoom_participations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_host')->default(false);
            $table->string('meeting_uuid', 64);
            $table->string('name', 120);
            $table->string('email', 191)->nullable();
            $table->string('zoom_user_id', 64)->nullable();
            $table->timestamp('joined_at');
            $table->timestamp('left_at');

            $table->index(['lesson_id', 'student_id']);
            $table->index('student_id');
        });

        Schema::create('lesson_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->timestamp('first_joined_at');
            $table->timestamp('last_left_at');
            // Ustma-ust tushgan vaqt (bir nechta qurilma) bir marta hisoblanadi.
            $table->unsignedInteger('seconds');
            // Dars Zoom'da boshlangandan keyin necha soniyada kirgani.
            $table->unsignedInteger('late_seconds')->default(0);
            $table->unsignedSmallInteger('sessions')->default(1);

            $table->unique(['lesson_id', 'student_id']);
            $table->index('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_attendances');
        Schema::dropIfExists('zoom_participations');
        Schema::dropIfExists('student_aliases');
        Schema::dropIfExists('students');

        Schema::table('lessons', function (Blueprint $table) {
            $table->dropIndex(['zoom_state']);
            $table->dropIndex(['attendance_due_at']);
            $table->dropColumn([
                'zoom_state', 'zoom_started_at', 'zoom_ended_at', 'zoom_minutes', 'zoom_checked_at',
                'attendance_due_at', 'attendance_synced_at', 'attendance_attempts', 'attendance_error',
            ]);
        });
    }
};
