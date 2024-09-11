<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('desc')->nullable()->after('name');
        });
        Schema::table('groups', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()
                ->after('admin_id')->constrained('groups')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            //
        });
    }
};
