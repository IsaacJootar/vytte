# Preservation Register

## Purpose

This register identifies current assets that future phases must preserve, the compatibility promise attached to each asset, and the acceptable change boundary. It does not authorize changes.

## Preservation classes

- **Untouched**: no behavioral or schema change without a separately approved compatibility plan.
- **Extend through seam**: additive integration is acceptable after approval and regression coverage.
- **Refactor behind contract**: internal refactoring may be necessary, but only after tests lock current external behavior.
- **Dormant; verify first**: schema exists without active application use and must not be repurposed casually.

## Register

| Asset | Current contract to preserve | Class | Future boundary |
|---|---|---|---|
| Workspace registration | User, workspace, OWNER membership, and active workspace created atomically | Untouched | Add onboarding only outside the transaction contract. |
| Workspace/project isolation | Projects and targets from other workspaces are not route-bindable | Untouched | Strengthening downstream isolation is allowed only with compatibility tests. |
| Existing primary keys and FKs | Mixed UUID/integer/composite identifiers and public route keys | Untouched | New tables may use new conventions; do not normalize legacy IDs. |
| Project creation | Project and first target created/attached transactionally | Untouched | Template discovery may follow project creation; it must not replace target creation. |
| `project_targets` | Existing relationships and rows | Untouched | Decide primary/multi-target semantics before changing UI assumptions. |
| Module catalogue | Existing `assessment_modules` IDs, codes, names, target types, active flags | Extend through seam | Templates should reference modules through versioned composition records. |
| Module-local domains | Existing `module_domains` sections and ordering | Untouched | Do not conflate with global scoring domains. |
| Question and option IDs | Existing response foreign-key targets | Untouched | Published template versions need immutable copies/revisions, not in-place ID replacement. |
| Question translations | Existing fallback-to-English overlays | Extend through seam | Template/version translation needs an explicit inheritance rule. |
| Assessment rows | Existing project/target/tier/status/scope references | Untouched | Add nullable template-version/snapshot references only after approval. |
| Assessment-module scope | Existing selected/excluded module records and exclusion reasons | Extend through seam | Natural adapter output for template resolution; preserve existing rows. |
| Authenticated runner UX | One-question flow, autosave, progress, consent, localization | Refactor behind contract | Add response-type renderers and snapshot input behind the current route/component contract. |
| Public respondent links | Existing token URLs and anonymous response rows | Extended behind contract | Existing URLs remain valid; new sessions add durable identity, full-scope loading, and token auditing without rewriting historical rows. |
| Respondent consent records | Verbatim text, actor/session, timestamps | Untouched | Version consent text or module applicability without rewriting prior consent. |
| Responses | Existing assessment/question/option/text/numeric values | Untouched | Add notes/evidence and uniqueness fixes without rewriting historical rows. |
| Scoring output tables | Existing sub-index/domain/assessment score rows | Untouched | New algorithms require an approved versioning/recalculation policy. |
| `ScoringService` public behavior | Null for uncalibrated, stored status, synchronous submit hook | Refactor behind contract | Isolate algorithms by template/scoring profile only after reproducibility tests. |
| Score bands and maturity | Weak/moderate/strong thresholds and five maturity ranges | Untouched | Changes require product approval and historical-report policy. |
| Results routes | Authenticated results URLs and workspace checks | Untouched | Add template metadata without changing legacy URLs. |
| PDF/CSV exports | Existing endpoints, filenames/content shape, plan gates | Untouched | Add fields compatibly; preserve legacy columns and reports. |
| Signed reports | Existing temporary signed URLs and public read-only behavior | Untouched | Persisted report snapshots may sit behind the same route after approval. |
| Progress/comparison | Existing project-scoped completed-run pages | Refactor behind contract | Compare only compatible composition fingerprints once available. |
| Notifications/email gate | Database notifications plus email toggle | Untouched | New template events use the same gate and audience rules. |
| Billing and feature gates | Workspace plans, limits, and `plan_features` matrix | Untouched | Template features should initially use additive feature keys. |
| Platform admin routes | `PLATFORM_ADMIN` authorization boundary | Untouched | Curator governance requires its own approved role/permission boundary. |
| Dark/light theme and mobile shell | User theme persistence, desktop sidebar, 375px mobile navigation | Untouched | New template UI must use existing layouts/components. |
| Locale behavior | English/French user preference and runner fallback | Untouched | New locales require allowlist and content-coverage decisions. |
| Payment webhooks | Signature validation and plan upgrade behavior | Untouched | CSRF/configuration correction requires a tested operational change, not template work. |
| Test database fixtures | Existing factories and feature-test patterns | Extend through seam | Phase 22 must establish PostgreSQL parity before relying on SQLite alone. |
| `audit_logs` and inactive analytical tables | Existing schemas and any unknown production data | Dormant; verify first | Do not reuse names for template governance or evidence. |
| Root-cause/recommendation tables | Existing dormant schema | Dormant; verify first | Postpone until recommendation-library scope is approved. |
| Pre-existing uncommitted worktree | Multi-module creation/runner, school seeds, UI changes | Preserve as accepted Phase 22 baseline | Do not discard or overwrite. Treat multi-module behavior only as the comprehensive-path prototype. Keep Phase 22 audit/test changes separable until Isaac chooses a commit boundary. |

## Components to keep untouched during the first template implementation

The following should remain behaviorally untouched: authentication, workspace resolution, project/target creation, current URLs, billing/webhooks, notifications, layouts/design tokens, translation fallback, completed assessment rows, response rows, score rows, PDF/CSV/signed-report endpoints, and progress routes.

## Approved-seam candidates for later phases

After Phase 22 and explicit approval, the lowest-impact extension points are:

1. A versioned template catalogue above `assessment_modules`.
2. A template-version composition table referencing existing module IDs.
3. Nullable provenance fields or a separate one-to-one provenance record for new assessments.
4. A snapshot payload/normalized snapshot graph read by the runner.
5. An adapter that writes existing `assessment_module_scope` rows from a template selection.
6. Feature flags that expose template discovery while leaving legacy assessment creation available.

## Preservation test obligations for Phase 22

- Cross-workspace access for every assessment, response, score, report, token, consent, and export route.
- Completed-assessment output reproducibility using fixed fixtures.
- Existing signed links and public respondent links.
- Scoring idempotence and exact numeric outputs.
- Question/option edit effects on active and completed assessments.
- SQLite/PostgreSQL constraint parity, especially nullable uniqueness and CHECK constraints.
- Multi-module behavior separated from the last committed baseline.

## Phase 22 evidence added

`tests/Feature/Phase22CharacterizationTest.php` now locks six observed behaviors without correcting them:

1. Direct Livewire mounting does not enforce the workspace boundary.
2. The authenticated runner accepts an option belonging to a different question.
3. SQLite permits duplicate staff response keys when `respondent_id` is `NULL`.
4. The public runner loads every in-scope module (the former first-module defect is now a regression test).
5. The current scorer excludes external-respondent rows. This is an observed compatibility behavior, not the desired architecture; future support must extend the shared versioned scorer and report.
6. Numeric questions have no authenticated runner input/storage path.

These are preservation observations, not desired contracts. Each unsafe/incomplete behavior remains gated by its corresponding decision in `DECISION_LOG.md`.
