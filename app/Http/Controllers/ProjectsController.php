<?php

namespace App\Http\Controllers;

use App\Services\ProjectService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectsController extends Controller
{
    public function __construct(
        private readonly ProjectService $service,
    ) {}

    public function index(Request $request): Response
    {
        $projects = $this->service->listForUser($request->user());

        return Inertia::render('Projects/Index', [
            'projects' => $projects->values(),
        ]);
    }
}
