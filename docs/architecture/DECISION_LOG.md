# Architecture Decision Log

## Controlling Decision

### DEC-2026-07-18-001: Platform-Governed Assessment Composition

- **Status:** Accepted and implemented.
- **Decision:** Vytte owns official departments, department framework versions, facility profiles, catalogue releases, scoring methodology, aggregation policy, publication, versioning, and provenance.
- **Comprehensive Health Assessment:** implemented as a composition orchestrator. It resolves a facility profile, loads a published catalogue release, applies required/default/optional department policy, and freezes one immutable assessment snapshot.
- **Focused Health Assessment:** implemented as a single-scope catalogue release for one health domain, programme, topic, or intervention.
- **Catalogue rule:** the system must never resolve "latest framework version" at assessment creation time. A published catalogue release pins exact framework version IDs and hashes.
- **Snapshot rule:** every assessment freezes content, manifest, policy, collection config, scoring version, hashes, and exclusions.
- **Workspace rule:** workspaces consume approved Vytte content. They cannot publish official departments, official frameworks, official scoring, or official catalogue releases.
- **Local customization rule:** workspace-local custom sections may capture local context but cannot alter official content and never affect official scoring.
- **Reporting rule:** all use cases share the ordinary Vytte scoring and reporting architecture. No separate community, respondent, or local-section reporting subsystem exists.
- **Dataset rule:** current seeded catalogue content is demonstration-only and not production clinical methodology.

## Supporting Decisions

### DEC-2026-07-18-002: Production Database Authority

- **Status:** Accepted.
- **Decision:** PostgreSQL is the database authority for local development, automated tests, release verification, and production.
- **Release gate:** run migrations, full tests, and concurrency-sensitive checks against PostgreSQL before production release.

### DEC-2026-07-18-003: Two Creation Paths

- **Status:** Accepted and implemented.
- **Decision:** Vytte has exactly two creation paths: Comprehensive Health Assessment and Focused Health Assessment.
- **Boundary:** No unrelated bulk starter set, generic module picker, or giant comprehensive template is part of the product model.

### DEC-2026-07-18-004: Unified Respondent Architecture

- **Status:** Accepted and implemented.
- **Decision:** Community, patient, caregiver, citizen, and similar feedback use the same assessment lifecycle, scoring engine, reporting engine, permissions, exports, dashboards, and analytics.
- **Boundary:** Respondent role and template content may differ. Reporting architecture does not.

### DEC-2026-07-18-005: Inline Evidence Boundary

- **Status:** Accepted and implemented.
- **Decision:** Evidence is optional support attached to a question response. It is not a separate repository, workflow, or scoring input.

### DEC-2026-07-18-006: Response Type Publication Contract

- **Status:** Accepted and implemented.
- **Decision:** Official content may publish only response types with working UI, storage, validation, completeness, snapshot, reporting, and scoring or explicit unscored behavior.
- **Current publishable types:** scalar option, Likert, open text when unscored, and numeric with valid configuration.

### DEC-2026-07-18-007: Terminal Assessment Completion

- **Status:** Accepted and implemented.
- **Decision:** Assessment execution is `IN_PROGRESS -> COMPLETE`. Completion is terminal and creates the immutable report snapshot.
- **Future work:** reopening, correction versions, cancellation, or archive semantics require new approved lifecycle rules.

### DEC-2026-07-18-008: Demonstration Dataset

- **Status:** Accepted and implemented.
- **Decision:** Use a small labelled demonstration dataset to prove the architecture rather than inventing production clinical methodology.
- **Boundary:** production content requires governed source, licence, review, scoring, and publication.

### DEC-2026-07-18-009: Platform Admin UI and Demo Account Boundary

- **Status:** Accepted and implemented.
- **Decision:** Vytte Platform Admin uses the same application shell and visual language as the workspace app, with only a subtle sidebar color distinction to signal elevated platform authority.
- **Account boundary:** the demo Platform Admin account is not an Agency customer account; workspace plan labels must not be used as the visible authority label for Platform Admins.
- **UI rule:** Platform Admin users should see `Vytte Platform Admin` as their access label, while ordinary workspace users continue to see their configured paid-plan label.

