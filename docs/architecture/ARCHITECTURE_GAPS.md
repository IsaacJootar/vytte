# Architecture Gaps

## Current Status

The governed assessment engine, official production seed, nine-step Governance Studio, complete
framework/catalogue editors, typed response contract, versioned scoring, unified reporting,
contribution workflow, independent-review workflow, action management, and workspace local-question
formats are implemented.

Release verification is one full sequential PostgreSQL suite plus Pint, Blade compilation, official
seed validation, and a production frontend build. Batched tests are never release evidence.

## Resolved Architecture Decisions

| Area | Current resolution |
|---|---|
| Assessment creation | Exactly two paths: Comprehensive Health Assessment and Focused Health Assessment |
| Comprehensive assessment | Composition orchestrator over published catalogue releases |
| Focused assessment | One governed health domain, programme, topic, or intervention |
| Content accountability | Publishers own content and methodology claims; Vytte governs integrity and declared review evidence |
| Framework authoring | Governance Studio exposes Purpose, Publisher & source, Structure, Questions, Logic, Scoring, Review, Test, and Publish |
| Framework/catalogue lifecycle | Draft creation, editing, publication, supersession, and archival are implemented with immutable published history |
| Response contract | `SINGLE_SELECT`, `MULTI_SELECT`, `LIKERT`, unscored `OPEN_ENDED`, and `NUMERIC`; numeric scoring requires frozen bands |
| Runtime content | Assessment snapshots freeze payload, manifest, policy, collection config, scoring versions, and hashes |
| Reports | One immutable report snapshot supports multiple explicit measurement purposes and tailored presentation lenses |
| Actions | Findings may feed the ordinary action and progress workflow; there is no parallel recommendation system |
| Local customization | Seven local formats are supported; only configured Yes/No and rating formats enter a separate optional local score |
| Production seed | Official source-informed beta library; demo accounts and demo catalogue fixtures are test-only |

## Remaining Gaps

| ID | Gap | Risk | Required treatment |
|---|---|---|---|
| GAP-01 | Dependency graph and visual version comparison are not implemented | Advanced impact review remains partly manual | Add only after the current governance workflow stabilizes |
| GAP-02 | Production has no evidence-backed governance claims or completed independent review assignments | Published content may be mistaken for independently reviewed methodology | Assign real reviewers, collect evidence, and record independent decisions; never seed or fabricate approvals |
| GAP-03 | National and jurisdiction-specific clinical adaptation is incomplete | A source-informed beta library may not satisfy local regulation | Publish governed successor versions with local authority, licence, and clinical review |
| GAP-04 | Facility profile editing after project creation is not exposed | A mistaken profile choice requires support before future runs | Add a guarded change flow affecting only future assessments |
| GAP-05 | Plan-to-content entitlement is not implemented | Paid tiers cannot safely promise different catalogues | Keep one catalogue entitlement during beta or approve a versioned entitlement model before differentiated sale |
| GAP-06 | Workspace custom assessment designs remain design drafts and are not executable instruments | Users could confuse a saved design with a runnable assessment | Keep the UI explicitly labelled or promote designs only through the governed instrument workflow |
| GAP-07 | Date/time, ranked choice, repeatable groups, display-only items, and computed indicators remain vision-level types | Publishing them would break at least one consumer | Implement only as complete vertical slices under the response-type release rule |
| GAP-08 | Generic external API and interoperability packages are not implemented | A premature public surface increases security and compatibility burden | Add only for an approved consumer and versioned contract |
| GAP-09 | Legacy snapshots retain embedded scoring values | Historical contracts cannot be rewritten | Keep dual-read compatibility while all new instruments use immutable scoring-model versions |
| GAP-10 | Operational monitoring and restore drills require ongoing external practice | Code alone cannot prove recovery or incident response | Monitor health, queue failures, backup age, certificate expiry, and perform scheduled restore drills |

## Rule

Future work must extend the governed assessment engine. Do not introduce parallel assessment,
scoring, aggregation, reporting, evidence, respondent, or recommendation systems.
