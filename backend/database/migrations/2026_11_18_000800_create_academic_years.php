<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * O'quv yillari: har birida ikkita semestr — Kuzgi va Bahorgi. Darslar sanasiga
 * qarab semestrga biriktiriladi (arxiv). Holat: planned — rejada, active — joriy,
 * archived — arxiv. Mavjud semestrlar o'quv yillariga avtomatik joylanadi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->string('name', 32)->unique();
            $table->string('status', 16)->default('planned')->index();
            $table->timestamps();
        });

        Schema::table('semesters', function (Blueprint $table) {
            $table->foreignId('academic_year_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            // autumn — Kuzgi, spring — Bahorgi.
            $table->string('type', 8)->nullable()->after('academic_year_id');
            $table->unique(['academic_year_id', 'type']);
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->foreignId('semester_id')->nullable()->after('lesson_series_id')->constrained()->nullOnDelete();
        });

        $now = now();
        foreach (DB::table('semesters')->orderBy('starts_on')->get() as $semester) {
            $start = Carbon::parse($semester->starts_on);
            // Avgust–yanvar — kuzgi, fevral–iyul — bahorgi; o'quv yili sentabrdan boshlanadi.
            $autumn = $start->month >= 8 || $start->month === 1;
            $first = $start->month >= 8 ? $start->year : $start->year - 1;
            $name = $first.'–'.($first + 1);
            $yearId = DB::table('academic_years')->where('name', $name)->value('id')
                ?? DB::table('academic_years')->insertGetId(['name' => $name, 'status' => 'archived', 'created_at' => $now, 'updated_at' => $now]);
            $type = $autumn ? 'autumn' : 'spring';
            if (! DB::table('semesters')->where(['academic_year_id' => $yearId, 'type' => $type])->exists()) {
                DB::table('semesters')->where('id', $semester->id)->update(['academic_year_id' => $yearId, 'type' => $type]);
            }
            DB::table('lessons')->whereBetween('starts_at', [$start->copy()->startOfDay(), Carbon::parse($semester->ends_on)->endOfDay()])
                ->update(['semester_id' => $semester->id]);
        }

        // Bugungi sanani o'z ichiga olgan o'quv yili — joriy.
        $today = $now->toDateString();
        $current = DB::table('semesters')->whereNotNull('academic_year_id')->where('starts_on', '<=', $today)->where('ends_on', '>=', $today)->value('academic_year_id');
        if ($current) {
            DB::table('academic_years')->where('id', $current)->update(['status' => 'active']);
        }
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropConstrainedForeignId('semester_id');
        });
        Schema::table('semesters', function (Blueprint $table) {
            $table->dropUnique(['academic_year_id', 'type']);
            $table->dropConstrainedForeignId('academic_year_id');
            $table->dropColumn('type');
        });
        Schema::dropIfExists('academic_years');
    }
};
