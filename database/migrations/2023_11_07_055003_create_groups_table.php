<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hemis_id');
            $table->foreignId('admin_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->text('api')->nullable();
            $table->text('account_id')->nullable();
            $table->text('client_id')->nullable();
            $table->text('client_secret')->nullable();
            $table->enum('active', ['0', '1'])->default('1');
            $table->string('course', 2);
            $table->timestamps();
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
