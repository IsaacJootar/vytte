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
| Assessment objectives | 29 |
| Health areas | 147, across 36 health domains |
| Analysis lenses | 20 |
| Insight categories | 21 |
| Assessment templates | 40 |
| Objective recommendations | ~110 |
| Objective presets | 40 |

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
- Health areas are uneven by design, because subjects genuinely differ in how much detail they need. `GENERAL_HEALTH_SYSTEMS` is deliberately small at four areas; everything routinely assessed on its own has been promoted to a health domain. A test fails if it grows past six.

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

## Review findings — resolved 2026-07-20

All blocking findings from the architecture review are closed.

| Finding | Resolution |
| --- | --- |
| D1 — Malaria, NCDs and NTDs were not health domains | Health domains expanded from 12 to 36. Malaria, Non-Communicable Diseases, Neglected Tropical Diseases, Laboratory, Pharmacy, Emergency and Critical Care, Surgical Care, Blood Services, Diagnostic Imaging, Rehabilitation, Palliative Care, Oral Health, Eye Health, Sexual and Reproductive Health, Adolescent Health, Older People, Disability and Inclusion, Community Health, Health Information Systems, Health Promotion, Environmental Health, Occupational Health, Antimicrobial Resistance and Outbreak Response are all first class. The `MALARIA_BASELINE` preset now points at Malaria rather than General Health Systems. |
| General Health Systems absorbing subjects | Reduced from 15 areas to 4. A test fails if it grows past 6, because a swelling catch-all is the signal that something inside deserves to be a domain. |
| Data Gaps insight category | Added, with Insufficient Evidence, No Change, Deterioration, Systemic Issues and Good Practice to Share. Data Gaps and Insufficient Evidence are marked diagnostic. |
| Data Quality Assessment objective | Added, with Training and Capacity Needs, Results-Based Financing Verification, Outbreak Response Review, Service Expansion Readiness, Efficiency and Value, Sustainability Review and Patient Satisfaction. |
| Analysis lenses | Efficiency and Value, Sustainability and Data Confidence added. |

### Also found and fixed during the expansion

Promoting subjects to health domains exposed a collision the original catalogue already
had: nine objectives named a subject or a measurement dimension rather than a purpose —
Health Workforce, Leadership and Governance, Health Financing, Health Information,
Infrastructure, Supply Chain, Community Engagement, Digital Health and Health Promotion.
Health Promotion had become an exact code collision with the new health domain.

These were removed from the objective catalogue and replaced with objective presets, so
a user still starts from the familiar name while the model behind it stays a purpose
narrowed by a subject or dimension. Two tests now enforce this in both directions, one
checking against the whole health domain table so a future promotion cannot silently
reintroduce a collision.

The seeder also gained pruning. It previously could only add, so an entry removed from
the catalogue lingered in the database and was still shown to administrators. It now
reconciles, and a test proves a dropped entry is removed.

### Financing measurement domain — resolved

`FIN` (Financing and Resource Management) is now defined in the published taxonomy, completing the WHO health system building blocks. A fresh install seeds all eight in version 1; the existing environment was moved forward through the normal governance path — new version, publish, previous version superseded — with both steps audited.

Publication now refuses any taxonomy version that leaves a measurement domain undefined, so no domain can be left staged and inert again. See DEC-2026-07-20-033.

## Pre-seed validation

`php artisan methodology:validate` checks that every entity is reachable and every reference resolves. It must pass before the master seed, and a test runs it.

First run found twelve advisories, all now fixed: seven objectives that suggested nothing and appeared in no starting point, and five templates nothing routed to. Current result: no problems, no advisories.
