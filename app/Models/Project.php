<?php

namespace App\Models;

use App\Enums\OperatingMode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'description',
        'repository_url',
        'local_path',
        'default_branch',
        'operating_mode',
        'config',
        'is_active',
    ];

    protected $casts = [
        'operating_mode' => OperatingMode::class,
        'config'         => 'array',
        'is_active'      => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Project $project): void {
            if (empty($project->slug)) {
                $project->slug = Str::slug($project->name);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function platformConnections(): HasMany
    {
        return $this->hasMany(PlatformConnection::class);
    }

    public function rules(): HasMany
    {
        return $this->hasMany(ProjectRule::class)->orderBy('order');
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
