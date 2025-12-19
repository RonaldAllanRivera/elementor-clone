# Figma → Elementor Clone

Convert Figma designs into clean HTML and Elementor‑compatible JSON that can be imported into WordPress.

This repository contains the **Laravel core** for the converter:

- Projects + designs management with authenticated CRUD.
- A normalized `layout_json` format stored per design.
- Server-side layout JSON → HTML generation with a sandboxed preview.
- A foundation for future Elementor JSON export and WordPress import workflows.

For a deeper technical roadmap, see [`PLAN.md`](./PLAN.md).

---

## Features (current phase)

- **Authentication** with Laravel Breeze (login, profile, password reset).
- **Dockerized local environment** using Laravel Sail (PHP, MySQL, Node).
- **Projects & Designs scaffolding**:
  - Eloquent models, migrations, controllers.
  - Form request classes for validation.
- **Projects & Designs UI**:
  - Authenticated CRUD screens for projects and designs.
  - Import from Figma via a Frame URL.
  - HTML preview (sandboxed iframe) generated from the internal `layout_json`.
- **Elementor JSON export**:
  - Download Elementor-compatible JSON in two formats:
    - Classic (section/column/widget)
    - Container-based (nested containers)
  - View/copy JSON inline.
- **Automatic project slugs** generated from the project name (unique, regenerated on save).
- **Single-user setup**: registration disabled, admin user seeded via `.env`.
- **Test suite** (Laravel default feature + auth tests) all passing.

Next milestones:

- Improve Figma → layout fidelity (width constraints, better widget inference).
- Optional image hosting (currently placeholders) for better Elementor imports.
- WordPress import workflow (plugin or API-based).

---

## Tech Stack

- **Backend**: Laravel (PHP 8+).
- **Frontend**: Blade + TailwindCSS (from Breeze starter).
- **Database**: MySQL (via Laravel Sail).
- **Runtime**: Docker / Docker Compose (Laravel Sail).
- **Tooling**:
  - PHP Unit tests (`artisan test`).
  - Node, Vite, Tailwind for assets.

---

## Architecture Overview

High‑level components (see `PLAN.md` for details):

- **Laravel app (this repo)**
  - Receives and stores layout JSON.
  - Generates HTML preview from layout JSON.
  - Will expose endpoints for Elementor JSON export.

- **Future components (separate repos)**
  - WordPress plugin to import Elementor JSON.
  - Figma plugin / integration to generate normalized layout JSON.
  - Optional Node/TypeScript conversion engine (Phase 2+).

---

## Local Development

### Prerequisites

- Docker and Docker Compose installed.
- Composer installed on your host (for initial setup, optional later).

### First‑time setup

From the project root:

```bash
cp .env.example .env
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
```

### Figma import setup

To enable “Import from Figma”, configure a Figma Personal Access Token:

- `FIGMA_TOKEN` – required

The importer expects a **Frame URL** (must include `node-id`). Example:

```
https://www.figma.com/design/<fileKey>/<name>?node-id=123-456
```

Notes:

- The API call uses the `X-Figma-Token` header.
- The Frame must be accessible to the token’s user.

If Breeze is not installed yet (only once):

```bash
./vendor/bin/sail composer require laravel/breeze --dev
./vendor/bin/sail artisan breeze:install blade
./vendor/bin/sail npm install
./vendor/bin/sail artisan migrate
```

Frontend assets are handled by a dedicated Docker service (`vite`) and start automatically with Sail.

Visit the app (default local domain):

- `http://elementor-clone.test/` – Laravel welcome page / home.
- `http://elementor-clone.test/login` – login.

If `elementor-clone.test` does not resolve, add this line to your `/etc/hosts` file:

```text
127.0.0.1   elementor-clone.test
```

### Common Sail commands

```bash
./vendor/bin/sail up -d      # start containers
./vendor/bin/sail down       # stop containers
./vendor/bin/sail artisan tinker
./vendor/bin/sail artisan migrate
```

If you ever need to rebuild production assets (not required for normal dev):

```bash
./vendor/bin/sail npm run build
```

---

## Running Tests

Run the full test suite inside Sail:

```bash
./vendor/bin/sail artisan test
```

Add new tests under the `tests/Feature` and `tests/Unit` directories as you build features.

### Admin user (seeded)

If you run database seeders (for example via `php artisan migrate:refresh --seed`), an admin user is created (idempotent) using `.env` values:

- Email: `ADMIN_EMAIL`
- Password: `ADMIN_PASSWORD`

Note: registration routes are disabled (single-admin system).

---

## Roadmap

The high‑level plan and future phases (Elementor export, WordPress plugin, Figma integration, AWS deployment, CI/CD) are documented in [`PLAN.md`](./PLAN.md).

Changelogs for each version / milestone are tracked in [`CHANGELOG.md`](./CHANGELOG.md).

---

## Contributing

This is currently a personal/project‑level repository. If you open it up to external contributions later, consider adding:

- A `CONTRIBUTING.md` with guidelines.
- An explicit `LICENSE` file.

For now, follow standard GitHub practices:

- Use feature branches (`feature/...`).
- Open pull requests against `develop` or `main`.
- Ensure tests pass before merging.

---

## License

The license for this project has not been explicitly defined yet.

