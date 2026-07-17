# Architecture Remediation Progress

## Module 10 — Immutable final reports and safe comparisons

**Status:** Complete

**Resolved:**

- Completion, scoring, and final report capture now occur in one database transaction.
- Each newly completed assessment receives one immutable structured report snapshot, schema version, and SHA-256 content hash.
- Final payloads freeze title, every included area, target/project identity, assessor/completion metadata, score/maturity, scoring version, and domain/sub-index labels and values.
- Results, PDF, shared report, and score history consume frozen report values.
- CSV exports list all included modules instead of only the first.
- Template history uses exact composition hashes; comparisons reject different compositions. Legacy comparisons require the exact same sorted module IDs.
- Legacy completed assessments remain readable through a best-effort compatibility builder and are not silently rewritten.

**Verification:**

- Focused results/export/progress/completion suites: passed.
- Clean temporary SQLite install, complete migrations, and full database seed: passed.
- Full regression suite: passed.
- PostgreSQL parity: pending restoration of Docker/PostgreSQL.

## Module 9 — Frozen published content and scoring profiles

**Status:** Complete

**Resolved:**

- Published versions now store the exact content payload represented by their SHA-256 hash.
- Assessment creation reads that stored payload; later catalogue edits or template-pivot edits cannot change a published version.
- Snapshot payloads include consent applicability and the full scoring profile: sub-index identity, domain identity, question membership/weights, and option weights.
- Authenticated and public runners validate template responses and consent against the frozen snapshot contract.
- Template assessment scoring reads frozen option weights and sub-index links and records algorithm `vytte-3.0-snapshot-profile`.
- Legacy assessments retain a clearly separated live-profile compatibility path and are not silently recalculated.
- Publishing rejects empty areas, unsupported response types, scored open text, missing option weights, and scored questions without sub-index mappings.
- Incomplete school/facility sample content cannot weaken these rules; it must be curated and reseeded as a valid new template.

**Verification:**

- Focused template/snapshot/scoring suite: 25 tests, 77 assertions, all passed.
- Clean temporary SQLite install, complete migrations, and full database seed: passed.
- Full regression suite: passed.
- PostgreSQL parity: pending restoration of Docker/PostgreSQL.

## Module 8 — Durable full-scope public responses

**Status:** Complete

**Resolved:**

- AG-24: public respondents now receive every in-scope assessment module rather than only the first.
- Template-created public assessments read immutable snapshot questions, translations, response types, and options.
- AG-26: public participation has a durable response-session row with start, activity, locale, and submission timestamps.
- Public responses and consent records reference that session through database foreign keys; legacy respondent UUID values remain only as a compatibility/cohort marker.
- Token records now capture creator, revocation, usage count, and last-used time.
- Authorized workspace members can revoke a link without deleting its submitted responses.
- Consent is recorded for every included module that requires it.
- Submission rechecks required stored responses on the server and survives browser/component remounts.
- Public answers remain a deliberately separate voice cohort and are not silently blended into staff/assessor scores.

**Verification:**

- Focused public-runner suite: 20 tests, 44 assertions, all passed.
- Clean temporary SQLite install, complete migrations, and full database seed: passed.
- Full regression suite: passed.
- PostgreSQL parity: pending restoration of Docker/PostgreSQL.

## Approved scope

Isaac approved correction of all Phase 21/22 gaps on 17 July 2026. Work proceeds in bounded modules, each tested, committed, and pushed separately.

## Module 1 — Assessment security and response integrity

**Status:** Complete

**Resolved:**

- AG-18: creation rejects inactive or target-incompatible modules.
- AG-22: a partial unique database index enforces one staff response per assessment/question while retaining respondent-specific uniqueness.
- AG-23: assessment identity is locked in Livewire, workspace access is enforced on mount and mutation, and question/option scope is validated.
- Public runner state identifiers are locked and every mutation revalidates the token/assessment context.
- Public and authenticated runners reject an option belonging to another question.

**Migration safety:**

`2026_07_17_000001_enforce_unique_staff_responses.php` refuses to create the index if historical duplicates exist. It does not silently delete or merge responses. Local SQLite contained no duplicates and migrated successfully.

**Verification:**

- Focused security suite: 37 tests, 81 assertions, all passed.
- Full suite: 320 tests, 750 assertions, all passed in 54.161 seconds.
- Local SQLite migration: passed.
- PostgreSQL parity: pending restoration of Docker/PostgreSQL.

## Module 7 — Two-path creation workflow

**Status:** Complete

**Resolved:**

- Assessment creation now presents exactly two choices: Comprehensive Health Assessment and Focused Health Assessment.
- The legacy standard-battery/module-picker controller workflow is removed.
- Focused creation shows one approved health-domain template and starts it directly without unrelated modules or checkboxes.
- Comprehensive creation only shows published frameworks matching the project's setting and permits removal of non-applicable areas with reasons.
- Department terminology appears only when the setting's taxonomy or explicit custom-setting choice says departments genuinely apply.
- Project creation supports correctional facilities, workplaces/businesses, places of worship, NGOs/programmes, government organizations, and user-defined custom settings.
- Category IDs are now validated against the selected setting type.
- AG-15: the default database seed no longer depends on a personal Downloads document. The external PHSAI and sample school seeders were removed.
- Only repository-contained content that passes template publishing validation is exposed; the initial published focused template is HIV Awareness and Service Uptake.

