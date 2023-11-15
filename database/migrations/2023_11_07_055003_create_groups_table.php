<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->unsignedBigInteger('hemis_id')->primary();
            $table->bigInteger('telegram_id')->nullable();
            $table->string('name');
            $table->string('email');
            $table->text('api')->nullable();
            $table->enum('active', ['0', '1'])->default('1');
            $table->string('course', 2);
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};
