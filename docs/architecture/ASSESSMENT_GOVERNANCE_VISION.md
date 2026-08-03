# Vytte Assessment Governance Vision

Date: 2026-08-03

## Status

Approved product direction and implementation contract.

This document defines the target architecture for question authoring, assessment publication, scoring, comparison, reporting, and AI assistance. It extends the existing catalogue-composition architecture. It does not rewrite historical assessments, snapshots, scores, or reports.

## Product Position

Vytte is a governed assessment operating system.

Vytte is not the universal clinical authority and is not an unrestricted form builder. Publishers remain accountable for the purpose, content, methodology, and scoring of their instruments. Vytte is authoritative for the integrity of the process: identity, provenance, review state, versioning, validation, publication, reproducibility, comparison eligibility, audit, and secure delivery.

The platform must make a simple assessment easy to create while progressively revealing expert controls when they are needed.

## Constitutional Rules

1. Content origin does not determine scoring eligibility.
2. A question may contribute to an instrument's primary score when its publisher deliberately includes it in a complete, validated, immutable scoring model.
3. Question meaning and scoring interpretation are separately versioned.
4. One report may contain several clearly named measurements; it must never merge unlike constructs into a misleading number.
5. Comparison is permitted only when a compatibility rule says it is valid.
6. Published instrument versions, scoring models, assessment snapshots, completed scores, and final report snapshots are immutable.
7. Missing, unknown, declined, not observed, and not applicable are data states, not zero scores.
8. AI may draft, inspect, simulate, explain, and recommend. A human remains accountable for approval and publication.
9. Local content is never silently promoted into a shared catalogue.
10. Existing assessments continue to render and score from their frozen legacy contracts.

## Authority and Trust

### Publishers

Every governed instrument has an accountable publisher. A publisher may be:

- Vytte;
- a standards or regulatory organization;
- a government body;
- a hospital or health system;
- an NGO or programme;
- a professional or research organization;
- a verified expert;
- a customer workspace.

The publisher owns the instrument's intended use and methodology. Vytte verifies what was reviewed and records what was not reviewed.

### Distribution levels

Content may be:

1. private to a workspace;
2. published for one organization;
3. shared with selected partners;
4. publicly discoverable;
5. published by a verified publisher;
6. curated by Vytte under a declared review policy.

### Independent trust signals

One vague “verified” badge is insufficient. Vytte records independent signals for:

- publisher identity;
- source and licence;
- subject-matter review;
- methodology review;
- scoring review;
- field testing;
- translation review;
- benchmark approval;
- last review date.

“Vytte curated” means the stated governance checks were completed. It does not mean Vytte originated or owns the underlying health guidance.

## Governed Content Model

### Publisher identity

A publisher has a stable identity, type, visibility, verification state, attribution, contact, and optional owning workspace. Platform administration controls verification. A workspace controls only its own publisher profile and private or organization content.

### Instrument identity and immutable version

The current framework and catalogue models remain the physical authority while the product language evolves toward “instrument”. An instrument version freezes:

- intended purpose and scope;
- target setting, subject, population, and respondent;
- sections, instructions, and question placements;
- exact question versions and response scales;
- branching, repetition, applicability, and validation rules;
- evidence expectations;
- exact scoring-model version;
- report definition;
- provenance, licence, translations, and review state;
- comparison signature and content hash.

Comprehensive Health Assessment remains composition across applicable governed frameworks. Focused Health Assessment remains exactly one governed topic, programme, domain, or intervention.

### Question identity and immutable version

The existing stable question identity and immutable question-version model remains. A question version defines semantics:

- canonical wording;
- construct and intended interpretation;
- recall or observation period;
- response type;
- answer option codes and labels;
- validation constraints;
- applicability and respondent guidance;
- evidence expectation;
- source, licence, methodology, and translations.

Option labels must not be treated as universally scored values. A scoring model interprets the answer in an instrument context.

### Sections and groups

The existing framework section is extended rather than replaced. It can carry:

- name and purpose;
- respondent-facing instructions;
- respondent role;
- estimated completion time;
- applicability and visibility rules;
- scoring-domain context;
- repeatability;
- nested display groups where required.

### Response-scale version

Reusable scales such as yes/no and standard Likert scales may be independently versioned. A question version pins an exact scale version. A question may also own a private option set when its answers are unique.

### Scoring-model version

Scoring is a first-class immutable contract separate from question semantics. A scoring-model version contains:

- item scoring rules;
- numeric bands;
- multi-select calculation rules;
- item and domain weights;
- score direction;
- missing and not-applicable denominator rules;
- critical-failure rules;
- thresholds and maturity bands;
- aggregation method;
- calibration status;
- scoring algorithm version.

Each placement pins an item rule from the instrument's scoring model. A question can therefore be unscored in one instrument, weighted in another, and critical in a third without changing its meaning.

### Report-definition version

