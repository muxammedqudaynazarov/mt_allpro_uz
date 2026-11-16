<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zoom_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('account_id', 128);
            $table->string('client_id', 128);
            // Laravel "encrypted" cast orqali APP_KEY bilan shifrlanadi.
            $table->text('client_secret');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_checked_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->timestamps();

            $table->unique(['account_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zoom_accounts');
    }
};
