<?php

use App\Enums\MessagePlatform;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('platform'); // MessagePlatform enum value
            $table->string('channel_id');
            $table->string('channel_name')->nullable();
            $table->json('credentials')->nullable(); // encrypted at model level
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['project_id', 'platform', 'channel_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_connections');
    }
};