A report definition declares the measurements, sections, disclosures, and comparison statements that will appear in the final report. Reports are rendered from the immutable final report snapshot, not reconstructed from mutable master content.

## Response Contract

The target response engine supports:

- boolean and yes/no;
- single select;
- multi-select with minimum, maximum, exclusive options, and “other” text;
- Likert and rating scales;
- integer, decimal, and quantity with unit;
- short and long text;
- date, time, and date-time;
- ranked choices;
- repeating groups;
- display-only instructions;
- computed indicators;
- optional inline evidence attachments and notes.

No response type becomes publishable until authenticated and public renderers, validation, storage, completeness, snapshot, report rendering, and scoring or explicit unscored behavior are implemented and tested.

### Response states

The authoritative response contract distinguishes an answered value from:

- `NOT_APPLICABLE`;
- `UNKNOWN`;
- `NOT_ASSESSED`;
- `NOT_OBSERVED`;
- `DECLINED`;
- `MISSING`.

The scoring model explicitly declares how each state affects completeness and denominators. None becomes zero implicitly.

### Branching and repetition

Branching is deterministic, versioned, and expressed through supported operators. Arbitrary executable scripts are prohibited. Publication simulation detects cycles, unreachable required items, invalid references, hidden answered items, and scoring denominator failures.

Completed responses contain only items that were legitimately enabled under the frozen instrument rules.

## Scoring Architecture

### One scoring pipeline

Every scored item, regardless of origin, follows:

`raw answer -> response state -> item rule -> normalized result -> sub-index -> domain -> primary score`

Canonical calibrated scores remain 0–100. Uncalibrated values remain `null` with an explicit calibration state.

### Required declarations

Every reported metric declares:

- construct, such as readiness, compliance, need, experience, capacity, or prevalence;
- direction, such as higher-is-better or higher-is-more-need;
- source items;
- formula and weights;
- missing and not-applicable policy;
- calibration state;
- threshold or critical behavior;
- comparison eligibility.

### Score views in one report

The unified report may contain:

- **Primary instrument score:** the publisher-defined result of the exact instrument, including governed local or partner questions where the scoring model includes them;
- **Domain and sub-index scores:** interpretable components of the primary score;
- **Common-core score:** a benchmark-eligible result from approved anchor questions;
- **Context and need indicators:** measures that must not be represented as performance;
- **Qualitative findings:** unscored text and observations;
- **Critical findings and evidence:** safety or governance signals requiring attention.

There is no “Vytte question score” versus “user question score” architecture. Content origin is disclosed separately from score purpose.

### Multi-select scoring

Scored multi-select uses a controlled versioned method, such as weighted coverage, all-or-none, or threshold scoring. The model defines maximum selections, exclusive options, partial credit, and score caps. Arbitrary formulas are not accepted.

### Comparability signature

Every published instrument and assessment snapshot carries a signature derived from:

- instrument and question versions;
- scoring-model version;
- required and optional composition;
- setting, subject, and intended population;
- respondent and aggregation method;
- approved translation where relevant.

Vytte labels comparisons as directly comparable, common-core comparable, approved-crosswalk comparable, descriptively related, or not comparable. Equal 0–100 values alone never establish comparability.

## Unified Report

One immutable report contains:

1. instrument, publisher, version, purpose, and provenance;
2. primary score and calibration statement;
3. domain and sub-index results;
4. common-core benchmark where eligible;
5. context and need indicators;
6. qualitative findings;
7. critical findings and evidence;
8. data-quality and comparison limitations;
9. recommendations linked to exact findings;
10. actions and follow-up where enabled;
11. scoring and methodology disclosure.

AI-generated narrative must cite exact frozen findings, responses, or evidence. It cannot introduce unsupported conclusions.

The named lenses are quick starting points, not a fixed limit. Users may tailor the report's focus, measurement area, and level of detail. Vytte keeps the underlying score, exact issues, evidence, critical findings, limitations, and comparison rules immutable. Visuals are purposeful rather than decorative: the score gauge shows level, the domain profile shows balance, the risk strip shows exposure, the performance-stage ladder explains the score's action posture, and the trend line shows change.

## Governance Lifecycle

The full lifecycle is:

`Draft -> Technical validation -> Source/licence review -> Subject review -> Methodology/scoring review -> Pilot -> Approval -> Publication -> Monitoring -> Supersession/withdrawal`

Private organization content may use a lighter declared review policy. Public, curated, and benchmark-approved content requires progressively stronger review. Publication never implies checks that were not performed.

### Contribution and promotion

A workspace may submit a local question or instrument for broader reuse. Vytte then checks ownership and licence, detects possible duplicates, records review, and creates a separately governed published version with attribution. The local original is never silently rewritten or synchronized.

## AI Assistance Boundary

AI may:

- extract structured content from source documents;
- propose sections, question wording, response types, missing states, and branching;
- detect duplicates, ambiguity, leading wording, double-barrelled questions, and reading-level risks;
- suggest terminology mappings and draft translations;
- identify source, licence, scoring, and governance omissions;
- simulate branching, completeness, scoring, and extreme cases;
- analyse field-test non-response, discrimination, drift, and completion time;
- draft report explanations and recommendations grounded in frozen findings.

