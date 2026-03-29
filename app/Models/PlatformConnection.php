<?php

namespace App\Models;

use App\Enums\MessagePlatform;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlatformConnection extends Model
{
    use HasFactory;
    protected $fillable = [
        'project_id',
        'platform',
        'channel_id',
        'channel_name',
        'credentials',
        'is_active',
    ];

    protected $casts = [
        'platform'    => MessagePlatform::class,
        'credentials' => 'encrypted:array',
        'is_active'   => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function scopeActive($query): void
    {
        $query->where('is_active', true);
    }
}
