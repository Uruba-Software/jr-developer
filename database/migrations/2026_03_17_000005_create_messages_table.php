<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('role'); // user | assistant | tool
            $table->text('content');
            $table->string('platform_message_id')->nullable(); // Slack ts, Discord message ID, etc.
            $table->json('tool_calls')->nullable(); // AI tool call requests
            $table->json('tool_results')->nullable(); // Tool execution results
            $table->integer('input_tokens')->default(0);
            $table->integer('output_tokens')->default(0);
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
