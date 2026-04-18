<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Settings', [
            'appUrl'          => config('app.url'),
            'environment'     => config('app.env'),
            'queueConnection' => config('queue.default'),
            'connections'     => [
                'github' => filled(config('services.github.token')),
                'slack'  => filled(config('services.slack.token')),
                'jira'   => filled(config('services.jira.token')),
                'ai'     => filled(config('prism.providers.anthropic.api_key'))
                         || filled(config('prism.providers.openai.api_key')),
            ],
        ]);
    }
}
