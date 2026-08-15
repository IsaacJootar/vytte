# Seed Dataset Manifest

## Authority

`DatabaseSeeder` is the official production seed. It is repository-contained and never reads personal
Downloads folders or untracked source documents.

The production sequence is:

1. `PlatformSettingsSeeder`
2. `ReferenceDataSeeder`
3. `OfficialTaxonomySeeder`
4. `SubscriptionPlanSeeder`
5. `PlanFeatureSeeder`
6. `OfficialReferenceSeeder`
7. `MethodologyCatalogueSeeder`
8. `OfficialQuestionLibrarySeeder`
9. `OfficialDepartmentQuestionLibrarySeeder`
10. `OfficialFrameworkSeeder`
11. `OfficialCatalogueSeeder`
12. methodology publication, publisher backfill, and scoring-model backfill

`PlatformGovernedDemoSeeder`, `DemoAccountSeeder`, and `DemoDataSeeder` are excluded from production.
They remain test fixtures only; `TestBaselineSeeder` may use the governed demonstration baseline for
fast deterministic automated tests.

## Official beta library

The current official seed publishes:

- 388 question identities and immutable question versions;
- 57 framework versions and 926 placements;
- 46 departments;
- 41 catalogue releases, of which 36 are currently published;
- 23 health-facility profiles with comprehensive releases;
- 13 focused subjects;
- one published eight-domain measurement taxonomy;
- 40 methodology starting points.

`OFFICIAL_SEED_REPORT.md` records the content breakdown. Counts are release metadata, not permanent
architecture constants.

## Publication rule

The seed uses the same publishing services, validation, hashes, audit, immutable versions, supported
response rules, and scoring-model requirements as interactive authoring. Being in the official seed
does not manufacture independent clinical review. Evidence-backed governance claims remain separate
records and must reflect real reviewers and evidence.

## Publishable response types

- `SINGLE_SELECT`
- `MULTI_SELECT`
- `LIKERT`
- explicitly unscored `OPEN_ENDED`
- `NUMERIC`, with frozen bands whenever scored

Declaring another type in reference data does not make it publishable.
