# Official Master Seed — Build Report

The canonical production knowledge base that ships with Vytte. Built stage by stage,
each stage published through the governed lifecycle and verified against PostgreSQL.

## Headline

A fresh database seeded from `DatabaseSeeder` is production-ready and contains **no
demonstration content**. A beta customer signing in finds a comprehensive assessment for
every supported health-facility profile and thirteen focused subjects.

| Entity | Count |
| --- | --- |
| Official questions | 388 |
| Published question versions | 388 |
| Published official frameworks | 57 |
| Catalogue releases | 41 total; 36 currently published |
| Health-facility profiles with a comprehensive release | 23 of 23 |
| Departments | 46 |
| Measurement domains | 8 (published taxonomy) |
| Methodology | Published |

The original 238-question cross-cutting library is extended by 150 human-approved,
source-informed questions for 25 service departments. Emergency Care reuses eight
existing questions and Finance reuses ten, avoiding duplicate identities.

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
- **Reusable department frameworks:** 42 content-ready departments, including family
  planning, labour and delivery, postnatal care, outpatient, inpatient, referral, theatre,
  imaging, blood bank, critical care, NCD, rehabilitation and disability inclusion.

The question count a customer sees exceeds the count authored, because frameworks reuse the
cross-cutting core. Hospital Readiness presents 134 questions built almost entirely from the
136-question spine authored once.

### Catalogue releases

There are 41 immutable historical releases: 23 currently published comprehensive releases,
13 published focused releases, and five superseded comprehensive predecessors. General
Hospital V3 has 33 departments and 334 available questions; PHC V3 has 25 and 280; Clinic
V2 has 16 and 218. All aggregate through `MEAN_OF_SCORED_SUB_INDICES` with governed
critical-failure handling.

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
- Full PostgreSQL suite — 692 tests, 692 passed (2,423 assertions).
- Fresh `migrate:fresh --seed` — clean official state, verified by the counts above.

## Remaining gaps and post-seed backlog

- **National adaptation.** The V1 minimum-readiness package is source-informed and
  product-owner approved. Country-specific regulation or clinical review should create
  governed successor versions where a jurisdiction requires stronger or narrower wording.
- **Depth.** Departments carry a concise minimum-readiness set. Deeper specialty content
  can be added later through immutable successor versions.
- **Lens preconditions, baseline-to-endline link, agreed-actions entity** — PS-2 to PS-4, unchanged, belong to the lens-driven reporting phase.
- **Remaining PHSAI subject depth** — available for a later governed pass.
