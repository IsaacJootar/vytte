# Architecture Gaps

## Current Status

The former assessment-creation discrepancy has been resolved by the platform-governed composition architecture.

Current verified local state:

- `php artisan test`: 395 tests, 972 assertions passing.
- Disposable PostgreSQL `migrate:fresh --seed`: passing.
- Production frontend build: passing.
- Commit `44f0186` implements the governed composition model.

## Resolved Architecture Decisions

| Area | Current resolution |
|---|---|
| Assessment creation | Exactly two paths: Comprehensive Health Assessment and Focused Health Assessment |
| Comprehensive assessment | Composition orchestrator over published catalogue releases |
| Focused assessment | Single health domain/programme/topic/intervention |
| Official content authority | Vytte owns departments, framework versions, facility profiles, catalogue releases, scoring, and aggregation |
| Facility applicability | Facility profiles define required/default/optional departments |
| Version resolution | Published catalogue releases pin exact framework versions; no automatic latest lookup |
| Runtime content | Assessment snapshots freeze payload, manifest, policy, collection config, and hashes |
| Reports | One immutable report snapshot and one reporting architecture |
| Community/patient feedback | Normal assessment content, not a separate subsystem |
| Evidence | Optional response-bound support, not a separate workflow |
| Local customization | Local custom sections are context only and excluded from official scoring |

## Remaining Gaps

| ID | Gap | Risk | Recommended next action |
|---|---|---|---|
| GAP-01 | Deep dependency-graph and comparison views are not implemented | Platform Admins can inspect/publish content, but advanced impact review is still manual | Add dependency graph and version comparison views after core admin workflows stabilize |
| GAP-02 | Production clinical content is not curated | Demo data proves architecture only | Create governed source/licence/scoring review workflow for real content |
| GAP-03 | Facility profile editing after project creation is not exposed | Incorrect profile choice requires manual correction | Add a profile-change flow with clear effect on future assessments only |
| GAP-04 | Additional response types are declared but unavailable | Publishing unsupported types would break runners | Implement each type only with full contract and tests |
| GAP-05 | Recommendation/action planning remains inactive | Reports currently summarize scores rather than action workflows | Design only after the assessment/report core is stable |
| GAP-06 | Generic external API is not implemented | Unapproved API surface can increase security burden | Add only for an approved consumer and versioned contract |

## Rule

Future work must extend the governed assessment engine. Do not introduce parallel assessment, scoring, aggregation, or reporting systems.