### DEC-2026-07-18-010: Beta Licensing Without Payments

- **Status:** Accepted and partially implemented.
- **Decision:** Public beta uses configurable Starter, Professional, and Organization plan records with all features unlocked through plan configuration.
- **Boundary:** Payment processing, billing, subscriptions, invoices, provider webhooks, refunds, taxes, coupons, and ledgers are deferred until after beta.
- **Implementation rule:** Do not hardcode free access; use `subscription_plans`, `plan_features`, and `PlanService`.
- **Known drift:** `PlatformSettingController` still renders and persists `plan.free_projects`, `plan.free_assessments_per_project`, and `plan.pro_projects`. `PlanService` no longer reads them, so those controls have no effect. Resolving this requires a decision on whether to remove the controls; it is recorded here rather than silently corrected.

### DEC-2026-07-19-015: The Builder Retires A Version At Publication, Not At Clone Time

- **Status:** Accepted and implemented.
- **Context:** Advanced Tools supersedes a published framework version the moment its successor draft is created. In the builder that would pull a working assessment out of service as soon as an author began a correction, leaving nothing publishable until the new version was finished.
- **Decision:** "Create new version" clones the content into a draft and leaves the predecessor `PUBLISHED` and selectable. The predecessor, and the catalogue release pinning it, are moved to `SUPERSEDED` only when the successor is published.
- **Constraint:** only one open draft version per published assessment. A second attempt is refused so lineage stays a single line.
- **Preserved:** the predecessor keeps its frozen `published_payload` and `content_hash`. Assessments and reports already created are unaffected because their content lives in immutable snapshots. A superseded release can no longer start new assessments, which `AssessmentCreationService` already enforces.
- **Boundary:** Advanced Tools keeps its immediate-supersede behaviour for expert use. Both paths share one clone implementation in `AssessmentVersionService`; only the retirement timing differs.

### DEC-2026-07-19-013: Author Self-Approval Is A Temporary Beta Limitation

- **Status:** Accepted for beta. Requires a governance decision before production.
- **Context:** `CONTENT_GOVERNANCE.md` and the Phase A.5 review call for a review chain with distinct author, reviewer and approver. The assessment builder implements explicit, audited approval, but the same Platform Admin may author a question and approve it.
- **What is in place:** approval is never implicit. A question stays a draft through building, scoring and saving; a separate action approves it, routes through `QuestionVersionPublishingService` so every content check applies, freezes the version, and records `assessment.question.approved` with the acting user. Publication records `assessment.published`.
- **What is missing:** separation of duties. Nothing prevents one person performing both roles, and no reviewer identity distinct from the author is captured.
- **Why deferred:** a multi-person approval workflow is a governance design, not a UI change. Inventing one under beta time pressure risks a model that has to be replaced.
- **Boundary:** the audit trail already records who approved what, so a future policy can be applied without losing history. Do not describe current approval as independent review.

### DEC-2026-07-19-014: One Authored Assessment Publishes As One Focused Catalogue Release

- **Status:** Accepted and implemented.
- **Context:** what an author calls "an assessment" maps to two governed artifacts: a department framework version holding the content, and a catalogue release making it selectable. Publishing only the framework leaves nothing usable.
- **Decision:** the builder's Publish action publishes the framework and then creates and publishes one `FOCUSED` catalogue release pinning exactly that framework version, inside a single transaction. If either service refuses, the whole publication rolls back.
- **Rationale:** a focused release pins exactly one framework version and requires one health domain, which matches the author's mental model of one assessment.
- **Boundary:** comprehensive multi-department composition, facility profiles and multi-framework pinning remain Advanced Tools work and are unchanged.
- **Authority:** `DepartmentFrameworkPublishingService` and `CataloguePublishingService` remain the only authorities on whether publication may proceed. `AssessmentPublicationService` orchestrates and validates nothing itself.

### DEC-2026-07-19-011: Responses Are Keyed By Question Identity

