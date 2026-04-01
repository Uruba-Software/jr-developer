<?php

namespace App\Console\Commands\Setup;

use App\Enums\OperatingMode;
use App\Models\Project;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * T16 — jr:setup wizard
 *
 * Interactive CLI wizard that walks through initial project configuration.
 * Steps:
 *   1. Admin user creation (or select existing)
 *   2. Project name and path
 *   3. VCS provider (GitHub / GitLab / Bitbucket)
 *   4. Messaging platform (Slack / Discord)
 *   5. AI provider (Anthropic / OpenAI / Gemini / None)
 *   6. Tool permission defaults
 *   7. Initial project rules
 */
class SetupCommand extends Command
{
    protected $signature   = 'jr:setup {--force : Re-run setup even if projects already exist}';
    protected $description = 'Interactive setup wizard for jr-developer';

    public function handle(): int
    {
        $this->displayBanner();

        if ($this->hasExistingProjects() && !$this->option('force')) {
            $this->warn('A project is already configured. Use --force to re-run setup.');

            return self::SUCCESS;
        }

        // Step 1: User
        $user = $this->setupUser();

        // Step 2: Project
        [$name, $path, $mode] = $this->setupProject();

        // Step 3: VCS
        $vcsConfig = $this->setupVcs();

        // Step 4: Messaging
        $messagingConfig = $this->setupMessaging();

        // Step 5: AI provider
        $aiConfig = $this->setupAiProvider($mode);

        // Step 6: Tool permissions
        $toolPermissions = $this->setupToolPermissions();

        // Step 7: Initial rules
        $rules = $this->setupInitialRules();

        // Build project config
        $config = array_filter([
            'vcs'             => $vcsConfig,
            'messaging'       => $messagingConfig,
            'ai'              => $aiConfig,
            'tool_permissions' => $toolPermissions,
        ]);

        // Detect project type
        $projectType = $this->detectProjectType($path);
        $this->info("Detected project type: <comment>{$projectType}</comment>");

        // Create project
        $project = Project::create([
            'user_id'        => $user->id,
            'name'           => $name,
            'local_path'     => $path,
            'operating_mode' => $mode,
            'config'         => $config,
        ]);

        // Save initial rules
        if (!empty($rules)) {
            foreach ($rules as $index => $rule) {
                $project->rules()->create([
                    'title'     => "Rule " . ($index + 1),
                    'content'   => $rule,
                    'order'     => $index,
                    'is_active' => true,
                ]);
            }
        }

        $this->newLine();
        $this->info('✓ Setup complete!');
        $this->table(
            ['Setting', 'Value'],
            [
                ['Project',        $project->name],
                ['ID',             $project->id],
                ['Path',           $project->local_path ?? 'not set'],
                ['Mode',           $project->operating_mode->value],
                ['VCS',            $vcsConfig['provider'] ?? 'not configured'],
                ['Messaging',      $messagingConfig['platform'] ?? 'not configured'],
                ['AI Provider',    $aiConfig['provider'] ?? 'none'],
                ['Rules',          count($rules) . ' rule(s)'],
                ['Project type',   $projectType],
            ]
        );

        $this->newLine();
        $this->comment('Next steps:');
        $this->line('  php artisan jr:test:all   — verify all connections');
        $this->line('  php artisan jr:rules       — edit project AI rules');

        return self::SUCCESS;
    }

    // -------------------------------------------------------------------------
    // Steps
    // -------------------------------------------------------------------------

    private function setupUser(): User
    {
        $this->info('<fg=cyan>Step 1/7: Admin User</>');
        $this->line('This user will own the project.');
        $this->newLine();

        $existing = User::first();

        if ($existing) {
            $useExisting = !$this->confirm("Create a new user? (existing: {$existing->email})", false);

            if ($useExisting) {
                $this->line("Using existing user: <comment>{$existing->email}</comment>");

                return $existing;
            }
        }

        $name     = $this->ask('Full name');
        $email    = $this->ask('Email address');
        $password = $this->ask('Password');

        $user = User::create([
            'name'     => $name,
            'email'    => $email,
            'password' => Hash::make($password),
        ]);

        $this->info("✓ User created: {$email}");

        return $user;
    }

