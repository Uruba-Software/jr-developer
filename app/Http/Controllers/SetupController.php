<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class SetupController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Setup');
    }
}