- **Status:** Accepted as a description of existing behavior.
- **Context:** This record documents an existing implementation constraint that was previously undocumented. It introduces no new policy.
- **Behavior:** `responses` rows are keyed by `question_id`, the question identity, not by `framework_question_placement_id`. Scoring resolves each response by question identity against the frozen snapshot.
- **Consequence:** One question identity can appear at most once in a single assessment. `AssessmentCreationService::createFromCatalogue` rejects a composition containing duplicate question identities across the selected framework versions.
- **Boundary:** A question version may still be placed in multiple *frameworks* with different wording, weight, and analytical meaning. The constraint applies only within one composed assessment.
- **Trade-off:** A question cannot be answered separately per department within one comprehensive assessment. Distinct identities are required for that today.
- **Open question:** Whether to re-key responses to placements is not decided. Changing it would affect responses, both runners, both scorers, public session snapshots, and every existing report, and would be materially cheaper before a large content library exists than after.

### DEC-2026-07-19-012: Assessment Snapshot Immutability Is Enforced

- **Status:** Accepted and implemented.
- **Context:** This record documents an existing contract that is now enforced in code. It introduces no new policy.
- **Decision:** `AssessmentSnapshot` rejects updates through a model guard, matching `QuestionVersion`, `DepartmentFrameworkVersion`, `AssessmentCatalogueRelease`, and `AssessmentReportSnapshot`. Payload, composition manifest, aggregation policy, collection config, and content hash cannot be rewritten after creation.
- **Rationale:** The snapshot is the reproducibility authority. Silent mutation would falsify every downstream hash with no record of the original content and no way to detect or recover it.
- **Scope boundary:** Deletion is not guarded. The only real deletion path is the foreign-key cascade from `assessments`, which does not fire Eloquent events, and a missing snapshot already fails loudly in `ReportSnapshotService`. A delete guard remains a proposed follow-up.
- **Test contract:** Fixtures requiring different frozen content replace the snapshot instead of mutating it.

### DEC-2026-07-19-016: Platform Admin Is Not A Customer Account

- **Status:** Accepted and implemented.
- **Context:** DEC-009 already stated that a platform administrator governs the platform rather than using it. The implementation did not follow: signing in landed a platform admin on the workspace dashboard, in front of projects and assessments that were not theirs to create, and the way across to platform work was a small link in a footer card.
- **Decision:** A platform administrator signing in lands on `/admin`. The workspace footer "access level" card is removed. Where a platform admin does hold a workspace, the crossing is a first-class sidebar group rather than a footer link.
- **Rationale:** The role boundary is meaningless if the product opens onto the wrong side of it.
- **Boundary:** Platform role and workspace role remain separate columns. This changes where a person lands, not what they may do.

### DEC-2026-07-19-017: One Application Shell For Every Role

- **Status:** Accepted and implemented.
- **Decision:** All roles render through `layouts/app.blade.php`. Only `App\Support\RoleNavigation` differs between them. `AppLayout` and `AdminLayout` are the same view with a different navigation array.
- **Rationale:** Platform Admin had drifted from the workspace design — notably it was not mobile-first while every other role was. Two shells guarantee that drift returns. One shell means spacing, mobile behaviour, theme handling and focus states are defined once and cannot diverge.
- **Consequence:** A change to navigation structure is a change to one configuration file, not to several templates.

### DEC-2026-07-19-018: Screens Are Named For What They Answer

- **Status:** Accepted and implemented.
- **Context:** Several Platform Admin screens were named after the tables behind them, and two different screens were both called "assessments".
- **Decision:** Screens carry the name of the question an administrator is asking. `Platform Users and Roles` became `People`; `Report Share-Link Control` became `Shared Reports`; `Audit Logs` became `Activity`; `Assessment Oversight` became `Assessments in Use`. The primary `Assessments` screen keeps its name and holds the templates Vytte authors; `Assessments in Use` holds the filled-in ones customers are running.
- **Rationale:** Two links both reading "assessment" gave the reader no way to tell a blank template from a live one.
- **Boundary:** Route names, table names and stored values are unchanged. This is presentation only.

### DEC-2026-07-19-019: Storage Vocabulary Never Reaches The Reader

- **Status:** Accepted and enforced by test.
- **Context:** The oversight screen printed raw identifiers, `IN_PROGRESS`, `COMPREHENSIVE`, and an "Immutable artifacts" column reading `Snapshot: Yes · Report: No`.
- **Decision:** Governance and storage vocabulary is never displayed as text. Codes may still appear as form control values where a query needs them to filter.
- **Test contract:** `assertDontSeeText` for the codes, not `assertDontSee`, because a `value=` attribute is a form wire rather than something a person reads.

