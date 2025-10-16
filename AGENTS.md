# Repository Guidelines

## Project Structure & Module Organization
- `pages/` holds user and admin PHP entry points; `pages/api/` serves JSON endpoints consumed by the UI.
- `models/` contains PDO-backed domain classes (e.g. `Booking.php`) autoloaded via Composer.
- `includes/` centralizes bootstrap code (`config.php`) and shared helpers (`functions.php`).
- `assets/` stores static CSS/JS/images served by the web root; `database/` provides `create_database.sql`.
- `tests/` hosts PHPUnit suites with `tests/bootstrap.php`; `scripts/` bundles CLI utilities (`start.sh`, `cleanup_ledger.php`).

## Build, Test, and Development Commands
- `composer install` — install PHP dependencies and register autoloaders.
- `php -S localhost:8080` or `./scripts/start.sh` — run the development server (port configurable via `PORT`).
- `docker-compose up -d` — launch PHP+Apache and MySQL stack; follow with `docker-compose down -v` when finished.
- `php scripts/db_import.sh` — import `database/create_database.sql` using `DB_*` environment variables.
- `composer test` (alias for `vendor/bin/phpunit -c phpunit.xml.dist`) — execute automated tests.

## Coding Style & Naming Conventions
- Follow PSR-12 formatting with 4-space indentation; prefer short array syntax and nullable type hints where available.
- Name classes and files in PascalCase (`models/Booking.php`), functions/helpers in snake_case, and API endpoints in lower_snake (`pages/api/booking_create.php`).
- Keep controllers thin: move validation and pricing logic into models or helpers; reuse `json_response` and logging utilities.

## Testing Guidelines
- Extend `PHPUnit\\Framework\\TestCase`; place unit tests under `tests/Unit` mirroring source namespaces.
- Use `tests/bootstrap.php` to configure environment helpers (e.g., `__set_booking_holidays_env`) and avoid hitting the real database.
- Include regression scenarios for pricing rules, validation paths, and API envelopes; provide fixture data inline or via dedicated builders.

## Commit & Pull Request Guidelines
- Write commits in imperative voice with concise subjects (`Add Windows installation guide`) and explanatory bodies when behavior changes.
- Reference issues in commit bodies or PR descriptions; summarize scope, test evidence, and deployment considerations.
- For UI-facing changes, attach before/after screenshots or screencasts and update documentation or sample configs as needed.

## Environment & Security Notes
- Copy `config/database.example.php` to `config/database.php` for local overrides; never commit secrets or `.env` dumps.
- Restrict writable directories to `uploads/`, `logs/`, `cache/`, and `backups/`; ensure permissions are hardened in production.
- Leave CSRF protection and session hardening toggles enabled in `includes/config.php`; document any temporary overrides in the PR.
