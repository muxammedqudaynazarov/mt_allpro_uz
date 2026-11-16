<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('hemis_id', 64)->nullable()->unique();
            $table->unsignedTinyInteger('course')->index();
            // Guruhni o'chirish faqat Zoom akkaunt bo'shaganda mumkin.
            $table->foreignId('zoom_account_id')->constrained()->restrictOnDelete();
            $table->string('zoom_user_email');
            // Mas'ul administrator o'chirilsa, guruh o'chmaydi — faqat biriktirilmagan bo'lib qoladi.
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['zoom_account_id', 'zoom_user_email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};
