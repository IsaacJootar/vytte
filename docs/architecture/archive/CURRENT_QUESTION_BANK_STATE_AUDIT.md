> **ARCHIVED 2026-07-19.** Historical record. Accurate when written; describes a past state of the repository. Do not treat as current and do not rewrite. See `README.md` in this folder.

# Current Question-Bank State Audit

Date: 2026-07-18

## Executive finding

The old PHSAI question bank is not present as assessment content in the current repository or clean seeded PostgreSQL database.

After the clean-slate cleanup, Vytte currently seeds only governed demonstration assessment content through `PlatformGovernedDemoSeeder`. The implementation already publishes immutable department framework payloads and freezes those payloads into assessment snapshots, but the source model still reads question text, response options, numeric bands, and scoring links directly from reusable-looking `questions` records attached to `assessment_modules`.

That means the old PHSAI content is gone, but the question-bank architecture is not yet fully separated into question identity, immutable question version, and framework placement.

## PHSAI source scan

Repository scan covered:

- application models, services, controllers, Livewire components, and resources;
- routes;
- database migrations, seeders, factories, and fixtures;
- automated tests;
- documentation;
- README and environment examples.

Findings:

- No old PHSAI questions were found in source files.
- No old PHSAI seeder remains.
- No old PHSAI tests remain.
- No old PHSAI models, services, controllers, Livewire components, API resources, routes, factories, fixtures, or UI components remain.
- One documentation line still mentions the historic `phsai_schema.sql v1.1` schema origin in `docs/modules/01-foundation.md`; it is documentation residue only and does not seed or run question content.

## Migration, copy, adaptation, or reuse

No evidence was found that old PHSAI questions were migrated, copied, adapted, or reused inside the governed demonstration framework versions.

Current demonstration questions use `source = DEMO_CURATED`, `question_status = APPROVED`, and codes in the `DOPD.DEMO.*`, `DPHM.DEMO.*`, `DLAB.DEMO.*`, and `DMNH.DEMO.*` families.

## Remaining old identifiers or references

No current source references were found for:

- `PHSAI`;
- `HIVAW`;
- old assessment-template classes;
- old template publishing services;
- old `template_version_id`;
- old community-voice reporting subsystem;
- standard-battery legacy flow.

## Current seeded demonstration question count

A clean PostgreSQL `php artisan migrate:fresh --seed --force` currently produces:

- 16 active official demonstration questions;
- 4 published department framework versions;
- 2 published catalogue releases.

The 16 demonstration questions are:

| Module | Question codes |
|---|---|
| DOPD | `DOPD.DEMO.Q1`, `DOPD.DEMO.Q2`, `DOPD.DEMO.Q3`, `DOPD.DEMO.Q4` |
| DPHM | `DPHM.DEMO.Q1`, `DPHM.DEMO.Q2`, `DPHM.DEMO.Q3`, `DPHM.DEMO.Q4` |
| DLAB | `DLAB.DEMO.Q1`, `DLAB.DEMO.Q2`, `DLAB.DEMO.Q3`, `DLAB.DEMO.Q4` |
| DMNH | `DMNH.DEMO.Q1`, `DMNH.DEMO.Q2`, `DMNH.DEMO.Q3`, `DMNH.DEMO.Q4` |

Each module has:

- two scored option questions;
- one unscored open-text context question;
- one unscored numeric context question.

## Files defining governed demonstration questions

Current governed demonstration content is defined primarily in:

- `database/seeders/PlatformGovernedDemoSeeder.php`
- `database/seeders/ReferenceDataSeeder.php`

The publication and snapshot behavior is implemented through:

- `app/Services/DepartmentFrameworkPublishingService.php`
- `app/Services/FrameworkContentService.php`
- `app/Services/CataloguePublishingService.php`
- `app/Services/AssessmentCreationService.php`

## Clean seed result

Confirmed before Phase 2 changes:

```bash
php artisan migrate:fresh --seed --force
```

Result: passed on PostgreSQL.

The clean seed produces the current governed demonstration content only.

## Dependency finding

No legacy PHSAI question-bank code remains because another legacy component depends on it.

However, the current official framework publishing implementation still depends on the older direct question storage shape:

- `questions` owns the rendered wording and response type directly;
- `question_options` and `question_numeric_bands` belong directly to `questions`;
- `sub_index_questions` connects scoring directly to `questions`;
- `DepartmentFrameworkPublishingService` publishes a framework by reading `AssessmentModule->questions`;
- `FrameworkContentService` freezes those direct records into `department_framework_versions.published_payload`.

This is not a PHSAI compatibility layer, but it is not yet the approved reusable question-version architecture.

## Current governed framework implementation model

The current implementation:

- owns question text directly in `questions`;
- references reusable-looking question records through module relationships;
- freezes rendered content into `department_framework_versions.published_payload`;
- freezes selected published framework payloads again into `assessment_snapshots.payload`;
- does not yet separate question identity, immutable question version, and framework-specific placement.

## Phase 2 implication

Phase 2 should keep the good parts:

- immutable published framework payloads;
- immutable assessment snapshots;
- catalogue-driven comprehensive composition;
- focused assessments using governed releases;
- local custom sections excluded from official scoring.

Phase 2 should replace the remaining direct question-source model with:

- reusable question identities;
- immutable question versions;
- framework sections;
- framework indicators;
- framework-specific question placements;
- published payloads resolved from exact question versions.

