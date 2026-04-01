<?php

namespace App\Console\Commands\Setup;

use App\Models\Project;
use App\Models\ProjectRule;
use Illuminate\Console\Command;

/**
 * T18 — php artisan jr:rules
 *
 * Opens project AI rules in $EDITOR for editing.
 * Rules are stored in the project_rules table (content field per row).
 * They are injected into the AI system prompt on every conversation turn.
 *
 * If $EDITOR is not set, falls back to nano, then vi.
 * Supports: list, add, edit, delete operations with --project option.
 */
class EditRulesCommand extends Command
{
    protected $signature = 'jr:rules
        {action? : Action to perform: list, add, edit, delete, export (default: list)}
        {--project= : Project ID or name (defaults to first active project)}';

    protected $description = 'Manage project AI rules that are injected into the system prompt';

    public function handle(): int
    {
        $project = $this->resolveProject();

        if (!$project) {
            $this->error('No project found. Run php artisan jr:setup first.');

            return self::FAILURE;
        }

        $action = $this->argument('action') ?? 'list';

        return match ($action) {
            'list'   => $this->listRules($project),
            'add'    => $this->addRule($project),
            'edit'   => $this->editRule($project),
            'delete' => $this->deleteRule($project),
            'export' => $this->exportRules($project),
            default  => $this->invalidAction($action),
        };
    }

    // -------------------------------------------------------------------------
    // Actions
    // -------------------------------------------------------------------------

    private function listRules(Project $project): int
    {
        $rules = $project->rules()->where('is_active', true)->orderBy('order')->get();

        $this->info("Project: <comment>{$project->name}</comment>");
        $this->newLine();

        if ($rules->isEmpty()) {
            $this->warn('No rules configured. Use: php artisan jr:rules add');

            return self::SUCCESS;
        }

        $this->info("Active rules ({$rules->count()}):");
        $this->newLine();

        foreach ($rules as $rule) {
            $this->line("<fg=yellow>[{$rule->id}]</> {$rule->title}");
            $this->line("    {$rule->content}");
            $this->newLine();
        }

        $this->comment('System prompt preview:');
        $this->line($this->buildSystemPromptPreview($rules->all()));

        return self::SUCCESS;
    }

    private function addRule(Project $project): int
    {
        $title   = $this->ask('Rule title (short description)');
        $content = $this->ask('Rule content (instruction for AI)');

        if (!$title || !$content) {
            $this->error('Title and content are required.');

            return self::FAILURE;
        }

        $maxOrder = $project->rules()->max('order') ?? -1;

        $rule = $project->rules()->create([
            'title'     => $title,
            'content'   => $content,
            'order'     => $maxOrder + 1,
            'is_active' => true,
        ]);

        $this->info("✓ Rule #{$rule->id} added: {$title}");

        return self::SUCCESS;
    }

    private function editRule(Project $project): int
    {
        $rules = $project->rules()->orderBy('order')->get();

        if ($rules->isEmpty()) {
            $this->warn('No rules to edit. Use: php artisan jr:rules add');

            return self::SUCCESS;
        }

        $choices = $rules->mapWithKeys(fn (ProjectRule $r) => [$r->id => "[{$r->id}] {$r->title}"])->toArray();
        $chosen  = $this->choice('Which rule to edit?', $choices);

        $ruleId = (int) str_replace('[', '', explode(']', $chosen)[0]);
        $rule   = $rules->find($ruleId);

        if (!$rule) {
            $this->error("Rule #{$ruleId} not found.");

            return self::FAILURE;
        }

        // Open in $EDITOR if available, otherwise ask inline
        $editor = getenv('EDITOR') ?: getenv('VISUAL') ?: null;

        if ($editor) {
            $tmpFile = tempnam(sys_get_temp_dir(), 'jrdev_rule_');
            file_put_contents($tmpFile, $rule->content);

            $this->line("Opening rule in {$editor}...");
            system("{$editor} {$tmpFile}");

            $newContent = file_get_contents($tmpFile);
            unlink($tmpFile);
        } else {
            $this->line("Current content: <comment>{$rule->content}</comment>");
            $newContent = $this->ask('New content');
        }

        if (empty($newContent) || $newContent === $rule->content) {
            $this->warn('No changes made.');

            return self::SUCCESS;
        }

        $rule->update(['content' => $newContent]);
        $this->info("✓ Rule #{$rule->id} updated.");

        return self::SUCCESS;
    }

    private function deleteRule(Project $project): int
    {
        $rules = $project->rules()->orderBy('order')->get();

        if ($rules->isEmpty()) {
            $this->warn('No rules to delete.');

            return self::SUCCESS;
        }

        $choices = $rules->mapWithKeys(fn (ProjectRule $r) => [$r->id => "[{$r->id}] {$r->title}"])->toArray();
        $chosen  = $this->choice('Which rule to delete?', $choices);

        $ruleId = (int) explode(']', str_replace('[', '', $chosen))[0];
        $rule   = $rules->find($ruleId);

        if (!$rule) {
            $this->error("Rule #{$ruleId} not found.");

            return self::FAILURE;
        }

        if (!$this->confirm("Delete rule: \"{$rule->title}\"?", false)) {
            $this->line('Aborted.');

            return self::SUCCESS;
        }

        $rule->delete();
        $this->info("✓ Rule #{$ruleId} deleted.");

        return self::SUCCESS;
    }

    private function exportRules(Project $project): int
    {
        $rules = $project->rules()->where('is_active', true)->orderBy('order')->get();

        if ($rules->isEmpty()) {
            $this->warn('No active rules to export.');

            return self::SUCCESS;
        }

        $this->line("# AI Rules — {$project->name}");
        $this->newLine();

        foreach ($rules as $rule) {
            $this->line("## {$rule->title}");
            $this->line($rule->content);
            $this->newLine();
        }

        return self::SUCCESS;
    }

    private function invalidAction(string $action): int
    {
        $this->error("Unknown action: {$action}");
        $this->line('Valid actions: list, add, edit, delete, export');

        return self::FAILURE;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function resolveProject(): ?Project
    {
        $identifier = $this->option('project');

        if ($identifier !== null) {
            return is_numeric($identifier)
                ? Project::find((int) $identifier)
                : Project::where('name', $identifier)->orWhere('slug', $identifier)->first();
        }

        return Project::where('is_active', true)->first();
    }

    /**
     * @param  ProjectRule[]  $rules
     */
    private function buildSystemPromptPreview(array $rules): string
    {
        if (empty($rules)) {
            return '(no rules — AI has no project-specific instructions)';
        }

        $lines = ["## Project Rules\n"];

        foreach ($rules as $rule) {
            $lines[] = "- {$rule->content}";
        }

        return implode("\n", $lines);
    }
}
