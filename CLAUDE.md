# Junior Developer (jr-dev) — Claude Code Guide

## Auto-load Memory
Read these files at the start of every session:
- `/home/bayramu/.claude/projects/-var-www-eKareAPI/memory/jr-dev-project.md` — full project brief
- `/home/bayramu/.claude/projects/-var-www-eKareAPI/memory/jr-dev-redmine-tasks.md` — all tasks

---

## What Is This Project?

A self-hosted, messaging-driven AI development assistant.
Developers send messages via Slack/Discord/etc., the AI reads/edits code,
shows diffs for approval, runs tests, handles Git/Redmine workflows — all from chat.

- **Repo**: `https://github.com/Uruba-Software/jr-developer`
- **Server**: Hetzner CX33, `89.167.119.59`, `/opt/uruba/jr-developer`
- **SSH Key**: `~/.ssh/uruba_server`
- **Stack**: Laravel 12, PHP 8.4, Prism PHP, Vue 3, Inertia.js, Redis, MySQL
- **License**: MIT (jr-developer core) + Proprietary (sr-developer)

---

## Architecture

```
Controller → FormRequest → Service → Repository → Model
                                          ↓
                                    Event/Observer
                                          ↓
                                    Queue Job (async)
```

### Layers

| Layer | Responsibility |
|-------|---------------|
| **Controller** | Thin. Accept request, return response. No logic. |
| **FormRequest** | Validation and authorization. Always use for input. |
| **Service** | Business logic. One service per domain. |
| **Repository** | Database queries only. No business logic. |
| **Model** | Eloquent definitions, relationships, casts, scopes. |
| **Event/Listener** | Side effects (notifications, logs, hooks). |
| **Job** | Async operations (AI calls, message dispatch, heavy ops). |
| **Resource** | API response transformation. Always use JsonResource. |

### Directory Structure

```
app/
├── Http/
│   ├── Controllers/        # Thin controllers
│   ├── Requests/           # FormRequest per action
│   └── Resources/          # JsonResource per model/response
├── Services/               # Business logic (one dir per domain)
├── Repositories/
│   ├── Contracts/          # Repository interfaces
│   └── Eloquent/           # Eloquent implementations
├── Models/                 # Eloquent models
├── Events/                 # Domain events
├── Listeners/              # Event listeners
├── Jobs/                   # Queue jobs
├── Enums/                  # PHP 8.1+ enums for constants
├── DTOs/                   # Data Transfer Objects (readonly classes)
├── Exceptions/             # Custom exceptions + Handler
├── Adapters/               # MessagingPlatform implementations
├── Contracts/              # Core interfaces (MessagingPlatform, AIProvider, etc.)
└── Providers/              # Service providers, bindings
```

---

## Core Principles

### SOLID
- **S** — Single Responsibility: one class, one reason to change
- **O** — Open/Closed: extend via interface/inheritance, don't modify existing
- **L** — Liskov: adapters and implementations are interchangeable
- **I** — Interface Segregation: small focused interfaces (MessagingPlatform, AIProvider, ToolRunner)
- **D** — Dependency Inversion: inject interfaces, never concrete classes in constructors

### DRY / KISS / YAGNI
- No duplication — extract shared logic to services or traits
- Simplest solution that works — no premature abstraction
- Don't build what isn't needed yet — no hypothetical features
- Three similar lines of code is fine — abstract only when there are 3+ actual uses

### Laravel-Native First
Always prefer built-in Laravel features:

| Need | Use |
|------|-----|
| Input validation | `FormRequest` |
| API responses | `JsonResource` / `ResourceCollection` |
| Authorization | `Policy` |
| Side effects | `Event` + `Listener` |
| Async work | `Queue Job` |
| DB triggers | `Observer` |
| Scheduled tasks | `Console/Kernel` schedule |
| Config values | `config()` + `.env` |
| Type-safe constants | PHP 8.1 `enum` |
| Data passing | `readonly` DTO class |
| Caching | `Cache` facade with tags |
| External HTTP | `Http` facade (not raw curl/Guzzle) |

### Abstract Layers (Non-negotiable)
- All repositories implement an interface from `app/Repositories/Contracts/`
- All adapters implement a contract from `app/Contracts/`
- Bind interfaces to implementations in `AppServiceProvider`
- Services depend on repository interfaces, never concrete classes

```php
// Always inject interface:
public function __construct(
    private readonly ProjectRepositoryInterface $projects,
    private readonly MessagingPlatform $messaging,
) {}
```

---

## Code Standards

