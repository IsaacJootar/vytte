# Vytte

Vytte is a health-assessment platform for comprehensive setting assessments and focused health-domain assessments. It uses reusable, governed templates; immutable assessment and report snapshots; versioned scoring; workspace isolation; and one reporting architecture.

## Product model

Vytte has exactly two creation paths:

1. **Comprehensive Health Assessment** loads a setting-appropriate framework. Users remove assessment areas that do not apply. “Department” is used only where the setting genuinely has departments.
2. **Focused Health Assessment** opens one health domain, programme, topic, or intervention from one published template. It does not load unrelated modules or a standard battery.

Community surveys, patient-experience surveys, caregiver feedback, and similar use cases are normal assessment templates. Respondent role may differ; the lifecycle and reporting architecture do not.

## Stack

- PHP 8.3+ and Laravel 13
- Blade, Livewire 4, Alpine.js, Tailwind CSS 4, and Vite
- PostgreSQL as the production authority
- SQLite for temporary local desktop development and the fast automated test suite
- PHPUnit 12

## Local setup

Requirements: PHP 8.3+, Composer, Node.js/npm, and the PHP extensions required by Laravel.

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

For active frontend development, run `npm run dev` in a second terminal.

The default example configuration uses `database/database.sqlite`. Create the file if it does not exist:

```bash
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
```

## PostgreSQL configuration

Production and release-candidate verification must use PostgreSQL:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=vytte
DB_USERNAME=vytte
DB_PASSWORD=change-me
```

SQLite passing is not a substitute for PostgreSQL parity on migrations, partial indexes, foreign keys, upserts, and concurrency-sensitive response writes.

## Verification

```bash
composer test
npm run build
```

Before release, also run the full test suite against PostgreSQL.

## Architecture references

- `AGENTS.md` — active engineering rules
- `docs/architecture/CURRENT_ARCHITECTURE.md` — implemented platform
- `docs/architecture/CURRENT_ASSESSMENT_FLOW.md` — assessment lifecycle
- `docs/architecture/DATA_MODEL_AUDIT.md` — schema authority and risks
- `docs/architecture/LIFECYCLE_STATE_MACHINE.md` — canonical states
- `docs/architecture/DECISION_LOG.md` — approved product and architecture decisions
- `docs/architecture/IMPLEMENTATION_PROGRESS.md` — remediation commits and verification

## Data and content

Published template versions and completed report snapshots are immutable. New or corrected content must be published as a new governed version. Seed content is development/reference content and must not be treated as production clinical authority without source, licence, and review metadata.
