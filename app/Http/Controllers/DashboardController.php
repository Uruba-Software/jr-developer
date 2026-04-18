<?php

namespace App\Http\Controllers;

use App\Services\ProjectService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly ProjectService $projects,
    ) {}

    public function index(Request $request): Response
    {
        $projectCount = $this->projects->listForUser($request->user())->count();

        return Inertia::render('Dashboard', [
            'stats' => [
                'projects'       => $projectCount,
                'activeSessions' => 0,
                'tokensToday'    => 0,
            ],
        ]);
    }
}