**Verification:**

- Focused creation/project/snapshot/template suite: 41 tests, 123 assertions, all passed.
- Clean temporary SQLite install, complete migrations, and full database seed: passed.
- Full suite: 342 tests, 811 assertions, all passed in 52.790 seconds.
- No local working database was erased during clean-install verification.
- PostgreSQL parity: pending restoration of Docker/PostgreSQL.

## Module 6 — Assessment-owned content snapshots

**Status:** Complete

**Resolved:**

- New template-based assessments store creation path, template version, composition hash, and an assessment-owned immutable payload.
- Both creation paths converge on one transactional `AssessmentCreationService` that creates assessment, module scope, exclusions, and snapshot atomically.
- Focused creation resolves its one template scope directly; it accepts no unrelated module checklist.
- Comprehensive creation accepts a validated subset of its framework and records reasons for excluded standard areas.
- Comprehensive templates must match the project's setting; focused health domains remain independent of setting.
- Snapshot payload freezes modules, questions, translations, response types, sections, options, ordering, observation flags, and score weights.
- Template-based authenticated runners read frozen snapshot content rather than mutable catalogue text.
- AG-43 is resolved for new template-based assessment presentation: later master edits no longer alter the assessment runner.

**Verification:**

- Focused snapshot/template/runner suite: 25 tests, 64 assertions, all passed.
- Full suite: 340 tests, 800 assertions, all passed in 49.808 seconds.
- Local SQLite migration: passed.
- PostgreSQL parity: pending restoration of Docker/PostgreSQL.

## Module 5 — Immutable template publishing and open-text responses

**Status:** Complete

**Resolved:**

- Published templates now have immutable version records and versioned module composition.
- Focused templates require exactly one health domain and one direct assessment scope; grouped modules are rejected.
- Comprehensive templates require an explicit setting context.
- Publishing requires source authority and license metadata.
- Publishing creates a SHA-256 content hash from exact module, question, response-type, option, ordering, and scoring content.
- AG-21: the initial runnable response contract is enforced at publish time. Scalar options, Likert choices, and true open-ended text are supported; unsupported types cannot publish.
- Authenticated and public runners now autosave genuine open-ended text responses, while legacy option-based `OPEN_ENDED` content remains compatible.
- Server-side completion recognizes both valid option responses and required open-text responses.

**Verification:**

- Focused template/runner suite: 36 tests, 80 assertions, all passed.
- Full suite: 336 tests, 789 assertions, all passed in 73.818 seconds.
- Local SQLite migration: passed.
- PostgreSQL parity: pending restoration of Docker/PostgreSQL.

## Module 4 — Health taxonomy and setting contexts

**Status:** Complete

**Resolved:**

- AG-13: health domains are now explicit and separate from global operational scoring domains and module-local questionnaire sections.
- Settings are controlled contexts: health facility, school, community, correctional facility, workplace/business, place of worship, NGO/programme, government organization, water point, and custom.
- Department terminology is data-driven. Only health facilities default to `uses_departments = true`; targets can explicitly override it.
- Custom settings preserve the user's label without creating ad hoc tables or schema.
- Existing target types and assessment modules are preserved through mapping tables.
- Modules can map to multiple health domains, such as the existing combined HIV/TB module.

**Verification:**

- Focused taxonomy/project suite: 18 tests, 59 assertions, all passed.
- Full suite: 329 tests, 776 assertions, all passed in 51.545 seconds.
- Local SQLite migration and idempotent reference seeding: passed.
- PostgreSQL parity: pending restoration of Docker/PostgreSQL.

## Module 2 — Completion and payment-webhook safeguards

**Status:** Complete

**Resolved:**

- AG-20: server submission now requires every active, scored, option-based question in the assessment scope to have a valid staff response.
- AG-41: Flutterwave is explicitly exempted from CSRF, matching Paystack, while controller-level secret-hash verification remains mandatory.
- Unanswered submission retains `IN_PROGRESS` and returns the assessor to the runner with a plain-language message.
- Flutterwave has regression coverage for invalid signatures and successful plan upgrades.

**Verification:**

- Focused completion/scoring/billing suite: 43 tests, 117 assertions, all passed.
- Notification regression suite: 13 tests, 25 assertions, all passed after replacing legacy unanswered fixtures with valid completed responses.
- Full suite: 323 tests, 757 assertions, all passed in 62.140 seconds.

## Module 3 — Versioned canonical scoring

**Status:** Complete

**Resolved:**

- AG-28: scoring output is canonical 0–100. Questions whose complete option scale is 0–1 are normalized during calculation without rewriting source content.
- Every sub-index, domain, and assessment score now records `scoring_version`.
- Existing score rows receive `legacy-v1`; newly calculated rows use `vytte-2.0-normalized`.
- Multi-module calculation includes sub-indices from every in-scope module and records active/expected module counts.
- Completed assessments are not recalculated automatically, preserving historical results.

**Verification:**

- Exact 0–1 normalization fixture: passed with canonical score 100.
- Two-module aggregation fixture: passed with independently expected score and partial-calibration state.
- Focused scoring suite: 14 tests, 52 assertions, all passed.
- Full suite: 325 tests, 765 assertions, all passed in 67.210 seconds.
- Local SQLite migration: passed.
- PostgreSQL parity: pending restoration of Docker/PostgreSQL.
