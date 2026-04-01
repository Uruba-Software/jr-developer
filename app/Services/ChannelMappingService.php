<?php

namespace App\Services;

use App\Enums\MessagePlatform;
use App\Exceptions\ChannelAlreadyMappedException;
use App\Models\PlatformConnection;
use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;

/**
 * T27 — ChannelMappingService
 *
 * Manages the mapping between messaging channels and projects.
 * Each (platform, channel_id) pair can only belong to one project at a time.
 */
class ChannelMappingService
{
    /**
     * Resolve the active project for a given platform + channel.
     * Returns null if no mapping exists.
     */
    public function resolve(string $platform, string $channelId): ?Project
    {
        $connection = PlatformConnection::query()
            ->where('platform', $platform)
            ->where('channel_id', $channelId)
            ->where('is_active', true)
            ->with('project')
            ->first();

        return $connection?->project;
    }

    /**
     * Map a channel to a project.
     *
     * @throws ChannelAlreadyMappedException if channel is already mapped to a different project
     */
    public function map(
        Project         $project,
        string          $platform,
        string          $channelId,
        string          $channelName = '',
    ): PlatformConnection {
        // Check for conflict
        $existing = PlatformConnection::query()
            ->where('platform', $platform)
            ->where('channel_id', $channelId)
            ->where('is_active', true)
            ->first();

        if ($existing !== null && $existing->project_id !== $project->id) {
            throw new ChannelAlreadyMappedException(
                platform:    $platform,
                channelId:   $channelId,
                projectName: $existing->project->name ?? "(#{$existing->project_id})",
            );
        }

        return PlatformConnection::updateOrCreate(
            [
                'project_id' => $project->id,
                'platform'   => $platform,
                'channel_id' => $channelId,
            ],
            [
                'channel_name' => $channelName,
                'is_active'    => true,
            ],
        );
    }

    /**
     * Remove a channel mapping for a project.
     * Soft-deactivates rather than deleting to preserve history.
     */
    public function unmap(Project $project, string $platform, string $channelId): bool
    {
        $updated = PlatformConnection::query()
            ->where('project_id', $project->id)
            ->where('platform', $platform)
            ->where('channel_id', $channelId)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        return $updated > 0;
    }

    /**
     * Check if a channel is already mapped to a different project.
     */
    public function hasMappingConflict(
        string $platform,
        string $channelId,
        ?int   $excludeProjectId = null,
    ): bool {
        return PlatformConnection::query()
            ->where('platform', $platform)
            ->where('channel_id', $channelId)
            ->where('is_active', true)
            ->when($excludeProjectId !== null, fn ($q) => $q->where('project_id', '!=', $excludeProjectId))
            ->exists();
    }

    /**
     * Get all active channel mappings for a project.
     *
     * @return Collection<PlatformConnection>
     */
    public function getMappings(Project $project): Collection
    {
        return PlatformConnection::query()
            ->where('project_id', $project->id)
            ->where('is_active', true)
            ->get();
    }

    /**
     * Get all channel mappings across all projects.
     *
     * @return Collection<PlatformConnection>
     */
    public function getAllMappings(): Collection
    {
        return PlatformConnection::query()
            ->where('is_active', true)
            ->with('project')
            ->get();
    }
}