### Naming
- Controllers: `ProjectController` (singular noun + Controller)
- Services: `ProjectService`, `ConversationService`
- Repositories: `EloquentProjectRepository` implements `ProjectRepositoryInterface`
- Jobs: `ProcessIncomingMessage`, `SendDiffForApproval` (imperative verb phrase)
- Events: `MessageReceived`, `DiffApproved` (past tense)
- Listeners: `HandleMessageReceived`, `ApplyApprovedDiff`
- DTOs: `CreateProjectData`, `SendMessageData` (noun + Data)
- Enums: `ToolPermission`, `OperatingMode`, `MessagePlatform`
- Interfaces: `ProjectRepositoryInterface`, `MessagingPlatform`, `AIProvider`

### PHP Style
- PHP 8.4 — use all modern features: readonly properties, named args, match expressions, nullsafe operator
- Type everything: parameters, return types, properties — no `mixed` unless truly unavoidable
- Enums over string/int constants
- DTOs (readonly classes) for structured data between layers
- No static methods except helpers/utilities
- No facades in Services/Repositories — inject via constructor
- No `array` type hints for structured data — use DTOs

### Error Handling
- Custom exceptions per domain: `ProjectNotFoundException`, `AIProviderException`
- Register in `Handler::register()` with appropriate HTTP response
- Never catch and swallow exceptions silently
- Use `report()` for logging unexpected errors

---

## Testing

### Philosophy
Tests are **workflow/flow tests** — they test a user-facing scenario end-to-end,
not individual methods. Every possible outcome must be covered.

### Every feature must have:
- Happy path (success)
- Invalid / missing input (422)
- Unauthorized (401/403)
- Not found (404)
- Conflict / already exists (409)
- Edge cases (empty, null, boundary values)

### Test Structure
```php
class CreateProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_project(): void { ... }
    public function test_create_project_requires_name(): void { ... }
    public function test_create_project_requires_unique_name(): void { ... }
    public function test_unauthenticated_user_cannot_create_project(): void { ... }
    public function test_unauthorized_user_cannot_create_project(): void { ... }
}
```

### Rules
- Use `RefreshDatabase` on all feature tests
- Use `actingAs($user)` for auth
- Use `assertDatabaseHas` / `assertDatabaseMissing` to verify state
- Mock external services (AI, Slack API) with `Http::fake()` or `$this->mock()`
- No testing implementation details — test behavior and outcomes
- Use factories for test data: `Project::factory()->create()`

### Auto-approval Rule
**If all tests pass → task is done. No additional approval needed.**
Commit, push, open PR, update Redmine to Testing — proceed automatically.

---

## Development Workflow

### Task Flow
1. Read Redmine task (from memory file or via API)
2. Identify ambiguities → ask user if unclear
3. Present plan (files to create/modify, approach)
4. **Wait for user approval on the plan** (one-time checkpoint per task)
5. Create branch: `git checkout dev && git pull && git checkout -b TASK-ID`
6. Write code following all standards above
7. Write tests → run tests
8. If tests pass → commit + push + open PR + update Redmine to Testing (no further approval needed)
9. If tests fail → fix code → re-run → repeat until green

### Branch Strategy
| Situation | Base Branch |
|-----------|-------------|
| New feature / standalone | `dev` |
| Continuation of related task | Related task's branch |
| Branch name | Always the task ID (e.g. `IS-T01`) |
| PR target | `dev` |

### Commit Message Format
```
type(scope): short description

- bullet point details

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
```
Types: `feat`, `fix`, `refactor`, `test`, `ci`, `chore`, `docs`

### Git Commands
```bash
git checkout dev && git pull origin dev
git checkout -b TASK-ID
# ... code ...
git add app/ tests/ database/ routes/   # specific paths, never git add .
git commit -m "..."
git push -u origin TASK-ID
gh pr create --base dev --title "TASK-ID: description" --body "..."
```

### PR Template
```bash
gh pr create --base dev --title "IS-T01: Short description" --body "$(cat <<'EOF'
## Task
[IS-T01 - Task title](https://redmine.urubasoftware.com/issues/TASK_ID)

## Description
- Change 1
- Change 2

## Type
- [x] New feature
- [ ] Bug fix
- [ ] Refactor

## Testing
- [x] Tests written and passing
- [x] Self-review complete
- [x] No unnecessary changes
EOF
)"
```

---

## Redmine Integration

- **URL**: `https://redmine.urubasoftware.com`
- **API Key**: `c1b03257e60302d435076e0e7808ec3f519c57a1`
- **Project identifier**: `jr-developer`

