# Figma → Elementor Clone: Project Plan

## 1. Goals
- **Primary**: Convert Figma designs into HTML, then into Elementor‑compatible JSON that can be imported into WordPress.
- **Secondary**: Provide a UI to manage designs, preview output, and push templates to WordPress sites.
- **Infra**: Dockerized for local and production (AWS), managed in GitHub.

---

## 2. High‑Level Architecture

- **Figma Plugin (later phase)**
  - TypeScript plugin to export structured layout JSON from Figma frames.

- **Backend Core (Laravel app: this repo)**
  - Receives normalized layout JSON.
  - Stores projects/designs/templates in DB.
  - Converts layout JSON →
    - HTML/CSS (for preview & export).
    - Elementor JSON (for WordPress import).
  - Exposes REST API for Figma plugin and WP plugin.

- **Conversion Engine (Phase 2+)**
  - Option A (initial): Implement mapping directly in Laravel (PHP).
  - Option B (later): Extract conversion logic into Node.js/TypeScript microservice.

- **WordPress Plugin**
  - Minimal PHP plugin installed on WP site.
  - Accepts Elementor JSON via REST or file upload.
  - Writes `_elementor_data` to posts/pages/templates.

- **Frontend UI**
  - Laravel Blade or Inertia.js (Vue/React) for:
    - Project list.
    - Design detail + HTML preview.
    - Download Elementor JSON.

---

## 3. Tech Stack

- **Core**: Laravel (PHP 8+), MySQL/Postgres.
- **Language Skills Used**:
  - PHP (Laravel) – main backend.
  - JavaScript – frontend and future Figma plugin.
  - Python – optional later (heuristics/ML if needed).
- **Frontend**: Blade + TailwindCSS (simple) or Inertia + Vue/React (richer).
- **Infra**:
  - Docker + docker-compose for local dev.
  - AWS (Fargate or ECS on EC2, or Lightsail/EC2 with Docker).
  - GitHub for source control, GitHub Actions for CI/CD.

---

## 4. Phases & Milestones

### Phase 1 – Core Laravel App (Local, Dockerized)

- **Features**
  - [x] Basic auth (Laravel Breeze/Fortify).
  - [ ] CRUD for `Projects` and `Designs` (routes, controllers, Blade views fully wired).
  - [ ] Upload/import a JSON layout file (manual mock of Figma output).
  - [ ] Convert stored layout JSON → HTML (simple mapping, no Elementor yet).
  - [ ] HTML preview page.

- **Deliverables**
  - [x] Laravel app running via Docker locally.
  - [x] `docker-compose.yml`/Sail configuration with:
    - `app` (PHP-FPM + web server).
    - `db` (MySQL/Postgres).
  - [x] Initial DB migrations for `users`, cache, jobs, `projects`, and `designs`.

### Phase 2 – Elementor JSON Export

- **Features**
  - Define internal `layout schema` (sections, columns, widgets).
  - Implement PHP services to map layout schema → Elementor JSON structure.
  - UI button: "Export Elementor JSON" for a design.
  - Downloadable `.json` file compatible with Elementor import.

- **Deliverables**
  - Unit tests for mapping functions.
  - Example fixture JSONs and expected Elementor outputs.

### Phase 3 – WordPress Plugin

- **Features**
  - Custom plugin providing:
    - Admin page: upload Elementor JSON (from your Laravel app).
    - Optional REST endpoint to accept Elementor JSON from Laravel.
  - Save JSON to `_elementor_data`, mark `_elementor_edit_mode` and related meta.

- **Deliverables**
  - WP plugin repo (separate GitHub repo or subfolder).
  - Tested against a local Dockerized WordPress instance.

### Phase 4 – Figma Integration (Basic)

- **Features**
  - (Manual first) Use Figma REST API or export JSON and create a mock converter from Figma file JSON → layout schema.
  - (Later) Figma plugin in TypeScript to send structured JSON directly to Laravel API.

- **Deliverables**
  - Mapping rules (doc) from Figma structures to your layout schema (naming conventions, auto‑layout usage).

### Phase 5 – AWS Deployment & CI/CD

- **Features**
  - Dockerized production image for Laravel app.
  - AWS infrastructure (start simple):
    - Option A: EC2 instance with Docker + docker-compose.
    - Option B: ECS/Fargate with a single service.
  - GitHub Actions:
    - Run tests on push/PR.
    - Build Docker image and push to ECR or Docker Hub.
    - Deploy via SSH or ECS deploy step.

- **Deliverables**
  - `Dockerfile` optimized for production.
  - GitHub Actions workflow YAML.
  - Basic monitoring/logging strategy.

---

## 5. Docker Plan (Local & AWS)

### Local (Development)

- **Files**
  - `Dockerfile.dev` (Laravel app + PHP-FPM).
  - `docker-compose.yml` with services:
    - `app`: PHP-FPM container.
    - `web`: Nginx (or Caddy) reverse proxy to `app`.
    - `db`: MySQL/Postgres.

- **Dev Flow**
  - `docker-compose up -d` to start stack.
  - Code mounted as volume for hot reload.
  - Use `artisan` via `docker-compose exec app php artisan ...`.

### Production (AWS)

- **Strategy**
  - Single production `Dockerfile` (no volumes, built assets baked in).
  - Use environment variables for DB and app config.

- **Option A: EC2 + Docker Compose**
  - Provision small EC2 instance (eligible for free tier initially).
  - Install Docker + docker-compose.
  - Pull images from registry and start via compose.

- **Option B: ECS Fargate**
  - Push image to ECR.
  - Create ECS Task Definition and Service using that image.
  - Attach RDS (for DB) or start with RDS-free option if cost-sensitive.

---

## 6. GitHub & Workflow

- **Repos**
  - `elementor-clone` (this Laravel app + Docker setup).
  - `wp-elementor-import-plugin` (WordPress plugin).
  - (Later) `figma-to-elementor-plugin` (Figma plugin).

- **Branching**
  - `main`: stable.
  - `develop`: active development.
  - Feature branches: `feature/elementor-json`, `feature/docker`, etc.

- **CI (GitHub Actions)**
  - Workflow 1: `test.yml`
    - Trigger: push/PR.
    - Steps: install PHP deps, run tests, lint.
  - Workflow 2: `deploy.yml`
    - Trigger: tag or push to `main`.
    - Steps: build Docker image, push, deploy to AWS.

---

## 7. Immediate Next Steps

**Completed so far**

1. Initialize Laravel project and configure Laravel Sail (Docker) for local development.
2. Run initial migrations and verify the application, login, and registration pages work.
3. Scaffold `Project` and `Design` models, migrations, controllers, and form request classes.
4. Define `projects` and `designs` database schema and relationships, including JSON storage for `layout_json` and `html` fields.
5. Configure file-based sessions and confirm stable login/logout flow.
6. Add authenticated resource routes for `projects` and `projects.designs` and a basic Projects index/create UI.

**Next focus**

1. Complete authenticated CRUD UI for `Projects` and `Designs` (show/edit views, designs index/create/edit/show/delete pages).
2. Add support to upload or paste a mock layout JSON file for a `Design` and persist it.
3. Implement a simple JSON → HTML mapping service and an HTML preview page for a `Design`.
4. Initialize the GitHub repository (if not already), push the project, and add a basic CI workflow that runs the test suite on every push/PR.
