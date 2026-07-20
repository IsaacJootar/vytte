# Technical Debt Report

## Summary

The codebase is organized and the core architecture is coherent, but production debt remains around incomplete operational controls, inactive schema, and incomplete UI coverage for backend-supported capabilities.

## Findings

| Severity | Debt | Recommendation |
| --- | --- | --- |
| CRITICAL | Paid-plan content entitlement is missing. | Add plan/module/template/catalogue binding tables, UI, and enforcement. |
| HIGH | Catalogue composition is not fully self-service. | Add release composition UI and tests. |
| HIGH | Workspace custom assessments are backend-supported but not fully user-facing. | Complete UI/lifecycle or remove production claim. |
| HIGH | Inactive/reserved tables remain in schema. | Remove or justify non-authoritative tables before production. |
| MEDIUM | Backup and incident runbooks are still absent. Monitoring, queue and mail are now visible in Platform Health, but there is no documented recovery procedure. | Write backup, restore and incident runbooks before production. |
| MEDIUM | Payment architecture is partial. | Add billing ledger, idempotency, subscriptions, invoices, and reconciliation before paid production. |
| MEDIUM | Public route throttles need explicit policies. | Add route-specific throttles. |
| MEDIUM | Evidence remains text-only. | Keep as text support or implement secure file lifecycle. |
| FUTURE ENHANCEMENT | Dependency graph and version comparison are absent. | Build after production blockers are resolved. |

## Resolved

| Debt | Resolution |
| --- | --- |
| Assessment snapshot immutability was convention only. | Enforced by a model guard rejecting updates, with tests. See DEC-2026-07-19-012. |
| Governance dependency counting double-counted snapshots and scanned every snapshot in PHP. | Counting is correct and runs in the database. |
| The question-version editor silently discarded `critical_failure` and `option_key` on save. | Stored values are carried forward. An author-facing control for `critical_failure` does not yet exist. |
| `SUPPORTED_ALGORITHM_VERSIONS` advertised a scoring version with no implementation. | Removed. |
| The demo dataset silently seeded nothing after the plan rename, so a fresh install had no demo assessments, scores, or reports. | Seeder identifiers aligned; a missing demo account now warns instead of failing silently. |
| The full sequential test suite had never been run. | It runs and passes. Earlier green claims came from batched runs. |
| Official framework editing was not self-service. | The Assessment Builder (B1–B6) covers sections, questions, scoring, evidence, review, publication and versioning through governed flows. Advanced Tools retains the raw governance views. |
| Workspace `SUSPENDED` was recorded but never enforced. Members of a suspended workspace kept full access. | `EnsureWorkspaceIsActive` blocks use; `SessionRevocationService` ends existing sessions. See DEC-2026-07-19-020. |
| Platform Admin landed in a workspace on sign-in, contradicting DEC-009. | Sign-in routes to `/admin`; the workspace footer access-level card is removed. See DEC-2026-07-19-016. |
| Platform Admin had drifted from the workspace design and was not mobile-first. | One shell for every role; only the navigation array differs. See DEC-2026-07-19-017. |
| Flash messages were hand-rendered per page, so pages that omitted them reported nothing after an action. | Rendered once in the layout; 25 duplicated per-page banners removed. `limit_error`, `info` and `warning` were being flashed but never displayed anywhere; they now surface. |
| Form inputs had no visible borders because `@tailwindcss/forms` is not installed and preflight sets `border-width: 0`. | Corrected at the base layer. |
| Invitations, respondent links and report share links were flashed once and lost on the next page load. Respondent links were never listed at all. | All three are listed persistently with copy and WhatsApp. See DEC-2026-07-19-022. |
| A suspended person learned of it only by failing to sign in. | Notified in-app immediately, and by email when platform email is enabled. See DEC-2026-07-19-023. |
| The plans screen set limits but showed no usage against them. | Per-plan allocation, limits in force, and a panel flagging any workspace at or past 80% of a limit. |
| The assessment oversight screen exposed raw identifiers, lifecycle codes and snapshot presence. | Rebuilt in plain language and pinned by test. See DEC-2026-07-19-019. |

## Outstanding, added since the original report

