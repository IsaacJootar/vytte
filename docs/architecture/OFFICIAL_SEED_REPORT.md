# Official Master Seed — Build Report

The canonical production knowledge base that ships with Vytte. Built stage by stage,
each stage published through the governed lifecycle and verified against PostgreSQL.

## Headline

A fresh database seeded from `DatabaseSeeder` is production-ready and contains **no
demonstration content**. A beta customer signing in finds official starting points for a
general hospital, a primary healthcare facility, and thirteen focused subjects.

| Entity | Count |
| --- | --- |
| Official questions | 238 |
| Published question versions | 238 |
| Official frameworks | 15 |
| Catalogue releases (selectable) | 15 |
| Facility profiles | 28 |
| Departments | 46 |
| Measurement domains | 8 (published taxonomy) |
| Methodology | Published |

Scope was cut to roughly half the originally planned ~600 questions by instruction, to
reach a shippable beta sooner. The remaining depth can be added post-seed through the same
governed workflow, because questions and frameworks only ever add.

## What ships

### Questions, by department

| Department | Questions | Department | Questions |
| --- | --- | --- | --- |
| Leadership & Governance | 17 | HIV / TB / PMTCT | 20 |
| Workforce (HR) | 15 | Malaria | 9 |
| Quality & Patient Safety | 16 | Immunization | 10 |
| Infrastructure & IPC | 37 | Maternal & Newborn (ANC) | 11 |
| Information & Records | 19 | Nutrition | 7 |
| Financing | 10 | Mental Health | 7 |
| Person-Centredness & Community | 12 | Laboratory | 8 |
| Facility WASH | 16 | Pharmacy | 8 |
| | | Emergency | 8 |

### Frameworks (all published, all composed from the shared library)

- **Comprehensive:** Hospital Operational Readiness (134 questions), Primary Healthcare Facility Assessment (86)
- **Cross-cutting focused:** Infection Prevention & Control (25), WASH in Health Care Facilities (21)
- **Programmes:** HIV (27), TB (25), Malaria (25), Immunization (26)
- **Population:** Maternal & Newborn (27), Child Health (24), Nutrition (23), Mental Health (23)
- **Clinical services:** Laboratory (24), Pharmacy (24), Emergency Care (24)

The question count a customer sees exceeds the count authored, because frameworks reuse the
cross-cutting core. Hospital Readiness presents 134 questions built almost entirely from the
136-question spine authored once.

### Catalogue releases

Fifteen, one per framework: two comprehensive (tied to a facility profile), thirteen
focused (tied to a health domain). All aggregate through `MEAN_OF_SCORED_SUB_INDICES` with
critical-failure handling, so a critical answer zeroes the overall score.

## Governance

Every question was published through `QuestionVersionPublishingService`, every framework
through `DepartmentFrameworkPublishingService`, every release through
`CataloguePublishingService`, and the taxonomy and methodology through their own publishing
services. Nothing was written straight to a table. Each object is validated, content-hashed
and audited exactly as one created by hand in the builder.

## PHSAI legacy migration

`PHSAI_Departmental_Questionnaires_v1_1.docx` (23 departments, ~350–400 questions) was
audited. It is a workflow and data-systems discovery study, not a scored assessment, so it
was **not migrated wholesale**. Two seams were harvested, taking the intellectual content
and discarding the genre.

| Legacy questions reviewed | ~350–400 |
| --- | --- |
| Reused unchanged | 0 — the genre differs; nothing transferred verbatim |
| Ideas rewritten into scored questions | ~14 |
| New official questions this created | 6 data-burden + ~8 subject (confidentiality, linkage, high-risk flagging, ANC record availability, screening tools, follow-up loss) |
| Discarded as already covered, wrong genre, or boilerplate | the remainder |
| Duplicates created | 0 |

The valuable intellectual property was preserved and improved: register duplication and
documentation burden (`BURD.001–006`, an angle Vytte lacked), HIV and mental health record
confidentiality with a critical-failure floor, PMTCT linkage quality, structured high-risk
pregnancy identification, and antenatal record availability on return. No PHSAI question was
copied; every retained idea was rewritten to Vytte's scored production standard.

**Remaining PHSAI value not yet harvested:** subject depth in departments not built at this
half scope (theatre, radiology, blood bank, ICU, referral, records, community, family
planning). Available for a later governed pass.

## Removal of demonstration content

The demonstration seeders (`PlatformGovernedDemoSeeder`, `DemoAccountSeeder`,
`DemoDataSeeder`) were removed from the production seed chain. They remain in the codebase
as test fixtures, seeded only by `TestBaselineSeeder` for the automated suite — test
fixtures are not production data. The official measurement-domain taxonomy, which had been
tangled inside the demonstration seeder, was extracted into `OfficialTaxonomySeeder` so the
official chain depends on no demonstration content.

`OfficialSeedTest` pins this by asserting the composition of the production seeder chain:
it must call every official seeder and must not call any demonstration seeder. The check
reads the seeder source rather than seeding a database, because the automated suite seeds a
shared demonstration baseline once per process for speed, which is incompatible with a test
that needs a different seed. The freshly seeded official database is verified separately, by
`migrate:fresh --seed` and the counts in this report.

## Validation

- `methodology:validate` — every entity reachable, every reference resolves, no orphans.
- Full PostgreSQL suite — 593 tests, 593 passed.
- Fresh `migrate:fresh --seed` — clean official state, verified by the counts above.

## Remaining gaps and post-seed backlog

- **Clinical review (PS-1).** The catalogue is curated from recognised international practice — WHO SARA, SPA, HHFA, WASH FIT, IPC minimum requirements, the health system building blocks — but has not been reviewed against source documents by a health methodologist. This should happen before it is presented to customers as authoritative.
- **Half-scope depth.** Programme and clinical subjects carry a solid foundational library, not comprehensive coverage. Named departments above have no framework yet.
- **Lens preconditions, baseline-to-endline link, agreed-actions entity** — PS-2 to PS-4, unchanged, belong to the lens-driven reporting phase.
- **Remaining PHSAI subject depth** — available for a later governed pass.
