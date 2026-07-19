# Vytte

Vytte is a platform-governed health assessment system. It provides comprehensive health facility assessments and focused health assessments from official Vytte catalogue releases, immutable snapshots, versioned scoring, workspace isolation, and one reporting architecture.

## Product Model

Vytte has exactly two creation paths:

1. **Comprehensive Health Assessment** composes one full-facility assessment from a published Vytte catalogue release. The release pins exact department framework versions for the selected facility profile. Required and default departments are preloaded; optional departments can be added; removable defaults can be excluded with a reason.
2. **Focused Health Assessment** opens one approved health domain, programme, topic, or intervention. It does not show unrelated departments or grouped module checklists.

Comprehensive Health Assessment is not a giant template. It is a composition orchestrator.

Community surveys, patient-experience surveys, caregiver feedback, and similar use cases are normal assessment content in the same architecture. Respondent role may differ; lifecycle, scoring, reports, permissions, exports, dashboards, and analytics stay unified.

## Platform Authority

Vytte owns official:

- departments;
- department framework versions;
- facility profiles;
- assessment catalogue releases;
- questions, indicators, evidence requirements, scoring rules, and aggregation policies;
- publication, versioning, hashes, provenance, and audit.

Workspaces consume approved content. They do not publish official departments, frameworks, scoring methods, or catalogue releases.

Workspace-local custom sections are allowed only as clearly marked local context. They cannot alter official questions, framework versions, catalogue releases, scoring, or reports.

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

## Demo Accounts

`php artisan migrate --seed` creates demo accounts, all with the password `password`:

- `starter@vytte.test`
- `professional@vytte.test`
- `organization@vytte.test`
- `admin@vytte.test` (Vytte Platform Admin)

The seed also creates a labelled demonstration catalogue and demo assessments, scores, and reports.

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

Production deployment procedures, backup and restore, monitoring, queue supervision, and incident
response are not yet codified. See `docs/architecture/OPERATIONS_READINESS.md` for the current
status and outstanding requirements. Do not deploy to production against this README alone.

## Architecture References

- `docs/architecture/CURRENT_ARCHITECTURE.md` - implemented platform model
- `docs/architecture/CURRENT_ASSESSMENT_FLOW.md` - assessment lifecycle
- `docs/architecture/DATA_MODEL_AUDIT.md` - schema authority
- `docs/architecture/QUESTION_BANK_ARCHITECTURE.md` - reusable question identity, question version, and framework placement model
- `docs/architecture/OFFICIAL_ASSESSMENT_CONTENT_LIFECYCLE.md` - official content publication lifecycle
- `docs/architecture/WORKSPACE_CUSTOM_ASSESSMENT_ARCHITECTURE.md` - customer-created assessment boundaries
- `docs/architecture/AI_ASSISTED_ASSESSMENT_BOUNDARIES.md` - future AI drafting limits
- `docs/architecture/CONTENT_GOVERNANCE.md` - publication and curation rules
- `docs/architecture/SCORING_CONTRACT.md` - scoring and aggregation rules
- `docs/architecture/DECISION_LOG.md` - controlling decisions

## Seed Data

The default seed includes a small clearly labelled demonstration dataset so the architecture can be tested end to end. It is not production clinical content and must not be presented as approved clinical methodology.
