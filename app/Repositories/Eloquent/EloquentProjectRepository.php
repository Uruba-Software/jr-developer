<?php

namespace App\Repositories\Eloquent;

use App\Models\Project;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use Illuminate\Support\Collection;

class EloquentProjectRepository implements ProjectRepositoryInterface
{
    public function findById(int $id): ?Project
    {
        return Project::find($id);
    }

    public function findBySlug(string $slug): ?Project
    {
        return Project::where('slug', $slug)->first();
    }

    public function allForUser(int $userId): Collection
    {
        return Project::where('user_id', $userId)
            ->active()
            ->orderBy('name')
            ->get();
    }

    public function create(array $data): Project
    {
        return Project::create($data);
    }

    public function update(Project $project, array $data): Project
    {
        $project->update($data);

        return $project->fresh();
    }

    public function delete(Project $project): void
    {
        $project->delete();
    }
}
