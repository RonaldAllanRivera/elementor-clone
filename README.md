# Figma → Elementor Clone

Convert Figma designs into clean HTML and Elementor‑compatible JSON that can be imported into WordPress.

This repository contains the **core Laravel application** that:

- Manages projects and designs.
- Stores normalized layout JSON.
- Generates HTML for preview.
- Will later export Elementor JSON for use in WordPress.

For a deeper technical roadmap, see [`PLAN.md`](./PLAN.md).

---

## Features (current phase)

- **Authentication** with Laravel Breeze (login, register, profile, password reset).
- **Dockerized local environment** using Laravel Sail (PHP, MySQL, Node).
- **Projects & Designs scaffolding**:
  - Eloquent models, migrations, controllers.
  - Form request classes for validation.
- **Basic Projects UI**:
  - Authenticated projects index and create screens.
- **Test suite** (Laravel default feature + auth tests) all passing.

Upcoming in this repository:

- Full CRUD UI for projects and designs.
- Uploading or pasting layout JSON for each design.
- Simple JSON → HTML mapping and preview page.
- Elementor JSON export endpoint.

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

If Breeze is not installed yet (only once):

```bash
./vendor/bin/sail composer require laravel/breeze --dev
./vendor/bin/sail artisan breeze:install blade
./vendor/bin/sail npm install
./vendor/bin/sail npm run dev
./vendor/bin/sail artisan migrate
```

Visit the app (default local domain):

- `http://elementor-clone.test/` – Laravel welcome page / home.
- `http://elementor-clone.test/register` – register a user.
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
./vendor/bin/sail npm run dev
```

---

## Running Tests

Run the full test suite inside Sail:

```bash
./vendor/bin/sail artisan test
```

Add new tests under the `tests/Feature` and `tests/Unit` directories as you build features.

### Seeded user (optional)

If you run database seeders (for example via `php artisan migrate:refresh --seed`), a default user is created:

- Email: `test@example.com`
- Password: `password`

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