### DEC-2026-07-19-020: A Control That Records A Decision Must Enforce It

- **Status:** Accepted and implemented.
- **Context:** Platform Admin could set a workspace to `SUSPENDED`. Nothing anywhere acted on that value, so members of a suspended workspace kept full access. The control wrote an audit record and changed nothing.
- **Decision:** `EnsureWorkspaceIsActive` blocks use of a suspended or closed workspace. `LoginRequest` blocks a suspended account from signing in, checked after credentials pass so a wrong password never reveals that an account is suspended. `SessionRevocationService` ends sessions that already exist, because blocking future sign-in would otherwise leave someone working until their session happened to expire.
- **Rationale:** A safeguard that reads as protection while providing none is worse than no safeguard.
- **Boundary:** Platform administrators are exempt from the workspace block, otherwise suspension would hide the workspace they need to review. Nothing is deleted in any of these states.
- **Known limit:** Session revocation requires server-side sessions. `SessionRevocationService::canRevoke()` returns false on any other driver and the service reports doing nothing rather than pretending. Changing `SESSION_DRIVER` away from `database` silently removes this protection.

### DEC-2026-07-19-021: Suspension Is Additive And Carries Its Reason

- **Status:** Accepted and implemented.
- **Decision:** Suspending a person sets `users.suspended_at` and a required `users.suspension_reason`. No row is removed; memberships, authored content and history are untouched. The reason is shown to the person at sign-in and in their notification.
- **Rationale:** An account locked out with no stated reason is unexplainable to the person affected and to whoever reviews the decision later.
- **Guards:** An administrator cannot suspend their own account, and the last active platform administrator cannot be suspended or demoted, otherwise the platform locks itself out of its own governance controls.

### DEC-2026-07-19-022: Shared Links Are Durable, Not Flashed

- **Status:** Accepted and implemented.
- **Context:** Workspace invitations, respondent links and report share links were each flashed once after creation and lost on the next page load. Respondent links were never listed anywhere, so an administrator creating several could only ever see the last one, once.
- **Decision:** Every link of these three kinds is listed persistently on its own screen through `x-share-link`, which shows the URL and offers copy and WhatsApp.
- **Rationale:** Email is switched off for beta. A link that cannot be retrieved is an invitation that cannot be delivered, which made the feature unusable rather than merely rough.
- **WhatsApp:** A `wa.me` link with the message encoded server-side. Deliberately not the Business API: no account, no approval, no per-message cost, and it works on phone and desktop. Revisit only if Vytte needs delivery receipts or templated outbound messaging.
- **Revocation:** Issuing a new link for an invitation replaces the token rather than extending the expiry, so a link shared with the wrong person stops working.

### DEC-2026-07-19-023: Notifications Are In-App First, Email Behind A Switch

- **Status:** Accepted and implemented.
- **Decision:** Every notification returns `['database']` from `via()` and appends `'mail'` only when the platform setting `email.notifications_enabled` is true. Account suspension and reactivation follow the same rule as the existing notifications.
- **Rationale:** In-app notification does not depend on a third-party service being configured, so it keeps working during beta while email is off.
- **Honesty requirement:** The toggle can be switched on while no mail service can actually send. Platform Health reports a configured Resend transport with no `RESEND_API_KEY` as a warning, and the Settings screen states this before the switch is flipped rather than allowing the belief that it worked.

### DEC-2026-07-20-024: The Methodology Layer Sits Above The Platform

- **Status:** Accepted and implemented.
- **Context:** P4 introduces objectives, health areas, analysis lenses, insight categories, templates and presets. The obvious implementation would attach them to assessments, questions and snapshots.
- **Decision:** The methodology layer adds no column to, and changes no behaviour of, questions, frameworks, catalogue releases, snapshots, scoring or reporting. An assessment that never references an objective behaves exactly as it did before P4.
- **Rationale:** The layer beneath is already governed, versioned and immutable. Reaching into it would put two authorities over the same rows and would make every existing reproducibility guarantee conditional on methodology state.
- **Test contract:** A test asserts the absence of methodology columns on `assessments`, `questions`, `question_versions` and `assessment_snapshots`.

