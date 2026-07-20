# Master Seed Plan

What the official seed will contain, and in what order. **Not yet executed** — the master seed runs only after approval.

## Order

The methodology depends on reference taxonomy, and content depends on methodology. Seeding out of order produces a catalogue whose recommendations resolve to nothing.

1. Reference taxonomy — target types, setting types, health domains, measurement domains, question types, assessment tiers. *(Existing seeders, unchanged.)*
2. **Methodology catalogue** — `MethodologyCatalogueSeeder`. Objectives, health areas, analysis lenses, insight categories, templates, objective recommendations, presets.
3. Official question library — question identities and published versions. *(Not yet written. Blocked on methodology approval, which is the reason P4 precedes the seed.)*
4. Framework versions and catalogue releases composed from the library.
5. Plan features. *(Existing.)*

## Methodology catalogue contents

| Entity | Count |
| --- | --- |
| Assessment objectives | 30 |
| Health areas | 61, across 12 existing health domains |
| Analysis lenses | 17 |
| Insight categories | 15 |
| Assessment templates | 22 — 6 enterprise, 16 focused |
| Objective recommendations | 74 |
| Objective presets | 15 |

## Properties

- **Idempotent.** Every entity uses `updateOrCreate` on its natural key. Re-running changes nothing, and a test asserts this.
- **Refuses to alter a published methodology.** If version 1 is published, the seeder warns and exits rather than attempting a write the model would reject.
- **Not part of the default seed.** Run explicitly: `php artisan db:seed --class=MethodologyCatalogueSeeder`.
- **Transactional.** A partial catalogue is worse than none, because publication validation would pass against an incomplete set.

## Sources

Curated against WHO Service Availability and Readiness Assessment (SARA), Service Provision Assessment (SPA), the Harmonized Health Facility Assessment (HHFA), the WHO health system building blocks, WHO/UNICEF WASH FIT, WHO IPC minimum requirements, and the programme review and supportive supervision patterns commonly used across Nigeria, Ghana, Kenya and South Africa.

Deliberately generic where national standards differ. Vytte supplies structure and vocabulary; a workspace supplies its own thresholds and content. No country's regulations are encoded as though universal.

## Honest limits

- This catalogue is **curated from established practice, not verified against source documents in this phase.** Before it becomes the official master seed, a health methodologist should review it. It is broad and defensible, not authoritative.
- Coverage is aimed at the common beta cases. The claim is that most beta users can start from an existing objective, not that the catalogue is exhaustive.
- Health areas are uneven by design: `GENERAL_HEALTH_SYSTEMS` carries 14 because it absorbs clinical services that have no dedicated health domain yet. Malaria and NCDs currently sit there. If either becomes a frequent focus, promoting it to a health domain is a methodology version change, not a schema change.

## After approval

Approving the methodology catalogue unblocks the official question library, which is the largest remaining seed component and the one that must follow this methodology rather than precede it.

## Blocking findings from the P4 architecture review

Recorded 2026-07-20. See `DIAGNOSTICS_AND_INTELLIGENCE_PIPELINE.md` for the full analysis.

These should be resolved **before** the master seed, because after it the official question library will already be mapped to health domains and re-mapping means a methodology version plus a content migration.

| # | Finding | Severity |
| --- | --- | --- |
| D1 | Malaria, NCDs and NTDs are not health domains. Malaria is an area under `GENERAL_HEALTH_SYSTEMS`, so the seeded `MALARIA_BASELINE` preset mis-files every malaria assessment. For a product whose primary market is Nigeria, Ghana, Kenya and South Africa this is a methodology error rather than a gap. | **Blocking** |
| — | Six subjects should be promoted to health domains: Malaria, Non-Communicable Diseases, Laboratory, Pharmacy and Supply Chain, Emergency and Critical Care, Neglected Tropical Diseases. `GENERAL_HEALTH_SYSTEMS` currently carries 14 areas, which is a sign it is absorbing too much. | **Blocking** |
| — | Add a **Data Gaps / Insufficient Evidence** insight category. The platform already computes `NOT_CALIBRATED` and `PARTIAL` calibration states and nothing surfaces them, so an assessment that is 40% unanswered produces a confident-looking report from thin data. | **Blocking** |
| — | Add three objectives: Data Quality Assessment, Training and Capacity Needs Assessment, Results-Based Financing Verification. DQA is the most commonly performed international assessment type currently absent. | Recommended |
| — | Consider Health Financing as an eighth measurement domain. It is a WHO building block with no dimension for findings to roll up into. Touches `domain_scores`; a genuine architectural change rather than catalogue content. | Decision needed |
| D2 | No link between a baseline assessment and its endline. Trend infers sequence by date, which is right for monitoring and wrong for a study. | Non-blocking |
| D3 | No agreed-actions entity for the Progress Tracking lens. Supportive supervision has nothing to track progress against. | Non-blocking |
