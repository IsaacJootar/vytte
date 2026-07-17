# Architecture Gaps and Documentation Discrepancies

## Severity

- **Critical**: can break isolation, historical reproducibility, scoring, or core workflow.
- **High**: material compatibility or governance risk.
- **Medium**: incomplete capability or misleading documentation.
- **Low**: limited operational/documentation impact.

## Complete discrepancy ledger found in Phase 21

| ID | Documented or implied behavior | Actual repository behavior | Affected files/tables | Severity | Recommendation | Isaac approval |
|---|---|---|---|---|---|---|
| AG-01 | Repository baseline is 20 clean completed modules and about 290 green tests | Branch is ahead of remote, worktree is dirty, 312 tests are declared, and no current green run is evidenced | Git state, `.phpunit.result.cache`, `tests/` | Critical | Phase 22 must define and verify the baseline before further architecture work | Required |
| AG-02 | `phases.md` is current through Module 20 | Module 20 commit is still TBD; geographic usage, plan features, PHSAI seeding, and current multi-module/school work are missing | `docs/phases.md`, Git log/worktree | High | Reconcile history after deciding whether uncommitted work is accepted | Required |
| AG-03 | `AGENTS.md` current phase is authoritative | It still says Module 01 | `AGENTS.md` | Medium | Update only after Phase 21 approval | Required |
| AG-04 | Detailed module specs exist in `docs/modules/` | Only `01-foundation.md` exists | `docs/modules/` | Medium | Backfill behavioral specs from tests and code during/after Phase 22 | Required for scope |
| AG-05 | README describes Vytte setup and operation | README is unmodified Laravel boilerplate | `README.md` | Medium | Replace with project-specific setup after environment decisions | Required |
| AG-06 | Development and production use PostgreSQL; never SQLite | `.env`, `.env.example`, `phpunit.xml`, and default database config use SQLite; Docker defines PostgreSQL separately | Environment/config files | Critical | Phase 22 must run parity tests and choose the supported developer/test matrix | Required |
| AG-07 | All tenant models use a workspace global scope plus policies | Only Project and Target use the trait; no policies directory exists | Models, trait, scope, controllers | Critical | Lock behavior with attack tests, then strengthen incrementally | Required before implementation |
| AG-08 | Workspace membership/invitation are globally scoped tenant models | They are manually filtered in controllers and carry no global scope | `workspace_members`, `workspace_invitations` | High | Preserve manual checks; consider scoped repositories/policies after tests | Required |
| AG-09 | Project v1 has exactly one target; `project_targets` is deferred | `project_targets` is migrated and used; schema is many-to-many while UI uses first target | Project/Target models, migrations, controllers/views | Critical | Decide whether one-primary-target or multi-target is canonical; do not remove the junction | Required |
| AG-10 | Schema has 42 tables translated 1:1 from documented names | Migrations create 60 tables with many different names/keys/columns | `docs/database.md`, migrations | Critical | Treat migrations as truth and preserve all existing tables; normalize documentation, not schema | Required |
| AG-11 | UUID primary keys everywhere | Many reference tables use integers; some use strings or composite keys | Migrations/models | High | Preserve mixed keys and document them | Required only if normalization proposed |
| AG-12 | Catalogue is `assessment_module_definitions`; assessment join is `assessment_modules` | Catalogue is `assessment_modules`; join is `assessment_module_scope` | Migration/model names | Critical | New template design must use actual names and adapters | Required |
| AG-13 | Global domain, module section/domain, and health-domain terminology is clear | `domains` are scoring dimensions; `module_domains` are questionnaire sections; health domains do not exist as governed taxonomy | Reference/schema/content code | High | Approve a controlled vocabulary that does not rename legacy tables | Required |
| AG-14 | 7 domains, 3 tiers, 23 modules, 120 sub-indices, 528 questions | Seeder/local state has 8 domains, 2 tiers, 27 modules, 4 sub-indices, and 601 questions | Seeders/local DB | Critical | Introduce explicit dataset/version manifests before template publishing | Required |
| AG-15 | Seed process is repository-contained and reproducible | PHSAI seeder depends on a hard-coded Downloads DOCX and silently skips if absent | `PHSAIQuestionsSeeder` | Critical | Vendor an approved/licensed source or generated fixture after licensing approval | Required |
| AG-16 | Published/approved content is stable | Admin edits shared modules, domain labels, questions, active flags, and translations in place | Admin controllers, content tables | Critical | Freeze legacy behavior; template versions must isolate published content from master edits | Required |
| AG-17 | Existing assessment creation is a stable one-module workflow | Current uncommitted worktree implements multi-module selection, exclusions, and `FULL_TARGET`/`MODULE_PICKER` | Assessment controller/runner/views/tests | Critical | Decide whether to accept, revert, or quarantine this work before Phase 22 baseline | Required |
| AG-18 | Module selection is scoped to target applicability | Request validation checks only global module existence; crafted IDs can cross target types | Assessment controller and module tables | Critical | Add compatibility validation only after regression tests/approval | Required to fix |
| AG-19 | Assessment statuses are documented consistently | Runtime uses `COMPLETE`, docs/UI rules use `COMPLETED`; publication has separate state | assessments and views/tests | High | Define state machine before snapshots; preserve existing values | Required |
| AG-20 | Incomplete assessments cannot be submitted | Direct submit marks an unanswered assessment complete; existing test expects this | Assessment controller/tests | Critical | Phase 22 should add characterization, then approve server-side completeness enforcement | Required to change behavior |
| AG-21 | All documented question types are runnable | Runners save only scalar option choices; numeric/text/ranking/multi-select controls are absent | Livewire runners, responses, response_options | Critical | Postpone template breadth; support only proven response types until a storage/validation design is approved | Required |
| AG-22 | Response uniqueness is enforced | Unique key includes nullable respondent ID, which permits duplicate authenticated rows under SQL NULL semantics | responses migration/model | Critical | Verify PostgreSQL behavior and concurrency in Phase 22; propose additive unique strategy | Required |
| AG-23 | Livewire IDs are locked and authorization is defense-in-depth | Assessment property is not locked; Livewire writes do not re-authorize or validate question/option scope | `AssessmentRunner`, UI rules | Critical | Add security regression tests before any fix | Required to change behavior |
| AG-24 | Public respondent runner supports the assessment scope | It uses only the first in-scope module | Public runner and scope table | Critical | Bind tokens to an explicit module/snapshot or support full composition after product decision | Required |
| AG-25 | Public responses participate in assessment results | Scoring explicitly reads only `respondent_id IS NULL` | Public runner, ScoringService, responses | Critical | Decide whether public responses are separate voice cohorts, merged inputs, or non-scoring evidence | Required |
| AG-26 | Public submission is durable and auditable | Submit state is only in browser session | Public runner/token tables | High | Approve durable respondent-session model before expansion | Required |
| AG-27 | Evidence is optional support attached to questions | No evidence or notes UI/storage path exists in active response model | Runners, responses | Medium | Postpone until after templates/snapshots; implement inline only, not a repository workflow | Required later |
| AG-28 | Scoring uses a consistent scale | PHSAI/school yes-no weights are 0/1; HIVAW and result thresholds are 0-100 | Seeders, ScoringService, score tables | Critical | Freeze score changes, build fixed-fixture reproducibility tests, then choose canonical scale/versioning | Required |
| AG-29 | Most framework content can be scored once calibrated | Only four current sub-indices are seeded; health-facility/school questions generally lack sub-index mapping | Seeders, sub-index tables, scoring | Critical | Label unsupported compositions unscored; do not publish score-ready templates without validation | Required |
| AG-30 | Documented seven-stage scoring pipeline exists | Active service calculates only sub-index, domain, overall, and maturity; no root-cause/recommendation/report assembly stage | `ScoringService`, dormant tables | High | Correct documentation and postpone unused stages | Required for roadmap |
| AG-31 | Domain weights and multiple scoring constructs are active | Domain weights, topics, corroboration, project rollups, and clinical-quality fields are unused | Scoring migrations/service | Medium | Preserve dormant schema and avoid treating it as implemented | No change without approval |
| AG-32 | Findings and recommendations are first-class/persisted | Findings are generated view text; recommendation/root-cause tables are inactive | Results view, dormant tables | High | Postpone first-class findings until approved Phase 29/30 | Required later |
| AG-33 | Reports and report sections are persisted | No report tables/models; outputs are generated on demand | Export controller/views | High | Add report snapshot/version only after snapshot decision | Required |
| AG-34 | Report history compares equivalent assessments | Current uncommitted code compares runs by `scope_type`, not exact composition | Assessment results controller | High | Add composition fingerprint after snapshot architecture; avoid misleading comparisons | Required |
| AG-35 | Multi-module exports/report labels reflect all modules | CSV/dashboard frequently display only first module | Export/dashboard/views | High | Preserve current output until multi-module baseline decision; then extend compatibly | Required |
| AG-36 | Signed-share records exist and are governed in the schema | Application uses stateless temporary signed URLs; `assessment_share_links` is unused | Export controller, share-links table | Medium | Keep current URLs; decide whether persistent revocation/audit is needed | Required later |
| AG-37 | Audit-sensitive actions are logged | `audit_logs` exists but is unused | audit table/controllers | High | Define auditable events before template governance | Required |
| AG-38 | Curator role and routes support content governance | User helper recognizes CURATOR, but only platform admins can edit content and no curator route group exists | User, routes, admin middleware | High | Approve curator permissions and separation of duties before template publishing | Required |
| AG-39 | UI rules use the current Ocean Blue design system | `ui-rules.md` lists a green Vytte palette while code and phases use Ocean Blue | UI rules and CSS | Low | Update documentation; preserve code tokens | Required only for doc normalization |
| AG-40 | Reports navigation is functional | Sidebar Reports item links to `#`; no reports index route | App layout/routes | Medium | Leave untouched in Phase 21; decide whether to remove or implement in an approved phase | Required |
| AG-41 | Flutterwave webhook is deployable as documented | Route uses web middleware but only Paystack is excluded from CSRF | bootstrap/routes/controller | Critical | Characterize with an HTTP test, then correct operational configuration | Required to fix |
| AG-42 | External APIs are part of a defined platform surface | No API route file/versioned API exists; only webhooks, signed links, forms, and Livewire | routes/bootstrap | Medium | Do not design a new API for templates unless a consumer requires it | Required later |
| AG-43 | Completed results are immutable/reproducible | They reference mutable live questions/options/modules and regenerated report views | Content/admin, responses, reporting | Critical | Snapshot/version design is mandatory before template publishing | Required |
| AG-44 | Evidence is a first-class library (primary handoff wording) | v2 explicitly says evidence is optional inline support and not a separate repository; implementation has none | Handoff documents | High | Follow v2 refinement: optional question support, progressively disclosed | Required acknowledgment |
| AG-45 | Target types/settings do not become unrelated verticals | Current seeds add school modules and generic target types; product boundary requires health assessments in settings | Reference/school seeders | High | Require health-purpose classification and product review for every setting/template | Required |

