<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ma'ruza: bir Zoom uchrashuvida bir nechta guruh. Darsning (va haftalik darsning)
 * barcha guruhlari alohida jadvalda; lessons.group_id — birinchi (asosiy) guruh.
 * Mavjud darslar o'z guruhi bilan ko'chiriladi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->boolean('is_lecture')->default(false)->after('auto_end');
        });
        Schema::table('lesson_series', function (Blueprint $table) {
            $table->boolean('is_lecture')->default(false)->after('topic');
        });

        Schema::create('lesson_group', function (Blueprint $table) {
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->constrained()->restrictOnDelete();
            $table->primary(['lesson_id', 'group_id']);
            $table->index('group_id');
        });
        Schema::create('lesson_series_group', function (Blueprint $table) {
            $table->foreignId('lesson_series_id')->constrained('lesson_series')->cascadeOnDelete();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->primary(['lesson_series_id', 'group_id']);
            $table->index('group_id');
        });

        DB::statement('INSERT INTO lesson_group (lesson_id, group_id) SELECT id, group_id FROM lessons');
        DB::statement('INSERT INTO lesson_series_group (lesson_series_id, group_id) SELECT id, group_id FROM lesson_series');
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_series_group');
        Schema::dropIfExists('lesson_group');
        Schema::table('lesson_series', fn (Blueprint $table) => $table->dropColumn('is_lecture'));
        Schema::table('lessons', fn (Blueprint $table) => $table->dropColumn('is_lecture'));
    }
};
