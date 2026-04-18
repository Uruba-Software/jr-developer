<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ConnectionTestController extends Controller
{
    public function github(Request $request): JsonResponse
    {
        $request->validate([
            'provider' => ['required', 'string'],
            'token'    => ['required', 'string'],
        ]);

        $response = Http::withToken($request->input('token'))
            ->get('https://api.github.com/user');

        if ($response->failed()) {
            return response()->json(['message' => 'GitHub token invalid or insufficient scopes.'], 422);
        }

        $login = $response->json('login');

        return response()->json(['message' => "Connected as {$login}"]);
    }

    public function slack(Request $request): JsonResponse
    {
        $request->validate([
            'token'   => ['required', 'string'],
            'channel' => ['required', 'string'],
        ]);

        $response = Http::withToken($request->input('token'))
            ->get('https://slack.com/api/auth.test');

        if (! $response->json('ok')) {
            $error = $response->json('error', 'invalid_token');

            return response()->json(['message' => "Slack error: {$error}"], 422);
        }

        $team = $response->json('team');
        $user = $response->json('user');

        return response()->json(['message' => "Connected as {$user} on {$team}"]);
    }

    public function jira(Request $request): JsonResponse
    {
        $request->validate([
            'url'      => ['required', 'url'],
            'username' => ['required', 'email'],
            'token'    => ['required', 'string'],
        ]);

        $response = Http::withBasicAuth($request->input('username'), $request->input('token'))
            ->get(rtrim($request->input('url'), '/') . '/rest/api/3/myself');

        if ($response->failed()) {
            return response()->json(['message' => 'Jira credentials invalid. Check URL, email, and API token.'], 422);
        }

        $displayName = $response->json('displayName', 'unknown');

        return response()->json(['message' => "Connected as {$displayName}"]);
    }

    public function all(Request $request): JsonResponse
    {
        $results = [];

        if ($request->has('github_token')) {
            $r = $this->github(new Request(['provider' => 'github', 'token' => $request->input('github_token')]));
            $results['github'] = ['ok' => $r->getStatusCode() === 200, 'message' => $r->getData(true)['message']];
        }

        if ($request->has('slack_token')) {
            $r = $this->slack(new Request(['token' => $request->input('slack_token'), 'channel' => $request->input('slack_channel', '')]));
            $results['slack'] = ['ok' => $r->getStatusCode() === 200, 'message' => $r->getData(true)['message']];
        }

        return response()->json(['results' => $results]);
    }
}
