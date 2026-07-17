# Architecture Gaps and Documentation Discrepancies

## Severity

- **Critical**: can break isolation, historical reproducibility, scoring, or core workflow.
- **High**: material compatibility or governance risk.
- **Medium**: incomplete capability or misleading documentation.
- **Low**: limited operational/documentation impact.

## Complete discrepancy ledger found in Phase 21

| ID | Documented or implied behavior | Actual repository behavior | Affected files/tables | Severity | Recommendation | Isaac approval |
|---|---|---|---|---|---|---|
| AG-01 | Repository baseline is known and green | Resolved: remediation is committed in bounded pushed modules and the current SQLite suite passes 365 tests / 870 assertions | Git history, tests, progress log | Resolved | Re-run after every module; PostgreSQL remains a release gate | Approved and implemented |
| AG-02 | `phases.md` reflects implemented history | Resolved: Module 20 commit and the complete remediation commit sequence are recorded | `docs/phases.md`, Git log | Resolved | Update the history with every future bounded module | Approved and implemented |
| AG-03 | `AGENTS.md` is authoritative | Resolved: it now describes the current two-path product, actual tenancy model, lifecycle, scoring, database matrix, and engineering rules | `AGENTS.md` | Resolved | Keep synchronized with accepted decisions | Approved and implemented |
| AG-04 | Behavioral documentation is discoverable | Resolved by documentation normalization: historical modules are in `phases.md`; current behavior and contracts are in architecture references and tests | `docs/phases.md`, `docs/architecture/`, tests | Resolved | Add a dedicated module spec only when it improves a future bounded change | Approved |
| AG-05 | README describes Vytte setup and operation | Resolved with Vytte product model, local setup, PostgreSQL configuration, verification, and architecture references | `README.md` | Resolved | Keep commands executable | Approved and implemented |
| AG-06 | Production database authority and local matrix are explicit | Resolved operationally: PostgreSQL is production/release authority; SQLite is supported temporarily for local development and fast tests | README, `.env.example`, PHPUnit, database config | Operationally resolved | Run PostgreSQL migrations/full suite/concurrency checks before release | Approved; parity pending environment |
| AG-07 | Tenant access is defense-in-depth | Resolved for active project/assessment surfaces: workspace scopes fail closed, workspace membership is verified, and explicit Project/Assessment policies protect controller actions | Scope, workspace middleware, policies, controllers | Resolved core | Apply the same policy pattern whenever a new tenant-owned route/model is activated | Approved and implemented |
| AG-08 | Workspace membership/invitation access is tenant-safe | Resolved by equivalent design: membership is the authority used to resolve a workspace; team/invitation mutations use explicit workspace predicates and role checks | Workspace middleware and TeamController | Resolved | Do not add global scopes to composite membership models where explicit authority queries are clearer | Approved and implemented |
| AG-09 | Project v1 has exactly one target/setting | Resolved: the existing junction remains, but a database unique index enforces one target per project and refuses migration when historical conflicts require an explicit decision | `project_targets`, project creation | Resolved | Postpone multi-target product behavior and a primary-target concept | Approved and implemented |
| AG-10 | Schema inventory matches migrations | Resolved: current data-model audit records 67 application/framework tables after migrations and treats migrations as authority | Data-model audit, migrations | Resolved | Avoid historical fixed-count claims | Approved and implemented |
| AG-11 | Identifier strategy is documented | Resolved: UUID, integer, string, and composite keys are explicitly preserved and documented | Data-model audit, migrations/models | Resolved | Do not normalize keys without a bounded compatibility need | Approved |
| AG-12 | Catalogue and scope-table names are accurate | Resolved: documentation and template code use `assessment_modules` for the catalogue and `assessment_module_scope` for assessment composition | Architecture docs and implementation | Resolved | Preserve names as compatibility contracts | Approved and implemented |
| AG-13 | Scoring domains, questionnaire sections, and health domains are distinct | Resolved with governed setting/health taxonomy and explicit documentation | Taxonomy tables, template validation, docs | Resolved | Preserve the distinction in code and UI | Approved and implemented |
| AG-14 | Seed counts are governed dataset metadata | Resolved with `DATASET_MANIFEST.md`, repository-contained seed order, expected baseline counts, and content-governance rules | Seeders and dataset manifest | Resolved | Version reviewed dataset changes; do not use counts as architecture constants | Approved and implemented |
| AG-15 | Seed process is repository-contained and reproducible | Resolved: environment-dependent PHSAI and incomplete school seeders were removed; the default seed is repository-contained | Default seeder and dataset manifest | Resolved | Add governed structured content with source/licence metadata | Approved and implemented |
| AG-16 | Published/approved content is stable | Resolved for governed content: published versions retain exact immutable payloads and assessments retain content/scoring snapshots | Template versions and assessment snapshots | Resolved | Legacy non-template assessments remain on the compatibility path | Approved and implemented |
| AG-17 | Assessment creation matches the approved product model | Resolved: exactly two paths exist; comprehensive templates may select areas, focused templates open one scope directly | Creation controller/service/view | Resolved | Do not restore standard-battery or generic module-picker creation | Approved and implemented |
| AG-18 | Module selection is scoped to target applicability | Resolved: creation is driven by a published template; comprehensive templates must match the target setting and selected areas must belong to the immutable payload | Creation service and publishing | Resolved | Preserve server-side validation | Approved and implemented |
| AG-19 | Assessment statuses are documented consistently | Resolved: execution, area, and template-publication states have separate canonical constants, guarded transitions, and a documented state machine | Models, services, lifecycle documentation | Resolved | Preserve persisted compatibility values; add reopen/archive only with explicit snapshot and audit rules | Approved and implemented |
| AG-20 | Incomplete assessments cannot be submitted | Resolved: server-side completeness validates required scored snapshot/live questions before terminal completion | Assessment controller/tests | Resolved | Extend the rule alongside any future response type | Approved and implemented |
| AG-21 | Published response types are runnable | Resolved by a narrow contract: scalar option/LIKERT and unscored open text are publishable; unsupported declared types are rejected | Runners, publishing, response contract | Resolved boundary | Add a type only with end-to-end renderer/storage/scoring/report support | Approved and implemented |
| AG-22 | Response uniqueness is enforced | Resolved for assessor rows with a partial unique index; public rows retain per-session uniqueness | Response migrations/model | Resolved locally | Verify PostgreSQL concurrency/parity before release | Approved and implemented |
| AG-23 | Livewire IDs are locked and authorization is defense-in-depth | Resolved: assessment/token identifiers are locked and mutations re-check workspace, token, question, option, consent, and completion authority | Livewire runners/tests | Resolved | Preserve attack regression tests | Approved and implemented |
| AG-24 | Public respondent runner supports the assessment scope | Resolved: it loads every in-scope module and uses immutable snapshot content when present | Public runner, snapshot, and scope table | Resolved | Preserve full-composition regression coverage | Approved and implemented |
| AG-25 | External respondent answers use the normal assessment results architecture | Resolved: durable sessions are independently scored, eligible completed scores use the frozen arithmetic-mean contract, and manual finalization creates the ordinary immutable report | Public runner, shared ScoringService, aggregation contract, reports | Resolved locally | Verify PostgreSQL concurrency/parity before release; add future methods only as governed versions | Approved and implemented |
| AG-26 | Public submission is durable and auditable | Resolved: durable response sessions, submitted timestamps, response/consent FKs, and token creator/revocation/usage audit fields are implemented | Public runner/token/session tables | Resolved | Define retention policy before production collection | Approved and implemented |
| AG-27 | Evidence is optional support attached to questions | Resolved for the authenticated runner: an optional note is stored on the exact response and revealed progressively | Runner, responses | Resolved core | Add governed file attachments only if a demonstrated need and retention policy justify them | Approved and implemented |
| AG-28 | Scoring uses a consistent scale | Resolved: option scales normalize to canonical 0–100, outputs store an algorithm version, and published scoring profiles are frozen | Scoring service/snapshots/tests | Resolved | New formulas require new versions and fixtures | Approved and implemented |
| AG-29 | Most framework content can be scored once calibrated | Resolved at the publishing boundary: incomplete sample frameworks are not seeded/published, and every scored question in a published template must have a complete scoring-profile mapping and weighted options | Publishing, snapshots, scoring | Resolved for published templates | Add curated frameworks only after their scoring profiles pass validation | Approved and implemented |
| AG-30 | Scoring documentation matches the active pipeline | Resolved in documentation: the active versioned sub-index/domain/overall/maturity pipeline is authoritative; unused stages are not claimed | Scoring contract/service | Resolved documentation | Add stages only through a versioned scoring decision | Approved |
| AG-31 | Dormant scoring constructs are not presented as active | Resolved boundary: domain weights, topic/project rollups, and clinical-quality fields remain reserved; obsolete special aggregation was removed | Data-model audit/migrations | Resolved boundary | Activate only with an approved consumer and algorithm | Approved |
| AG-32 | Findings/recommendations behavior is accurate | Resolved documentation boundary: findings are derived presentation text; root-cause/recommendation tables are dormant and not claimed as product features | Results/data-model audit | Postponed product capability | Do not activate until a health-assessment need is approved | Approved postponement |
| AG-33 | Reports and report sections are persisted | Resolved: completion persists an immutable structured report payload, schema version, and content hash | Report snapshot service/model | Resolved | Keep format-specific files regenerable from the structured snapshot | Approved and implemented |
| AG-34 | Report history compares equivalent assessments | Resolved: template history uses exact composition hashes and comparison rejects differing fingerprints; legacy runs use exact sorted module IDs | Results/progress controllers | Resolved | Revalidate if future customization changes scoring without changing composition hash | Approved and implemented |
| AG-35 | Multi-module exports/report labels reflect all modules | Resolved for results, PDF, shared reports, and CSV; some dashboard/project-card labels remain UI cleanup | Export/results/dashboard views | Medium | Use the same snapshot title/area helper in remaining summary cards | Core outputs resolved; UI cleanup pending |
| AG-36 | Shared-report records are governed | Resolved: governed links record creator, expiry, revocation, use count, and last-used audit; the reports index exposes active-link management | Export controller, reports index, share-link model | Resolved | Preserve legacy signed URLs as read-only compatibility routes | Approved and implemented |
| AG-37 | Audit-sensitive actions are logged | Resolved for template publication, assessment creation/completion/report finalization, respondent-link lifecycle, and report-link lifecycle | Immutable audit model/service and workflows | Resolved core | Expand the event catalogue only alongside new sensitive workflows | Approved and implemented |
| AG-38 | Curator role and routes support content governance | Resolved: CURATOR/platform admin authority is enforced by dedicated middleware and a governed publishing route; publishing is audited | Curation controller/middleware/routes | Resolved core | Build curator catalogue/review UI without weakening server authority | Approved and implemented |
| AG-39 | UI rules use the current Ocean Blue design system | Resolved: UI rules mirror the authoritative CSS tokens and canonical status/product language | UI rules and CSS | Resolved | Keep CSS authoritative | Approved and implemented |
| AG-40 | Reports navigation is functional | Resolved: the sidebar opens a workspace-scoped final-reports index with view, PDF, share, and revocation actions | Reports controller/view/routes | Resolved | Keep this as an index over the one reporting engine | Approved and implemented |
| AG-41 | Flutterwave webhook is deployable as documented | Resolved: Flutterwave and Paystack webhook paths are CSRF-exempt while retaining provider validation | bootstrap/controllers/tests | Resolved | Preserve end-to-end provider tests | Approved and implemented |
| AG-42 | External platform surface is accurately defined | Resolved documentation boundary: Vytte has webhooks, forms/Livewire, invitations, respondent links, and report links; no generic API exists without a consumer | Current architecture/routes | Resolved boundary | Add a versioned API only for an approved consumer | Approved postponement |
| AG-43 | Completed results are immutable/reproducible | Resolved for newly completed assessments: results, PDF, shared report, score history, and report labels consume an immutable final report snapshot | Content, scoring, reporting | Resolved | Legacy completed assessments use best-effort live fallback until deliberately backfilled | Approved and implemented |
| AG-44 | Evidence is a first-class library (primary handoff wording) | The v2 refinement is now implemented: evidence is optional inline support, not a repository or separate workflow | Runner, responses, v2 handoff | Resolved | Preserve this product boundary | Approved and implemented |
| AG-45 | Target types/settings do not become unrelated verticals | Resolved at the publish boundary: settings and health domains are separate, focused templates require one health domain, and comprehensive templates require a setting | Taxonomy and template publication | Resolved | Require health purpose and governed content for every published template | Approved and implemented |