| Severity | Debt | Recommendation |
| --- | --- | --- |
| MEDIUM | `PlatformSettingController` renders and persists `plan.free_projects`, `plan.free_assessments_per_project`, and `plan.pro_projects`, which `PlanService` no longer reads. The controls have no effect. | Decide whether to remove the controls or restore the behavior. Recorded as known drift under DEC-2026-07-18-010. |
| MEDIUM | `domain_scores` has no `respondent_type`, while `sub_index_scores` does. Both staff and aggregate runs upsert into one keyspace. | Latent rather than live. Resolve in a hardening pass. |
| MEDIUM | Nothing prevents re-scoring a `COMPLETE` assessment, though the scoring contract states results are never silently recalculated. | Enforce the contract or amend it. |
| MEDIUM | Multi-respondent aggregation copies domain metadata, including `questions_answered`, from the first contributing session. | Fix before any content enables multi-respondent collection. |
| LOW | Assessment snapshot deletion is not guarded, unlike report snapshots. | Proposed follow-up; requires updating shared test fixtures. |
| MEDIUM | Session revocation depends on `SESSION_DRIVER=database`. Changing the driver silently removes the protection that suspension takes effect immediately. | The service reports doing nothing rather than pretending, and the limit is documented in `ADMIN_SECURITY_AND_GOVERNANCE.md`. Revisit if the driver ever changes. |
| MEDIUM | Workspace invitations can be created, cancelled and re-issued, but there is no way to change an invited person's role without cancelling and re-inviting. | Add role editing on a pending invite, or accept as a beta limitation. |
| LOW | `.env.example` ships `MAIL_MAILER=log`, so a fresh deployment writes emails to a log file rather than sending them. Platform Health flags it. | Set real values at deploy time. |
| LOW | The public assessment runner has no evidence-capture support, so a published assessment requiring evidence cannot collect it through the public path. | Pinned by a test asserting builder publications are single-respondent. Resolve before evidence-bearing content is published publicly. |

## Codebase scan notes

- Active source is clear of old Platform Admin role terminology.
- Active source is clear of old structural question-group table/model naming.
- The cleanup report intentionally names removed legacy artifacts.
- Route list contains no first-party API endpoints yet.
- Laravel placeholder text in framework config is not app debt, but production env values must be real.

## Added in P4

| Severity | Debt | Recommendation |
| --- | --- | --- |
| MEDIUM | The methodology catalogue is curated from established international practice but has not been reviewed against source documents by a health methodologist. It is broad and defensible, not authoritative. Now covers 36 health domains, 147 areas, 29 objectives, 20 lenses, 21 insight categories and 40 templates. | Review before it becomes the official master seed. |
| MEDIUM | Methodology administration is browse, search and publish only. There is no per-entry create or edit. | Acceptable while the catalogue is seeded as a set. Build authoring if it starts being maintained entry by entry. |
| MEDIUM | The Trend and Benchmarking analysis lenses have preconditions — two completed assessments, and a peer set — that nothing yet enforces. A lens with unmet preconditions must say so rather than render empty. | Enforce when lens-driven reporting is built. |
| LOW | Objective recommendations reference health domains, measurement domains and evidence types by code without validation at publication, because those entities have their own lifecycle. A renamed measurement domain would leave a dangling recommendation. | Add a cross-lifecycle check if those taxonomies start changing. |

## Added while resolving the P4 review findings

| Severity | Debt | Recommendation |
| --- | --- | --- |
| RESOLVED | `FIN` is defined in the published taxonomy and Platform Admin has a full version-and-publish workflow. Publication refuses to leave any measurement domain undefined. See DEC-2026-07-20-033. |
| LOW | Objective presets reference health domains and templates by code with no foreign key, matching `objective_recommendations`. A renamed domain code would leave a preset pointing at nothing. | Publication validation checks lens, template and area references but not health domain codes, since those live outside the methodology version. Add a cross-lifecycle check if domain codes start changing. |

## Deferred to after the Official Master Seed

Four items agreed for post-seed resolution. Full detail in `MASTER_SEED_PLAN.md` under Post-seed backlog.

| Ref | Item | Severity |
| --- | --- | --- |
| PS-1 | Methodology catalogue not yet reviewed by a health methodologist against source documents. | MEDIUM |
| PS-2 | Trend and Benchmarking lens preconditions are described in prose and enforced nowhere. | MEDIUM |
| PS-3 | No baseline-to-endline link; Trend infers sequence by date. | MEDIUM |
| PS-4 | No agreed-actions entity, so the Progress Tracking lens has nothing to track against. | MEDIUM |

None blocks seeding. PS-2, PS-3 and PS-4 belong to the lens-driven reporting phase and should be scoped together.