## Missing documentation

- Current schema dictionary for the 60 migrated tables.
- Supported question-type matrix and response-storage rules.
- Assessment and publication state machine.
- Public respondent identity, submission, and scoring semantics.
- Scoring scale, algorithm version, aggregation rules, and recalculation policy.
- Content governance, curator permissions, and approval workflow.
- Report reproducibility and signed-link lifecycle.
- Deployment environment matrix and PostgreSQL/SQLite compatibility policy.
- Feature/plan key catalogue.
- Data retention/privacy policy for consent, public responses, future evidence, and exports.
- Operational ownership of dormant tables.
- Current post-Module-20 change history.

## Technical debt that should not be folded into template implementation

- Partial tenant scoping and missing policies.
- Composite-key Eloquent limitations.
- Hard-coded external seeder path.
- Mixed raw-query/Eloquent access without repositories or bounded services.
- Unsupported response types and unused schema.
- Inconsistent score scales and missing scoring versions.
- Stateless public submission.
- Missing audit logging.
- Generic README and stale operating documentation.

These items require characterization and separate approvals. Template work must not be used as a vehicle for an unbounded rewrite.

## Phase 22 verification update — 17 July 2026

- Existing full SQLite suite passed: 312 tests, 736 assertions, 0 failures, 0 skips, 60.476 seconds.
- Six additive characterization tests passed with 10 assertions.
- Final combined SQLite suite passed: 318 tests, 746 assertions, 0 failures, 0 skips, 62.078 seconds.
- AG-01 is partially resolved: the executable worktree boundary is accepted and tested, but no Git commit boundary has been selected.
- AG-03 is resolved in documentation: `AGENTS.md` now identifies Phase 22 and the approved two-path creation model.
- AG-06 is decided operationally: PostgreSQL remains production authority; SQLite is temporarily permitted locally. PostgreSQL parity remains unverified because the local Docker service is unavailable.
- AG-17 is decided directionally: preserve multi-module work only as the comprehensive-path prototype.
- AG-21 through AG-25 are now directly evidenced by characterization tests. They remain unresolved product/security behaviors and must not be mistaken for desired contracts.
- AG-41 remains code-evidenced: Paystack is exempted from CSRF and Flutterwave is not. No payment behavior was changed in Phase 22.

## Remediation status

- **Resolved in Module 1:** AG-18, AG-22, AG-23.
- **Resolved in Module 2:** AG-20, AG-41.
- **Resolved in Module 3:** AG-28.
- **Resolved in Module 4:** AG-13.
- **Foundation completed in Module 4:** AG-45 now has explicit health-domain classification and setting mappings; template-level enforcement follows in the template module.
- **Resolved in Module 5:** AG-21 for the approved initial response contract. Unsupported declared types remain unavailable and cannot enter published templates.
- **Foundation completed in Module 5:** AG-16, AG-17, AG-38, and AG-43 now have immutable template/version and provenance seams; assessment snapshots and curator interfaces follow in later modules.
- **Additionally hardened in Module 1:** public Livewire identifiers and mutations now revalidate their token/assessment context; authenticated and public responses validate the question/option relationship.
- Implementation evidence and test counts are maintained in `IMPLEMENTATION_PROGRESS.md`.
