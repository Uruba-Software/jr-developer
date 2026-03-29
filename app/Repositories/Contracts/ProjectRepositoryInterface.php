<?php

namespace App\Repositories\Contracts;

use App\Models\Project;
use Illuminate\Support\Collection;

interface ProjectRepositoryInterface
{
    public function findById(int $id): ?Project;

    public function findBySlug(string $slug, ?int $userId = null): ?Project;

    public function allForUser(int $userId): Collection;

    public function create(array $data): Project;

    public function update(Project $project, array $data): Project;

    public function delete(Project $project): void;
}
