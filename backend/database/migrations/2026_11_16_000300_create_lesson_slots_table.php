<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_slots', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64);
            $table->string('starts_at', 5); // HH:MM, Toshkent vaqti
            $table->unsignedSmallInteger('duration_minutes')->default(80);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_slots');
    }
};
