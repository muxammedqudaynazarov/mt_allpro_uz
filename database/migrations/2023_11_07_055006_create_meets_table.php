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
        Schema::create('meets', function (Blueprint $table) {
            $table->unsignedBigInteger('meet_id')->unique()->primary();
            $table->text('host_id')->nullable();
            $table->text('topic');
            $table->unsignedBigInteger('_from');
            $table->foreign('_from')->references('hemis_id')->on('users')->onDelete('cascade');
            $table->unsignedBigInteger('_to');
            $table->foreign('_to')->references('hemis_id')->on('groups')->onDelete('cascade');
            $table->enum('status', ['w', 's', 'o'])->default('w');
            $table->text('start_url');
            $table->text('join_url');
            $table->unsignedBigInteger('duration')->default(80);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meets');
    }
};
