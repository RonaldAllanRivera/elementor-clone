# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project (for now) does not follow semantic versioning strictly. Early
entries are milestone-based until a public release is tagged.

## [Unreleased]

### Added

- Initial Laravel application scaffolded via `laravel/laravel`.
- Dockerized local development using Laravel Sail (PHP, MySQL, Node).
- Authentication flow set up with Laravel Breeze (login, profile, password reset).
- `Project` and `Design` models with migrations, controllers, and form request classes.
- Authenticated Projects and Designs CRUD views using Blade.
- Simple layout JSON → HTML rendering service and a sandboxed preview page.
- Elementor JSON export for designs (Classic + Container formats), including download and view/copy pages.
- Artisan command to export a design’s Elementor JSON to storage.
- Figma Frame URL support on designs (`figma_url`) and a one-click “Import from Figma” action.
- Figma API importer using `FIGMA_TOKEN` to fetch a Frame node and generate `layout_json` + HTML preview.
- First-pass import fidelity:
  - Auto Layout → containers/columns + spacing.
  - SOLID fills, borders, radius, basic drop shadow.
  - Typography (family/size/weight/line-height/letter-spacing/color/alignment).
  - Image placeholders (no downloads).
- Fixtures and unit/feature tests for Elementor export mapping and Figma import.
- Admin user seeder for a single-user setup.
- Automatic, unique project slug generation from project name (regenerated on save).
- PHPUnit test suite running successfully (`artisan test`).
- Project plan documented in `PLAN.md`.
- Project-specific `README.md` and this `CHANGELOG.md` following GitHub best practices.

### Changed

- Switched session driver to `file` in `.env` for simpler local development and to avoid early 419 (Page Expired) issues.
- Configured `APP_URL` to `http://elementor-clone.test` and documented local hosts entry for a friendly Sail domain.
- Disabled public registration routes (single-admin system).
- Figma API authentication uses `X-Figma-Token` (personal access token header).
- Elementor container export normalizes root wrapping for consistent imports.

### Planned

- Elementor JSON export endpoint for WordPress integration.
- WordPress import workflow (plugin or API-based).
- Figma integration to generate normalized layout JSON.
- GitHub Actions workflow to run tests on each push and pull request.
- AWS deployment configuration (Docker-based).
