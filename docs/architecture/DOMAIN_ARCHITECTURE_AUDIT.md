# Domain Architecture Audit

## Current-state finding before implementation

The repository had three different concepts using the word “domain”:

1. `domains` / `domain_scores` / `sub_indices.domain_id` — an old global scoring grouping seeded with operational PHSAI-style labels.
2. `module_domains` — a legacy structural question grouping under an assessment module.
3. `health_domains` — focused health topics such as Mental Health, HIV, WASH, and Patient Experience.

Only `health_domains` aligned cleanly with the approved product model, because it represents focused assessment topics rather than analytical reporting domains.

## Domain-related tables and migrations

- `domains` in `2026_07_15_000004_create_reference_tables.php`.
- `sub_indices.domain_id` in `2026_07_15_000006_create_sub_indices_table.php`.
- `module_domains` in `2026_07_15_000005_create_assessment_modules_table.php`.
- `questions.module_domain_id` in `2026_07_15_000007_create_questions_table.php`.
- `domain_scores` and `project_domain_scores` in `2026_07_15_000011_create_scoring_tables.php`.
- `recommendation_rules.domain_id` in `2026_07_15_000012_create_root_causes_and_recommendations_table.php`.
- `health_domains` and `assessment_module_health_domain` in `2026_07_17_000003_create_health_taxonomy.php`.
- `framework_indicators` and `framework_question_placements` in `2026_07_18_000002_create_question_bank_architecture.php`.

## Old seeded domains found

The old seed values were:

- Workflow Efficiency
- Documentation Burden
- Reporting Burden
- Data Quality
- Digital Readiness
- Operational Pain
- Decision Intelligence
- Clinical & Service Quality

These were removed from the seed architecture because they reflected the previous PHSAI/data-burden model rather than Vytte’s official health-assessment analytical lens.

## Pre-change behavior

- Domains did not own questions.
- Domains did not generate questions.
- Domains did not compose comprehensive or focused assessments.
- Questions did not map directly to official analytical domains.
- Framework indicators did not map to official analytical domains.
- Domain scores were calculated by averaging sub-index scores grouped by `sub_indices.domain_id`.
- Reports displayed domain scores from that sub-index grouping.
- Findings were currently derived from weak sub-index scores in the results UI, not a governed recommendation engine.
- Recommendation tables existed, but no complete official recommendation engine was active.
- Benchmarks were not implemented as a governed domain-compatible system.
- Critical failures affected overall scoring through aggregation policy, not domains.
- `module_domains` affected old module library navigation and import structure but did not represent official analytical domains.

## Active, partial, obsolete, duplicated

- Active: `health_domains` for focused assessment selection.
- Active but changed: `domains` as canonical domain IDs for official analytical-domain definitions and score rows.
- Active but renamed in UI: `module_domains` as internal question groups for legacy question identities.
- Obsolete: old PHSAI/data-burden domain seed values and `domain_weights`.
- Partial before implementation: domain scores in reports.
- Missing before implementation: governed taxonomy versions, immutable domain definitions, indicator mappings, placement overrides, frozen taxonomy metadata in snapshots/reports, platform-admin inspection of taxonomy/mapping coverage.