```bash
# Get issue
curl -s -H "X-Redmine-API-Key: c1b03257e60302d435076e0e7808ec3f519c57a1" \
  "https://redmine.urubasoftware.com/issues/ISSUE_ID.json"

# Update status (2=In Progress, 3=Resolved, 4=Feedback, 5=Closed, 6=Testing)
curl -s -X PUT \
  -H "X-Redmine-API-Key: c1b03257e60302d435076e0e7808ec3f519c57a1" \
  -H "Content-Type: application/json" \
  -d '{"issue": {"status_id": 2}}' \
  "https://redmine.urubasoftware.com/issues/ISSUE_ID.json"

# Add comment
curl -s -X PUT \
  -H "X-Redmine-API-Key: c1b03257e60302d435076e0e7808ec3f519c57a1" \
  -H "Content-Type: application/json" \
  -d '{"issue": {"notes": "Comment text"}}' \
  "https://redmine.urubasoftware.com/issues/ISSUE_ID.json"
```

---

## Infrastructure

| What | Where |
|------|-------|
| Server | Hetzner CX33, `89.167.119.59` |
| SSH | `ssh -i ~/.ssh/uruba_server root@89.167.119.59` |
| App path | `/opt/uruba/jr-developer` |
| Redmine | `https://redmine.urubasoftware.com` |
| GitHub | `https://github.com/Uruba-Software/jr-developer` |
| GitHub Token | `$GH_TOKEN (stored in env / keyring — never commit)` (biyro02) |

### Local Development (Docker)
```bash
docker compose up -d         # start services
docker compose exec app bash  # enter container
php artisan test              # run tests
php artisan migrate           # run migrations
```

### CI/CD
- **CI** (`ci.yml`): runs on push to `main` and `dev` — composer install, migrate, test
- **CD** (`deploy.yml`): runs on push to `main` — SSH to server, git pull, migrate, cache, restart

---

## Domain Concepts

### Operating Modes
```php
enum OperatingMode: string {
    case Manual = 'manual';    // No AI, zero API cost
    case Agent  = 'agent';     // User's own API key
    case Cloud  = 'cloud';     // Paid, our API pool
}
```

### Tool Permission Levels
```php
enum ToolPermission: string {
    case Read    = 'read';     // auto-allowed
    case Write   = 'write';    // requires approval
    case Exec    = 'exec';     // requires approval
    case Deploy  = 'deploy';   // explicit confirmation
    case Destroy = 'destroy';  // always blocked
}
```

### Core Contracts
```php
interface MessagingPlatform {
    public function sendMessage(string $channel, string $text): void;
    public function sendFile(string $channel, string $path, string $name): void;
    public function sendApprovalPrompt(string $channel, string $message, array $actions): void;
    public function parseIncoming(array $payload): IncomingMessage;
    public function verifyRequest(Request $request): bool;
}

interface AIProvider {
    public function complete(array $messages, array $tools = []): AIResponse;
    public function stream(array $messages, callable $onChunk): void;
}

interface ToolRunner {
    public function supports(string $tool): bool;
    public function run(string $tool, array $params): ToolResult;
    public function permission(): ToolPermission;
}
```

---

## Key Packages

| Package | Purpose |
|---------|---------|
| `prism-php/prism` | Multi-provider AI (Anthropic, OpenAI, Gemini) |
| `laravel/sanctum` | API token auth |
| `predis/predis` | Redis client |
| `spatie/laravel-permission` | Roles and permissions |
| `spatie/laravel-data` | DTOs with validation |
| `laravel/horizon` | Queue monitoring |

---

## Quick Reference — Key Paths

| What | Where |
|------|-------|
| Add route | `routes/api.php` or `routes/web.php` |
| Controller | `app/Http/Controllers/` |
| FormRequest | `app/Http/Requests/` |
| Resource | `app/Http/Resources/` |
| Service | `app/Services/` |
| Repository interface | `app/Repositories/Contracts/` |
| Repository impl | `app/Repositories/Eloquent/` |
| Model | `app/Models/` |
| Migration | `database/migrations/` |
| Factory | `database/factories/` |
| Event | `app/Events/` |
| Listener | `app/Listeners/` |
| Job | `app/Jobs/` |
| Enum | `app/Enums/` |
| DTO | `app/DTOs/` |
| Exception | `app/Exceptions/` |
| Contract/Interface | `app/Contracts/` |
| Adapter | `app/Adapters/` |
| Artisan command | `app/Console/Commands/` |
| Tests | `tests/Feature/` |
