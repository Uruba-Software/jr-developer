# Contributing to Junior Developer

Thank you for your interest in contributing!

## Ways to Contribute

- **New messaging platform adapter** — implement the `MessagingPlatform` interface
- **New AI provider** — add a Prism PHP provider wrapper
- **New test runner adapter** — implement the `TestRunner` interface for your language
- **Bug fixes** — check open issues
- **Documentation** — improve README, add examples

## Development Setup

```bash
git clone https://github.com/biyro02/jr-developer.git
cd jr-developer
cp .env.example .env
docker-compose up -d
docker-compose exec app composer install
docker-compose exec app php artisan key:generate
docker-compose exec app php artisan migrate
```

## Pull Request Guidelines

1. Fork the repository
2. Create a branch: `git checkout -b feat/your-feature`
3. Write tests for your changes
4. Ensure all tests pass: `php artisan test`
5. Submit a PR with a clear description

## Code Standards

- PSR-12 coding style
- Laravel conventions
- No business logic in controllers — use Services
- Database queries in Repositories
- Every new Tool must implement the `Tool` contract

## Adding a Messaging Platform Adapter

1. Create `src/Adapters/YourPlatformAdapter.php`
2. Implement `JrDeveloper\Contracts\MessagingPlatform`
3. Register in `config/jr-developer.php`
4. Write a feature test in `tests/Feature/Adapters/`
5. Add documentation in `docs/adapters/`

## License

By contributing, you agree that your contributions will be licensed under the MIT License.
