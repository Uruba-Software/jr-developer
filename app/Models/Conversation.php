<?php

namespace App\Models;

use App\Enums\OperatingMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $fillable = [
        'project_id',
        'platform_connection_id',
        'thread_id',
        'title',
        'operating_mode',
        'token_count',
        'is_active',
    ];

    protected $casts = [
        'operating_mode' => OperatingMode::class,
        'token_count'    => 'integer',
        'is_active'      => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function platformConnection(): BelongsTo
    {
        return $this->belongsTo(PlatformConnection::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }

    public function scopeActive($query): void
    {
        $query->where('is_active', true);
    }
}
