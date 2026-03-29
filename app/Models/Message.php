<?php

namespace App\Models;

use App\Enums\MessageRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'platform_message_id',
        'tool_calls',
        'tool_results',
        'input_tokens',
        'output_tokens',
    ];

    protected $casts = [
        'role'         => MessageRole::class,
        'tool_calls'   => 'array',
        'tool_results' => 'array',
        'input_tokens' => 'integer',
        'output_tokens'=> 'integer',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function totalTokens(): int
    {
        return $this->input_tokens + $this->output_tokens;
    }
}
