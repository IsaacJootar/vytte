# Vytte Engineering Instructions

Read this file before changing the repository. Repository code and migrations are the technical source of truth; architecture decisions are recorded under `docs/architecture/`.

## Product

Vytte is a health-assessment platform. Every feature must make health assessments easier.

Product principles:

- Simplicity before capability.
- Progressive disclosure.
- Preserve before replacing; extend before rewriting.
- AI assists rather than replaces.
- Evidence is optional support attached to a response, not a separate repository.
- Community and patient feedback are assessment templates, not an independent subsystem.

## Assessment creation

There are exactly two creation paths:

1. **Comprehensive Health Assessment** — assess health across an entire setting with a setting-appropriate framework. Users may remove non-applicable areas. Use “department” only for settings that genuinely have departments.
2. **Focused Health Assessment** — assess exactly one health domain, programme, topic, or intervention from one published template. Do not show unrelated modules, batteries, or checkboxes.

Settings may include health facilities, schools, communities, correctional facilities, workplaces, places of worship, NGOs/programmes, government organizations, or custom contexts. A setting is not a health domain.

## Current architecture authority

Read, in order, for architecture or assessment work:

1. `docs/architecture/CURRENT_ARCHITECTURE.md`
2. `docs/architecture/CURRENT_ASSESSMENT_FLOW.md`
3. `docs/architecture/DATA_MODEL_AUDIT.md`
4. `docs/architecture/LIFECYCLE_STATE_MACHINE.md`
5. `docs/architecture/PRESERVATION_REGISTER.md`
6. `docs/architecture/ARCHITECTURE_GAPS.md`
7. `docs/architecture/DECISION_LOG.md`

## Stack

| Layer | Decision |
|---|---|
| Framework | Laravel 13 on PHP 8.3+ |
| UI | Blade, Livewire 4, Alpine.js |
| CSS/build | Tailwind CSS 4 CSS-first configuration and Vite |
| Production database | PostgreSQL |
| Local/test database | SQLite is temporarily supported; PostgreSQL parity remains required before release |
| Authentication | Laravel Breeze |
| Email | Resend, gated by platform settings |
| Icons | blade-ui-kit/blade-heroicons |

## Engineering rules

1. Use plain language in UI copy.
2. Scope tenant-owned operations through the active workspace and explicit policies. Only models that actually carry workspace ownership use a global workspace scope; downstream records inherit authority through their project/assessment relationship.
3. Keep projects isolated inside a workspace.
4. Preserve the one-setting-per-project invariant.
5. Treat published template versions, assessment snapshots, completed scores, and final report snapshots as immutable historical contracts.
6. Use the canonical lifecycle in `LIFECYCLE_STATE_MACHINE.md`.
7. Keep evidence inline and optional.
8. Do not create parallel scoring or reporting systems for respondent roles.
9. Make mobile-first, accessible interfaces that work at 375px.
10. Handle external-service failures in plain language without exposing raw exceptions.
11. Work in bounded modules; run focused tests and the full suite.
12. Commit and push each completed module separately.
13. Preserve unrelated worktree changes and never stage them accidentally.
14. PostgreSQL parity is a release gate even when SQLite is used locally.

## Scoring

- Canonical output is 0–100.
- Published templates must have a complete frozen scoring profile for every scored question.
- Uncalibrated results remain `null` with `NOT_CALIBRATED`; never turn missing calibration into zero.
- Every algorithm change requires a new scoring version.
- Completed assessments are never silently recalculated.

## Git identity

```bash
git config user.name "Isaac Jootar"
git config user.email "jootarisaac@gmail.com"
```
