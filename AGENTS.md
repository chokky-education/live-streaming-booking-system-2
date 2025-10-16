# Repository Guidelines

## Project Structure & Module Organization
`pages/` hosts user and admin entry points, while `pages/api/` serves JSON endpoints for the UI. Domain rules stay in Composer-autoloaded PDO models under `models/`. Shared bootstrap helpers live in `includes/` (`config.php`, `functions.php`). Static assets belong in `assets/`; database schema and seeds in `database/`; PHPUnit suites in `tests/`; CLI tools in `scripts/`. Keep logs, caches, uploads, and backups within their dedicated top-level directories.

## Build, Test, and Development Commands
- `composer install` installs dependencies and refreshes autoloaders.
- `php -S localhost:8080` or `./scripts/start.sh` serves the app; set `PORT=9090` (or similar) to change the port.
- `docker-compose up -d` provisions Apache/PHP and MySQL containers; run `docker-compose down -v` when finished.
- `php scripts/db_import.sh` loads `database/create_database.sql` using the configured `DB_*` environment variables.
- `composer test` (alias `vendor/bin/phpunit -c phpunit.xml.dist`) runs the automated suite; keep it green before commits.

## Coding Style & Naming Conventions
Follow PSR-12 with 4-space indentation, strict typing declarations, and short array syntax. Use PascalCase for classes and filenames (`models/Booking.php`), snake_case for helpers, and lower_snake for API files (`pages/api/booking_create.php`). Keep controllers slim by delegating validation, pricing, and side effects to models or shared helpers, and reuse `json_response()` plus the logging utilities for consistent envelopes.

## Testing Guidelines
Tests extend `PHPUnit\\Framework\\TestCase` and live under `tests/Unit`, mirroring source namespaces. Bootstrap fixtures through `tests/bootstrap.php` to toggle helpers like `__set_booking_holidays_env()` and avoid real database calls. Target regression coverage for pricing rules, validation flows, and API response contracts, and only push after `composer test` passes locally.

## Commit & Pull Request Guidelines
Write commits in imperative mood (e.g., `Add Windows installation guide`) and keep scope focused. Reference related issues in commit bodies or PR descriptions, state behavioural changes, and document how you validated the work. UI updates should include before/after screenshots or screencasts; note any deployment or data-migration steps that reviewers must follow.

## Security & Configuration Tips
Copy `config/database.example.php` to `config/database.php` for local overrides and never commit secrets or `.env` dumps. Restrict writable permissions to `uploads/`, `logs/`, `cache/`, and `backups/`. Leave CSRF and session-hardening toggles enabled in `includes/config.php`, document any temporary overrides, and revert them quickly.
