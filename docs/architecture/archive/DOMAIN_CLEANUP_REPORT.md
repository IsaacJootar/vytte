> **ARCHIVED 2026-07-19.** Historical record. Accurate when written; describes a past state of the repository. Do not treat as current and do not rewrite. See `README.md` in this folder.

# Domain Cleanup Report

## Current resolution

Vytte now separates three concepts cleanly:

- **Health domains** (`health_domains`) for focused assessment selection.
- **Analytical domains** (`domains`, taxonomy/version/mapping tables) for reporting and analysis.
- **Question groups** (`question_groups`) for structural grouping of questions inside a department or focused scope.

## Removed/replaced

- Removed old PHSAI/data-burden seed values as the primary analytical-domain set.
- Removed the obsolete `domain_weights` table from the reference migration.
- Replaced sub-index-only analytical scoring with indicator/placement-mapped analytical scoring.
- Replaced structural question-group UI/import language with `question_groups`.

## Current rule

Question groups are not analytical domains. Health domains are not analytical domains. Analytical domains are governed through the published taxonomy architecture.
