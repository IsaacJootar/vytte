# Architecture Remediation Progress

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