AI may not approve or publish, invent provenance, silently alter historical data, diagnose patients, or create arbitrary live scored questions. Adaptive delivery may select only from an approved versioned item pool under a frozen selection policy.

## Guided User Experience

### Instrument author workflow

The simple authoring path is a labelled wizard:

1. **Purpose** — name, intended use, setting, subject, population, and respondent.
2. **Publisher and source** — accountable publisher, source, licence, and attribution.
3. **Structure** — named sections, instructions, order, and applicability.
4. **Questions** — write or reuse questions and select response formats.
5. **Logic** — requiredness, missing states, branching, repetition, and evidence.
6. **Scoring** — metric purpose, item rules, weights, domains, critical rules, and common-core eligibility.
7. **Review** — technical, source, expert, methodology, translation, and pilot checks.
8. **Test** — respondent preview, all-path simulation, scoring extremes, and report preview.
9. **Publish** — confirm immutable version, visibility, limitations, and effective date.

Basic authors see plain-language choices and safe defaults. Advanced controls are progressively disclosed.

### Assessment-use workflow

1. Choose Comprehensive or Focused Assessment.
2. Choose the setting-appropriate governed instrument.
3. Review included areas and any permitted exclusions.
4. Add approved local extensions before the run if required.
5. Review how the assessment will score and whether it remains benchmark eligible.
6. Choose self-completion or governed respondent collection.
7. Complete, review, and finalize.
8. Read one unified report with clear score purposes and limitations.

## Safe Implementation Sequence

### Module 1 — Governance constitution and publisher foundation

- Record this vision and superseding decisions.
- Add publisher identities, distribution levels, and independent trust signals.
- Attach publishers compatibly to governed questions, frameworks, and catalogue releases.
- Preserve null publisher fields for legacy content and backfill Vytte as the legacy publisher.

### Module 2 — Scoring-model separation and comparison foundation

- Add immutable scoring-model versions and item rules.
- Generate legacy scoring models that reproduce current published payloads exactly.
- Pin scoring-model identifiers and comparison signatures into new snapshots and reports.
- Keep old snapshots on the existing scoring path.

### Module 3 — Typed responses and first-class missing states

- Introduce the typed response envelope and state contract.
- Preserve existing scalar response columns during migration.
- Implement response types one complete vertical slice at a time, beginning with multi-select.

### Module 4 — Structure, logic, and simulation

- Extend sections with instructions and applicability.
- Add deterministic enablement rules and repeatable groups.
- Add publication and respondent-preview simulation.

### Module 5 — Guided governance studio

- **Status: implemented.**
- Replace disconnected administrative screens with the nine labelled authoring steps.
- Add review assignments, review evidence, approvals, contribution requests, and publisher views.
- Preserve Advanced Tools for expert inspection.

The Platform Admin navigation names this surface **Governance Studio**. Every step has a real destination rather than a decorative progress label. Structure and Questions are distinct authoring screens; Scoring exposes the immutable model declarations and item rules; Review, Test, and Publish are separate decisions in that order.

Each trust signal follows an audited `Assigned -> Submitted -> Approved / Changes requested` review. The assigned reviewer supplies evidence and a recommendation; a different Platform Admin records the decision. Direct self-attestation cannot create a passed claim.

### Module 6 — Unified score views and reporting

- **Status: implemented.**
- Compose primary, domain, common-core, context, qualitative, and critical results into one report snapshot.
- Add comparison eligibility and methodology disclosure everywhere the result is shown or exported.

### Module 7 — Governed AI assistance and ecosystem

- **Status: partially implemented.** Source-grounded advisory lint is live; imports, interoperability, field-test analytics, and adaptive pools remain later bounded modules.
- Add source-grounded drafting, linting, simulation, translation review, and report assistance.
- Add signed import/export packages and health interoperability mappings.
- Add field-test analytics and approved adaptive item pools only after stable response data exists.

## Migration and Release Safety

- Extend before replacing and dual-read where contracts change.
- Never mutate an existing published payload, assessment snapshot, score, or report snapshot.
- Backfill new identifiers deterministically and verify hashes.
- Keep legacy scorers and renderers available for historical contracts.
- Add every response type through the full publication contract.
- Run focused tests during each module and one full sequential PostgreSQL suite before release.
- Commit and push each completed module separately.
- Deploy only after migrations, production build, full suite, backup, maintenance plan, and rollback checks pass.

## Definition of Done

This vision is complete when a verified or workspace publisher can create a governed instrument through the labelled workflow, use reusable or original questions, define one immutable scoring model independent of origin, validate every path, publish under a declared trust level, run it safely, and receive one reproducible report that distinguishes primary performance, benchmarkable common core, context, qualitative findings, evidence, and limitations.
