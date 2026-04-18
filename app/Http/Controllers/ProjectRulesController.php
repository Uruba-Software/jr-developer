<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\ProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectRulesController extends Controller
{
    public function __construct(
        private readonly ProjectService $service,
    ) {}

    public function update(Request $request, Project $project): JsonResponse
    {
        $request->validate([
            'rules' => ['required', 'string', 'max:10000'],
        ]);

        $this->service->updateRules($project, $request->input('rules'));

        return response()->json(['message' => 'Rules saved.']);
    }
}
