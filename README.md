# Junior Developer

> AI-powered messaging-driven development assistant. Self-hosted, open-source, human-in-the-loop.

[![PHP](https://img.shields.io/badge/PHP-8.4-blue)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-12-red)](https://laravel.com)
[![License](https://img.shields.io/badge/License-MIT-green)](LICENSE)

## What is Junior Developer?

Junior Developer (jr-developer) is a self-hosted AI development assistant that works through your existing messaging platforms (Slack, Discord, WhatsApp, Teams). It reads and edits code, shows diffs for approval, runs tests, manages Git workflows, and integrates with Jira — all from chat.

**Key principles:**
- **Human-in-the-loop** — every file change is shown as a diff and requires your approval before applying
- **Self-hosted** — your code and credentials never leave your infrastructure
- **Messaging-first** — works where your team already communicates
- **Open core** — core engine is MIT licensed; team and cloud features in [sr-developer](https://github.com/biyro02/sr-developer)

## Features

| Feature | jr-developer (free) | sr-developer (paid) |
|---|---|---|
| Slack + Discord | ✅ | ✅ |
| WhatsApp + Teams | ❌ | ✅ |
| Single project | ✅ | ✅ |
| Multi-project | ❌ | ✅ |
| Single user | ✅ | ✅ |
| Multi-user + permissions | ❌ | ✅ |
| Manual mode (no AI) | ✅ | ✅ |
| Agent mode (own API key) | ✅ | ✅ |
| Cloud mode (hosted AI) | ❌ | ✅ |
| Token & Cost Dashboard | ❌ | ✅ |
| Audit & Analytics | ❌ | ✅ |
| Jira integration | ✅ | ✅ |
| Test runner | ✅ | ✅ |
| Meeting notes | ✅ | ✅ |
| Deployment automation | ❌ | ✅ |

## Quick Start

```bash
# 1. Clone the repository
git clone https://github.com/biyro02/jr-developer.git
cd jr-developer

# 2. Start Docker environment
docker-compose up -d

# 3. Install dependencies
docker-compose exec app composer install

# 4. Run setup wizard
docker-compose exec app php artisan jr:setup
```

Setup wizard will guide you through:
- Choosing your messaging platform (Slack, Discord...)
- Connecting your AI provider (Anthropic, OpenAI, Gemini — or use Manual Mode for zero API cost)
- Connecting GitHub and optionally Jira
- Configuring project rules

## Architecture

```
Messaging Platform (Slack, Discord, WhatsApp...)
        ↓
jr-developer Laravel App
        ↓
AI Agent (Anthropic / OpenAI / Gemini via Prism PHP)
        ↓
Tools (File, Git, GitHub, Jira, Test Runner, Shell)
        ↓
Diff + Approval → back to Messaging Platform
```

**Stack:** Laravel 12, PHP 8.4, Prism PHP, Vue 3, Inertia.js, Redis, MySQL

**Operating Modes:**
- **Manual Mode** — No AI. You direct commands via chat. Zero API cost.
- **Agent Mode** — AI with your own API key. You approve every change.

## Supported Platforms

| Messaging | Status |
|---|---|
| Slack | ✅ Built-in |
| Discord | ✅ Built-in |
| WhatsApp | sr-developer |
| Microsoft Teams | sr-developer |
| *Add your own* | Implement `MessagingPlatform` interface |

## Adding a New Messaging Platform

```php
use JrDeveloper\Contracts\MessagingPlatform;
use JrDeveloper\DTOs\IncomingMessage;

class TelegramAdapter implements MessagingPlatform
{
    public function sendMessage(string $channel, string $text): void { ... }
    public function sendFile(string $channel, string $filename, string $content): void { ... }
    public function sendApprovalPrompt(string $channel, string $message, array $actions): string { ... }
    public function parseIncoming(array $payload): IncomingMessage { ... }
    public function verifyRequest(Request $request): bool { ... }
}
```

## Supported AI Providers

| Provider | Models |
|---|---|
| Anthropic | Claude Sonnet, Claude Haiku |
| OpenAI | GPT-4o, GPT-4o-mini |
| Google | Gemini Pro |
| *Add your own* | Implement Prism PHP provider |

## Token Optimization

jr-developer is designed to minimize API costs:
- **Model routing** — simple tasks use cheap models (Haiku), complex tasks use capable models (Sonnet)
- **Context pruning** — conversation history is summarized, not sent in full
- **Task routing** — `show last commit`, `list files` never trigger AI calls
- **Diff-only context** — AI sees changed lines, not full files
- **Redis caching** — Jira responses, file listings cached with TTL

## Configuration

All configuration is per-project, stored in the database. Example project rules:

```
Always show diff before editing any file.
Never force push.
Ask before running database migrations.
Use conventional commits: feat:, fix:, docs:, refactor:
Run tests after every code change.
```

## Requirements

- Docker + Docker Compose
- PHP 8.4+ (via Docker)
- MySQL 8.0+
- Redis 7+
- An API key for at least one AI provider (or use Manual Mode)

## Roadmap

- [x] MVP: Core messaging, file/git tools, approval flow
- [ ] v1.0: Jira integration, test runner, meeting notes, multi-project
- [ ] v2.0: Multi-user, WhatsApp/Teams, deployment automation
- [ ] v3.0: Cloud SaaS, billing, documentation site

## Contributing

Contributions welcome. Please read [CONTRIBUTING.md](CONTRIBUTING.md) before submitting a PR.

**Good first issues:**
- Add a new messaging platform adapter
- Add a new AI provider
- Add a test runner adapter for a new language

## License

[MIT](LICENSE) — core engine

sr-developer (team and cloud features) is a separate commercial package.

## About

Built by [Uruba Software](https://urubasoftware.com) — AI-powered tools for developer teams.

---

*Junior Developer is a tool, not a replacement. It amplifies your judgment, not bypasses it.*
