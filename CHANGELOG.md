# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project (for now) does not follow semantic versioning strictly. Early
entries are milestone-based until a public release is tagged.

## [Unreleased]

### Added

- Initial Laravel application scaffolded via `laravel/laravel`.
- Dockerized local development using Laravel Sail (PHP, MySQL, Node).
- Authentication flow set up with Laravel Breeze (registration, login, profile, password reset).
- `Project` and `Design` models with migrations, controllers, and form request classes.
- Basic authenticated Projects index and create views using Blade.
- PHPUnit test suite running successfully (`artisan test`).
- Project plan documented in `PLAN.md`.
- Project-specific `README.md` and this `CHANGELOG.md` following GitHub best practices.

### Changed

- Switched session driver to `file` in `.env` for simpler local development and to avoid early 419 (Page Expired) issues.
- Configured `APP_URL` to `http://elementor-clone.test` and documented local hosts entry for a friendly Sail domain.

### Planned

- CRUD UI for projects and designs (routes, controllers, Blade views).
- Upload/paste layout JSON for each design and persist to database.
- Basic JSON → HTML mapping and preview page for designs.
- Elementor JSON export endpoint for WordPress integration.
- GitHub Actions workflow to run tests on each push and pull request.
- AWS deployment configuration (Docker-based).
