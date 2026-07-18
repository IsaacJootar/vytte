# Domain Cleanup Report

## Removed/replaced

- Removed old PHSAI/data-burden domain seed values.
- Removed the obsolete `domain_weights` table from the reference migration.
- Replaced sub-index-driven domain scoring as the primary path with indicator/placement-mapped analytical scoring.
- Renamed visible legacy “domain” wording for module question groups to “question group.”
- Replaced module import JSON key `domains` with `question_groups`.

## Retained with narrowed meaning

- `domains` remains as the canonical small domain identity table for the official taxonomy.
- `module_domains` remains as an internal legacy table name used by question identities, but the UI now treats it as a question group, not as an analytical domain.
- `health_domains` remains as focused-assessment topic taxonomy, separate from analytical domains.

## Remaining caution

The physical `module_domains` table name is still historical. Full physical renaming would require a broader question-identity table rewrite; the current cleanup removes the misleading user-facing and admin-facing concept while preserving the working question identity storage.
