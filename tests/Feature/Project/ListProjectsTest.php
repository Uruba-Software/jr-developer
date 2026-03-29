<?php

namespace Tests\Feature\Project;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListProjectsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_sees_only_their_projects(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        Project::factory()->count(3)->create(['user_id' => $userA->id]);
        Project::factory()->count(2)->create(['user_id' => $userB->id]);

        $response = $this->actingAs($userA)->getJson('/api/projects');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    }

    public function test_inactive_projects_are_excluded(): void
    {
        $user = User::factory()->create();
        Project::factory()->count(2)->create(['user_id' => $user->id]);
        Project::factory()->inactive()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson('/api/projects');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    public function test_unauthenticated_user_cannot_list_projects(): void
    {
        $this->getJson('/api/projects')->assertUnauthorized();
    }
}
