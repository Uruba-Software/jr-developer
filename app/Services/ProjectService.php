<?php

namespace App\Services;

use App\Enums\OperatingMode;
use App\Models\Project;
use App\Models\User;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use Illuminate\Support\Collection;

class ProjectService
{
    public function __construct(
        private readonly ProjectRepositoryInterface $projects,
    ) {}

    public function listForUser(User $user): Collection
    {
        return $this->projects->allForUser($user->id);
    }

    public function create(User $user, array $data): Project
    {
        return $this->projects->create([
            'user_id'        => $user->id,
            'name'           => $data['name'],
            'description'    => $data['description'] ?? null,
            'repository_url' => $data['repository_url'] ?? null,
            'local_path'     => $data['local_path'] ?? null,
            'default_branch' => $data['default_branch'] ?? 'main',
            'operating_mode' => $data['operating_mode'] ?? OperatingMode::Manual->value,
        ]);
    }
}
