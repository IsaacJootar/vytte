# Phase 22 Regression and Security Baseline

## Outcome

Phase 22 established a reproducible SQLite baseline and added characterization coverage for the highest-risk assessment seams. It made no application-code, schema, migration, production-data, scoring, payment, or template changes.

The current executable baseline is green on SQLite, but it is **not yet safe to describe as PostgreSQL-verified or template-ready**. The added tests confirm several critical security and compatibility gaps that need explicit decisions before correction.

## Approved baseline boundary

- Repository: `C:\xampp\htdocs\vytte`
- Branch: `master`
- Committed anchor: `c190e419108bb2483febcea755fe30c13b7348fe`
- Boundary: committed anchor plus the preserved pre-existing dirty worktree
- Existing multi-module work: retained only as a comprehensive-assessment prototype
- Phase 22 additions: architecture documentation and one isolated characterization test file
- Commit/push: not performed; the worktree contains user-owned changes that must not be bundled without Isaac choosing the boundary

## Environment

| Item | Value |
|---|---|
| Date | 17 July 2026 |
| PHP | 8.3.31 |
| Laravel | 13.20.0 |
| Test database | SQLite `:memory:` |
| SQLite library | 3.51.3 |
| PDO SQLite | Enabled |
| Production database authority | PostgreSQL |
| PostgreSQL parity run | Blocked by unavailable local Docker service |

## Baseline execution

Command: `php artisan test`

| Suite | Tests | Assertions | Passed | Failed | Skipped | Duration |
|---|---:|---:|---:|---:|---:|---:|
| Existing repository suite before Phase 22 tests | 312 | 736 | 312 | 0 | 0 | 60.476 s |
| Phase 22 characterization file | 6 | 10 | 6 | 0 | 0 | 2.686 s |
| Final combined suite | 318 | 746 | 318 | 0 | 0 | 62.078 s |

The final combined run confirms that the Phase 22 documentation and characterization coverage introduced no regression to the executable SQLite baseline.

## Characterized behavior

| Area | Observed current behavior | Risk | Decision gate |
|---|---|---|---|
| Livewire tenancy | A user from another workspace can directly mount `AssessmentRunner` with an assessment object and receive its question data | Cross-workspace disclosure/write surface | DEC-021-001 / AG-23 |
| Response scope | Authenticated runner accepts an option ID belonging to a different question | Invalid response integrity and scoring | AG-23 |
| Staff response uniqueness | SQLite allows two response rows for the same assessment/question when `respondent_id` is `NULL` | Duplicate/conflicting staff answers; PostgreSQL has the same NULL uniqueness concern | DEC-021-002 / AG-22 |
| Public multi-module run | Public runner selects one in-scope module and ignores the rest | Incomplete comprehensive public assessment | DEC-021-007 / AG-24 |
| Public scoring | Responses with a public respondent UUID do not affect the current assessor score | Collected data remains absent from standard results until respondent-role semantics extend the shared engine | DEC-021-020 / AG-25 |
| Numeric response | Numeric question loads without options or type metadata and has no runner storage action | Assessment cannot be completed | DEC-021-015 / AG-21 |
| Direct completion | Existing suite proves an unanswered assessment can be completed through the submit route | Data quality and misleading reports | DEC-021-006 / AG-20 |
| Flutterwave webhook | Route is under web middleware but absent from the CSRF exemption list | Legitimate production webhook requests may be rejected | DEC-021-018 / AG-41 |

## Coverage already present and preserved

- Auth gates for assessment creation, runners, results, exports, sharing, and respondent-link creation.
- Route-level workspace rejection for assessment runs, results, PDF, CSV, and share-link creation.
- Signed-report validity, expiry, incomplete-assessment rejection, and anonymous access.
- Consent capture for authenticated and public runners.
- Response autosave/update behavior for scalar options.
- Exact scoring fixtures, null/partial/calibrated states, band boundaries, maturity assignment, idempotence, and workspace separation.
- PDF/CSV response headers and CSV column shape.
- Assessment-limit and payment-signature tests for the Paystack path.

## Coverage still required

1. PostgreSQL migration and critical-suite parity, especially nullable uniqueness, constraints, and upserts.
2. Concurrency tests for staff and public response writes.
3. Desired-state authorization tests before hardening Livewire actions.
4. Full response-type contract tests once supported types are approved.
5. Multi-module score aggregation fixtures with independently known expected values.
6. Completed report reproducibility tests proving whether catalogue edits change historical output.
7. A production-like Flutterwave HTTP/CSRF/signature test.
8. Composition-aware progress and comparison tests.

## Safest next actions after approval

1. Correct the Livewire authorization and question/option scope vulnerabilities behind desired-state regression tests.
2. Make response identity explicit so one staff response per assessment/question is enforceable on PostgreSQL and SQLite without rewriting history.
3. Add explicit respondent-role scoring semantics to the shared scoring and reporting engines before expanding external-respondent assessments.
4. Restrict publishable templates to runner-supported response types until renderer/storage strategies exist.
5. Add the smallest Flutterwave CSRF exemption consistent with the already-tested signature validation.
6. Restore Docker/PostgreSQL parity before any template migration is accepted.

## Approval after Phase 22

Isaac approved all pending recommendations and authorized correction of every recorded gap on 17 July 2026. Corrective work must proceed in bounded, tested, separately committed and pushed modules, beginning with security and response integrity before template implementation.
