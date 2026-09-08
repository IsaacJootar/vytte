# Vytte

Vytte is a platform-governed health assessment system. It provides comprehensive health facility assessments and focused health assessments from official Vytte catalogue releases, immutable snapshots, versioned scoring, workspace isolation, and one reporting architecture.

## Product Model

Vytte has exactly two creation paths:

1. **Comprehensive Health Assessment** composes one full-facility assessment from a published Vytte catalogue release. The release pins exact department framework versions for the selected facility profile. Required and default departments are preloaded; optional departments can be added; removable defaults can be excluded with a reason.
2. **Focused Health Assessment** opens one approved health domain, programme, topic, or intervention. It does not show unrelated departments or grouped module checklists.

Comprehensive Health Assessment is not a giant template. It is a composition orchestrator.

Community surveys, patient-experience surveys, caregiver feedback, and similar use cases are normal assessment content in the same architecture. Respondent role may differ; lifecycle, scoring, reports, permissions, exports, dashboards, and analytics stay unified.

## Platform Authority

Vytte does not claim universal authorship of official content. An instrument's accountable
publisher — Vytte itself, or a standards body, government, hospital, programme, researcher, or
expert — owns that instrument's content purpose, methodology, and scoring claim.

Vytte governs:

- publisher identity, provenance, and review state;
- departments, department framework versions, facility profiles, and assessment catalogue releases;
- questions, indicators, evidence requirements, scoring rules, and aggregation policies;
- technical validation, immutable versioning, hashes, audit, reproducibility, and comparison eligibility.

Workspaces consume approved content and may submit candidate questions through a governed review
path; they do not themselves publish official departments, frameworks, scoring methods, or
catalogue releases.

Workspace-local custom sections are allowed only as clearly marked local context. They cannot alter
official questions, framework versions, catalogue releases, scoring, or reports.

## Stack

- PHP 8.3+ and Laravel 13
- Blade, Livewire 4, Alpine.js, Tailwind CSS 4, and Vite
- PostgreSQL as the production authority
- PHPUnit 12

## Local Setup

### 1. Start PostgreSQL

The repository ships a PostgreSQL service. It publishes port **5433** on the host to avoid
clashing with an existing local PostgreSQL on 5432.

```bash
docker compose up -d
```

### 2. Create the test database

The test suite runs against a separate `vytte_test` database. Create it once:

```bash
docker exec vytte_postgres createdb -U vytte vytte_test
```

### 3. Install and boot the application

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

## PostgreSQL Configuration

Local development, automated tests, production, and release-candidate verification use PostgreSQL.
`.env.example` matches the shipped `docker-compose.yml`:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5433
DB_DATABASE=vytte
DB_USERNAME=vytte
DB_PASSWORD=secret
```

If you run your own PostgreSQL instead of the shipped service, set the port and password to match
it, and keep `phpunit.xml` in step.

## Demo Accounts (optional, local development only)

`php artisan migrate --seed` does not create any user accounts — the default seed is the real
official catalogue with no demo content (see Seed Data below). To get local login accounts for
development, run the demo seeder explicitly:

```bash
php artisan db:seed --class=DemoAccountSeeder
```

This creates four accounts, all with the password `password`:

- `starter@vytte.test`
- `professional@vytte.test`
- `organization@vytte.test`
- `admin@vytte.test` (Vytte Platform Admin)

It creates only the accounts and their workspaces — no demo assessments, scores, or reports.

## Verification

```bash
php artisan test
npm run build
php artisan vytte:preflight
```

The full test suite runs against PostgreSQL and is expected to pass as one sequential run, not as
separate batches. Batched runs have previously hidden failures that only a full sequential run
surfaces.

## Deployment

`deploy/README.md` documents the current production contract (cPanel, PHP path, database, backup
location) and the systemd units for the queue worker, scheduler, and backup timer. Monitoring,
alerting, and incident response are not yet codified — see `docs/architecture/GO_LIVE_CHECKLIST.md`
and `docs/architecture/OPERATIONS_READINESS.md` for current status and outstanding requirements.
Do not deploy to production against this README alone.

## Architecture References

`AGENTS.md` is the authoritative engineering guide and the single home for architecture
navigation — read it before changing anything.

## Seed Data

`php artisan migrate --seed` runs the real official production seed (`DatabaseSeeder`): the
official source-informed catalogue, fully published, with no demo accounts, workspaces,
assessments, or fixture content. See Demo Accounts above for an optional, explicit, local-only
seeder that adds development login accounts on top of it.