## Outstanding operating evidence

- PostgreSQL migration, full-suite, partial-index, upsert, and response-concurrency parity after the local PostgreSQL environment is restored.
- PostgreSQL parity for respondent-session partial unique indexes, locking, immutable artifacts, and the finalization transaction after the local PostgreSQL environment is restored.

## Bounded technical debt

- Composite-key Eloquent models require explicit composite predicates for writes.
- Declared but unsupported response types and dormant schema remain reserved.
- Legacy non-template assessments use live-content/best-effort report compatibility.
- Mixed query-builder and Eloquent access remains appropriate only where each operation preserves the same authority boundary.

Current contracts are documented in `RESPONSE_TYPE_CONTRACT.md`, `SCORING_CONTRACT.md`, `DATA_RETENTION_AND_PRIVACY.md`, `PLAN_FEATURE_CATALOGUE.md`, `LIFECYCLE_STATE_MACHINE.md`, and `DATA_MODEL_AUDIT.md`.

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
- **Resolved in Module 6 for new template-based assessments:** AG-16 and AG-43 presentation mutability. Report generation and scoring snapshot consumption remain separately tracked.
- **Resolved in Module 9:** published versions now retain their exact hashed payload; assessment creation, runner validation, consent applicability, and scoring consume frozen content/scoring fields. AG-29 is enforced at publication.
- **Resolved in Module 11:** AG-36, AG-37, and the server-authority portion of AG-38. Persistent report links are revocable/audited, core sensitive events are logged, and curator publishing has an explicit protected route.
- **Resolved in Module 12:** AG-07, AG-08, and AG-09. Workspace resolution requires membership, workspace-scoped queries fail closed, Project/Assessment policies centralize route authorization, and the database enforces one setting per project.
- **Direction corrected in Module 13:** community, patient, citizen, and caregiver feedback are ordinary assessment templates. Their respondent roles must extend the existing scoring and reporting engine rather than create a parallel report.
- **Resolved in Module 14:** AG-27 and AG-44. Optional supporting notes are progressively disclosed and attached directly to question responses; no evidence repository or parallel workflow was introduced.
- **Resolved in Module 15:** AG-19. Canonical lifecycle constants, guarded terminal completion, immutable publication transitions, and a state-machine reference now distinguish assessment `COMPLETE` from area `COMPLETED`.
- **Resolved in Module 16:** AG-40 and the AG-36 management follow-up. Reports navigation now opens the standard completed-assessment report index and manages governed share links.
- **Resolved in Module 7:** AG-15, AG-17, and the template-level enforcement portion of AG-45.
- **Superseded in Module 7:** the legacy "standard battery" creation language and UI. Only the two approved creation paths remain.
- **Additionally hardened in Module 1:** public Livewire identifiers and mutations now revalidate their token/assessment context; authenticated and public responses validate the question/option relationship.
- Implementation evidence and test counts are maintained in `IMPLEMENTATION_PROGRESS.md`.
