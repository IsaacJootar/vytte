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

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

## PostgreSQL Configuration

Local development, automated tests, production, and release-candidate verification use PostgreSQL:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=vytte
DB_USERNAME=vytte
DB_PASSWORD=change-me
```

## Verification

```bash
php artisan test
npm.cmd run build
```

The full test suite is expected to run against PostgreSQL.

## Architecture References

- `docs/architecture/CURRENT_ARCHITECTURE.md` - implemented platform model
- `docs/architecture/CURRENT_ASSESSMENT_FLOW.md` - assessment lifecycle
- `docs/architecture/DATA_MODEL_AUDIT.md` - schema authority
- `docs/architecture/CONTENT_GOVERNANCE.md` - publication and curation rules
- `docs/architecture/SCORING_CONTRACT.md` - scoring and aggregation rules
- `docs/architecture/DECISION_LOG.md` - controlling decisions

## Seed Data

The default seed includes a small clearly labelled demonstration dataset so the architecture can be tested end to end. It is not production clinical content and must not be presented as approved clinical methodology.