### DEC-2026-07-20-025: Objectives Are Purposes, Subjects Are Health Domains

- **Status:** Accepted and implemented.
- **Context:** The P4 brief listed Malaria, HIV and TB as assessment objectives. Those already existed as health domains.
- **Decision:** Objectives carry purpose only — Baseline, Accreditation, Supportive Supervision. Subjects stay in health domains. "Malaria" is not an objective; a Baseline applied to Malaria is.
- **Rationale:** Carrying the same concept in both entities would leave a user unable to tell which to pick and would make objective mapping circular.
- **Preserved:** The familiar name survives as an Objective Preset — "Malaria Baseline Assessment" preselects the objective, health domains, template and lenses. A preset is a saved combination, not a third entity.
- **Test contract:** A test asserts no health-domain subject appears in the objective catalogue.

### DEC-2026-07-20-026: Measurement Domains And Analysis Lenses Are Different Things

- **Status:** Accepted and implemented.
- **Context:** `DOMAIN_ARCHITECTURE.md` already described the seven `domains` as "analytical lenses". P4 needed the word for a genuinely different concept.
- **Decision:** `domains` are **Measurement Domains** — dimensions that scores roll up into, stored in `domain_scores`. `analysis_lenses` are **Analysis Lenses** — ways of reading results, holding no score. The "lens" wording is retired from the domain documentation.
- **Distinguishing test:** Executive Summary is a valid analysis lens and could never be a measurement domain, because nothing rolls up into it.
- **Boundary:** No schema, scoring or reporting behaviour changed. This is vocabulary, corrected before the master seed made it permanent.

### DEC-2026-07-20-027: The Methodology Publishes As One Coherent Version

- **Status:** Accepted and implemented.
- **Decision:** Objectives, health areas, lenses, insight categories, templates and presets belong to one `methodology_versions` row and publish together, with a content hash over the whole contents. Publication refuses a version whose recommendations do not resolve within it.
- **Rationale:** Objectives recommend lenses and templates. Independent versioning would allow publishing an objective pointing at a lens that does not exist, and the reader would see an empty recommendation with no explanation.
- **Boundary:** Health domains, measurement domains and evidence types are referenced but not validated at publication, because they have their own lifecycle outside the methodology version.

### DEC-2026-07-20-028: A Recommendation Must Name The Finding It Came From

- **Status:** Accepted as the governing constraint for a future phase. No generation is implemented.
- **Decision:** Any recommendation, however generated, must point at a specific score, response, pain point or trend.
- **Rationale:** A recommendation that cannot cite its finding is generic advice. In a health assessment report that is worse than none, because it borrows the authority of the assessment without its evidence.
- **Consequence:** The dormant `recommendations`, `recommendation_rules` and `root_causes` tables were retired. Their single-threshold shape could not express objective, lens, evidence or history as inputs, and extending them would have carried that assumption into the new framework. All three were empty and unreferenced.
- **Deliberately undecided:** Whether generation is rule-based or AI-assisted, where generated recommendations are stored, and whether they are frozen into the report snapshot. Recorded as open rather than guessed.

### DEC-2026-07-20-029: A Subject Earns A Health Domain By Being Assessed On Its Own

- **Status:** Accepted and implemented.
- **Context:** The first catalogue had 12 health domains and pushed everything else into areas under `GENERAL_HEALTH_SYSTEMS`, which grew to 15. Malaria — the highest disease burden in the primary market — was an area, so the seeded Malaria preset mis-filed every malaria assessment.
- **Decision:** A subject becomes a health domain when it is routinely assessed on its own somewhere in the world. Health domains expanded from 12 to 36; `GENERAL_HEALTH_SYSTEMS` reduced to 4 areas.
- **Guard:** A test fails if `GENERAL_HEALTH_SYSTEMS` exceeds 6 areas, because a swelling catch-all is the signal that something inside deserves to be first class.
- **Timing:** Done before the master seed. Afterwards the official question library would already be mapped to the wrong domain, and correcting it would mean a methodology version plus a content migration.

