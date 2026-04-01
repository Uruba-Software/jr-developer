<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a JSON config column to projects for storing per-project settings:
     * tool_permissions, AI provider overrides, VCS credentials (encrypted reference),
     * and messaging credentials (encrypted reference).
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->json('config')->nullable()->after('operating_mode');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('config');
        });
    }
};
