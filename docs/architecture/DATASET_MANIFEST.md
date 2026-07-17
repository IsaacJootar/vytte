# Seed Dataset Manifest

## Authority

Seed counts describe the repository's development dataset; they are not architecture constants, production guarantees, or evidence of clinical approval.

The default seed sequence is:

1. `PlatformSettingsSeeder`
2. `ReferenceDataSeeder`
3. `HivawQuestionsSeeder`
4. `AssessmentTemplateSeeder`
5. `PlanFeatureSeeder`
6. `DemoAccountSeeder`
7. `DemoDataSeeder`

The default seed is repository-contained. It does not read personal Downloads folders or external documents.

## Reference baseline

The current reference/content baseline is:

| Dataset | Expected baseline |
|---|---:|
| Target types | 10 |
| Setting types | 10 |
| Health domains | 12 |
| Scoring domains | 8 |
| Assessment tiers | 2 |
| Question types declared | 10 |
| Assessment modules | 27 |
| Sub-indices with curated mappings | 4 |
| Governed questions in a clean seed | 9 |
| Topics | 7 |
| Respondent roles | 8 |
| Published standard templates | 1 after the complete seed |

`SINGLE_SELECT`, `LIKERT`, unscored `OPEN_ENDED`, and `NUMERIC` are currently publishable. Scored numeric questions require frozen scoring bands; a declared database type is not automatically supported.

## Governed content rule

Seed content is sample/reference content until it has:

- an identified source authority;
- licence/provenance metadata;
- a completed scoring profile for scored questions;
- a curator review;
- an immutable published template version.

Counts may change only through a reviewed dataset update. Every update should record the changed seeder/artifact, source/version, licensing disposition, expected counts, template versions affected, and scoring/test impact.

## Removed sample dependencies

The former personal-path PHSAI document importer and incomplete school sample seeder were removed. They must not be restored as silent or environment-dependent default seed steps. New frameworks should enter as governed structured content and publish only after validation.