### DEC-2026-07-20-030: Subjects And Measurement Dimensions Are Not Objectives

- **Status:** Accepted and implemented.
- **Context:** Promoting subjects to health domains exposed a collision the original catalogue already carried. Nine objectives named a subject or a measurement dimension rather than a purpose: Health Workforce, Leadership and Governance, Health Financing, Health Information, Infrastructure, Supply Chain, Community Engagement, Digital Health and Health Promotion. Health Promotion had become an exact code collision with the new health domain.
- **Decision:** Removed from the objective catalogue. Each is reached through a purpose narrowed by a health domain or measurement domain, with an objective preset preserving the familiar entry point.
- **Rationale:** This is DEC-025 applied consistently. Excluding Malaria while admitting Health Workforce would have been the same mistake with a different label.
- **Test contract:** Two tests, in both directions. The health-domain check runs against the whole table rather than a fixed list, so a future promotion cannot silently reintroduce a collision.

### DEC-2026-07-20-031: The Catalogue Seeder Reconciles Rather Than Only Adds

- **Status:** Accepted and implemented.
- **Context:** The seeder used `updateOrCreate` throughout, so it could only add or amend. Moving areas out of `GENERAL_HEALTH_SYSTEMS` left the old rows behind, and the database silently stopped matching the catalogue file.
- **Decision:** After seeding, entries absent from the catalogue are deleted from the draft version.
- **Safety:** Pruning only ever runs against a draft. A published methodology is immutable and the seeder returns before reaching this point.
- **Test contract:** A test inserts a stray entry, re-seeds, and asserts it is gone.

### DEC-2026-07-20-032: Financing Is Staged, Not Yet In Force

- **Status:** Partially implemented; adoption deferred.
- **Context:** Financing is a WHO health system building block with no measurement domain, so a financing weakness cannot roll up and be compared across programmes the way a workforce weakness can.
- **Decision:** `FIN` is added to the `domains` master list. It is inert — nothing maps to it and nothing scores it — because a measurement domain only takes effect once a published domain taxonomy version contains it.
- **Why not further:** Adopting it needs a new taxonomy version to be created and published, and Platform Admin has no publish control for domain taxonomies, only browse. Creating a draft version nobody could publish would have been worse than staging the domain and recording the gap.
- **Consequence:** The WHO building blocks remain incomplete in scoring until a taxonomy publish action exists. Recorded as debt.

### DEC-2026-07-20-033: A Measurement Domain Cannot Be Published Into Inertness

- **Status:** Accepted and implemented.
- **Context:** `FIN` was added to the measurement domain master list but no published taxonomy version defined it, so it carried no scores and appeared in no report while looking active in the domain list. There was also no way to fix that: publish and supersede services existed, but nothing created a new version and no route invoked either.
- **Decision:** `startNewVersion()` copies the version in force into a draft and adds a stub for every undefined measurement domain. Publication refuses any version that leaves a domain undefined, and supersedes the previous version so exactly one is ever in force. Both actions are audited and reachable from Platform Admin.
- **Rationale:** A governance object that is half-active is worse than one that is absent, because the list implies it works.
- **Boundary:** Superseding does not touch published frameworks or existing reports. Framework content is frozen at publication and `domain_scores` records the taxonomy version and hash it was measured against, so only new mappings use the version now in force.
- **Result:** Taxonomy v2 publishes all eight WHO building blocks including Financing. v1 is superseded and stays on record.

### DEC-2026-07-20-034: Nothing Exists Only Because It Was Seeded

- **Status:** Accepted and enforced by command and test.
- **Decision:** Every methodology entity must be reachable: directly selectable, recommended through a relationship, or a participant in the diagnostics pipeline. `php artisan methodology:validate` checks this and fails the build if anything is unreachable or any reference does not resolve.
- **Found on first run:** Seven objectives suggested nothing and appeared in no starting point, so choosing one left the user at a blank page. Five templates were recommended by nothing, making them findable only by browsing the whole catalogue — the same as not existing, for most users. All twelve now have relationships.
- **Rationale:** A catalogue entry nobody is routed to will eventually be found by an administrator, not understood, and not trusted.