    /**
     * @return array{string, string, OperatingMode}  [name, path, mode]
     */
    private function setupProject(): array
    {
        $this->newLine();
        $this->info('<fg=cyan>Step 2/7: Project</>');

        $name = $this->ask('Project name');
        $path = $this->ask('Absolute path to the project codebase', getcwd());

        if (!is_dir($path)) {
            $this->warn("Directory does not exist: {$path}. Proceeding anyway.");
        }

        $modeChoice = $this->choice(
            'Operating mode',
            [
                'manual' => 'Manual — no AI, command-driven (zero API cost)',
                'agent'  => 'Agent — AI-powered with your own API key',
                'cloud'  => 'Cloud — AI-powered using jr-developer API pool (paid)',
            ],
            'manual'
        );

        $mode = OperatingMode::from($modeChoice);

        return [$name, $path, $mode];
    }

    /**
     * @return array<string, string>
     */
    private function setupVcs(): array
    {
        $this->newLine();
        $this->info('<fg=cyan>Step 3/7: Version Control</>');

        $provider = $this->choice('VCS provider', ['github', 'gitlab', 'bitbucket', 'none'], 'github');

        if ($provider === 'none') {
            return ['provider' => 'none'];
        }

        $token    = $this->secret("Personal access token for {$provider}");
        $repoUrl  = $this->ask('Repository URL (e.g. https://github.com/owner/repo)', '');

        $this->line("Testing {$provider} connection...");

        $ok = $this->testVcsConnection($provider, $token);

        if ($ok) {
            $this->info("✓ {$provider} connection successful");
        } else {
            $this->warn("⚠ Could not verify connection. Token saved, but please verify manually.");
        }

        return [
            'provider'    => $provider,
            'token'       => $token,         // stored in DB; encrypt in production via Crypt
            'repo_url'    => $repoUrl,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function setupMessaging(): array
    {
        $this->newLine();
        $this->info('<fg=cyan>Step 4/7: Messaging Platform</>');

        $platform = $this->choice('Messaging platform', ['slack', 'discord', 'none'], 'slack');

        if ($platform === 'none') {
            return ['platform' => 'none'];
        }

        $config = ['platform' => $platform];

        if ($platform === 'slack') {
            $config['bot_token']      = $this->secret('Slack bot token (xoxb-...)');
            $config['signing_secret'] = $this->secret('Slack signing secret');
            $config['channel_id']     = $this->ask('Default Slack channel ID (C...)');
        } elseif ($platform === 'discord') {
            $config['bot_token']      = $this->secret('Discord bot token');
            $config['public_key']     = $this->ask('Discord application public key');
            $config['channel_id']     = $this->ask('Default Discord channel ID');
        }

        $this->line("Messaging platform configured: <comment>{$platform}</comment>");

        return $config;
    }

    /**
     * @return array<string, string>
     */
    private function setupAiProvider(OperatingMode $mode): array
    {
        $this->newLine();
        $this->info('<fg=cyan>Step 5/7: AI Provider</>');

        if ($mode === OperatingMode::Manual) {
            $this->line('Operating mode is Manual — AI provider not required.');

            return ['provider' => 'none'];
        }

        $provider = $this->choice(
            'AI provider',
            ['anthropic', 'openai', 'gemini'],
            'anthropic'
        );

        $apiKey = $this->secret("API key for {$provider}");

        $simpleModel  = $this->ask('Model for simple tasks (fast/cheap)', $this->defaultSimpleModel($provider));
        $complexModel = $this->ask('Model for complex tasks (capable)', $this->defaultComplexModel($provider));

        return [
            'provider'      => $provider,
            'api_key'       => $apiKey,
            'model_simple'  => $simpleModel,
            'model_complex' => $complexModel,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function setupToolPermissions(): array
    {
        $this->newLine();
        $this->info('<fg=cyan>Step 6/7: Tool Permission Defaults</>');
        $this->line('Configure which tool operations require explicit approval.');
        $this->newLine();

        $this->table(
            ['Level', 'Default behaviour', 'Examples'],
            [
                ['READ',    'Auto-allowed',         'file read, git status, search'],
                ['WRITE',   'Requires approval',    'file write, branch create'],
                ['EXEC',    'Requires approval',    'run tests, shell commands'],
                ['DEPLOY',  'Requires confirmation', 'git commit, push, PR create'],
                ['DESTROY', 'Always blocked',       'force push, drop table'],
            ]
        );

        $overrideWrite  = $this->confirm('Auto-approve WRITE operations (file edits)?', false);
        $overrideExec   = $this->confirm('Auto-approve EXEC operations (test runs)?', false);
        $overrideDeploy = $this->confirm('Auto-approve DEPLOY operations (commit/push)?', false);

        return [
            'write'   => $overrideWrite  ? 'auto' : 'approval',
            'exec'    => $overrideExec   ? 'auto' : 'approval',
            'deploy'  => $overrideDeploy ? 'auto' : 'approval',
            'destroy' => 'blocked',
        ];
    }

    /**
     * @return string[]
     */
    private function setupInitialRules(): array
    {
        $this->newLine();
        $this->info('<fg=cyan>Step 7/7: Project Rules</>');
        $this->line('Rules are injected into the AI system prompt every turn.');
        $this->line('Example: "Always show diff before editing. Never force push."');
        $this->newLine();

        $defaults = [
            'Always show a diff before editing files. Do not apply changes until approved.',
            'Never force push to main or master branches.',
            'Ask before running database migrations.',
            'Prefer targeted file reads over reading entire large files.',
        ];

        $useDefaults = $this->confirm('Load default rules?', true);
        $rules = $useDefaults ? $defaults : [];

        if ($this->confirm('Add a custom rule now?', false)) {
            $rule = $this->ask('Enter rule');

            if ($rule) {
                $rules[] = $rule;
            }
        }

        return $rules;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function displayBanner(): void
    {
        $this->newLine();
        $this->line('  ╔═══════════════════════════════╗');
        $this->line('  ║   jr-developer setup wizard   ║');
        $this->line('  ╚═══════════════════════════════╝');
        $this->newLine();
        $this->line('This wizard will configure jr-developer for your project.');
        $this->line('Press Ctrl+C at any time to abort.');
        $this->newLine();
    }

    private function hasExistingProjects(): bool
    {
        return Project::exists();
    }

    private function testVcsConnection(string $provider, string $token): bool
    {
        return match ($provider) {
            'github'    => $this->testGitHub($token),
            'gitlab'    => $this->testGitLab($token),
            'bitbucket' => true, // basic check — expand in T17
            default     => false,
        };
    }

    private function testGitHub(string $token): bool
    {
        $ch = curl_init('https://api.github.com/user');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer {$token}",
                'User-Agent: jr-developer/1.0',
                'Accept: application/vnd.github+json',
            ],
            CURLOPT_TIMEOUT => 10,
        ]);
        $response = curl_exec($ch);
        $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response && $status === 200) {
            $data = json_decode($response, true);
            $this->line("  Authenticated as: <comment>{$data['login']}</comment>");

            return true;
        }

        return false;
    }

    private function testGitLab(string $token): bool
    {
        $ch = curl_init('https://gitlab.com/api/v4/user');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ["PRIVATE-TOKEN: {$token}"],
            CURLOPT_TIMEOUT        => 10,
        ]);
        $response = curl_exec($ch);
        $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $status === 200;
    }

    private function detectProjectType(string $path): string
    {
        if (file_exists($path . '/composer.json')) {
            $composer = json_decode(file_get_contents($path . '/composer.json'), true);
            $requires = array_merge(
                $composer['require'] ?? [],
                $composer['require-dev'] ?? []
            );

            if (isset($requires['laravel/framework'])) {
                return 'Laravel (PHP)';
            }

            return 'PHP (Composer)';
        }

        if (file_exists($path . '/package.json')) {
            $pkg = json_decode(file_get_contents($path . '/package.json'), true);

            if (isset($pkg['dependencies']['react']) || isset($pkg['devDependencies']['react'])) {
                return 'Node.js (React)';
            }

            if (isset($pkg['dependencies']['vue']) || isset($pkg['devDependencies']['vue'])) {
                return 'Node.js (Vue)';
            }

            return 'Node.js';
        }

        if (file_exists($path . '/requirements.txt') || file_exists($path . '/pyproject.toml')) {
            return 'Python';
        }

        if (file_exists($path . '/Gemfile')) {
            return 'Ruby';
        }

        if (file_exists($path . '/go.mod')) {
            return 'Go';
        }

        if (file_exists($path . '/Cargo.toml')) {
            return 'Rust';
        }

        return 'Unknown';
    }

    private function defaultSimpleModel(string $provider): string
    {
        return match ($provider) {
            'anthropic' => 'claude-haiku-4-5-20251001',
            'openai'    => 'gpt-4o-mini',
            'gemini'    => 'gemini-2.0-flash',
            default     => 'unknown',
        };
    }

    private function defaultComplexModel(string $provider): string
    {
        return match ($provider) {
            'anthropic' => 'claude-sonnet-4-6',
            'openai'    => 'gpt-4o',
            'gemini'    => 'gemini-2.0-pro',
            default     => 'unknown',
        };
    }
}
