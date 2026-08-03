# Assessment Governance Implementation Audit

Date: 2026-08-03

## Verdict

The approved beta governance product is implemented as one connected workflow. It is not only a data model: each authoring decision has a labelled screen, each runtime rule is consumed by collection and reporting, and each immutable contract has focused tests.

This audit separates that shipped contract from later ecosystem expansion. A signed external package format, additional specialist response types, psychometric field-test analytics, predictive forecasting, and adaptive item pools are not silently described as complete. They extend the platform after stable production data and an approved interoperability consumer exist; they are not required to create, govern, score, run, and report the assessments currently supported by Vytte.

## Four implementation phases

| Phase | Delivered product | Primary access |
|---|---|---|
| 1. Foundation | Accountable publishers, provenance, distribution, immutable versions, independent trust signals, audit | Platform Admin → Governance Studio → Publisher & source / Review; Platform Admin → Publishers |
| 2. Universal assessment engine | Reusable and original questions, sections, multi-select, numeric bands, qualitative answers, missing states, branching, one scoring model, simulation | Governance Studio → Structure / Questions / Logic / Scoring / Test |
| 3. Governance Studio | Nine-step authoring, expert contributions, human review assignments, deterministic lint, optional source-grounded AI advice, immutable publication | Platform Admin → Governance Studio; Workspace → Contribute Questions; Platform Admin → Contributions |
| 4. Unified reporting and premium intelligence | One frozen report, score views, exact issues, stable issue tracking, comparisons, report tailoring, purposeful visuals, AI narrative products, governed sharing | Workspace → Reports; Assessment hub → View report; Portfolio / Actions for follow-up |

## Fourteen commitments and evidence

| # | Commitment | Implemented behavior | Where a user finds it | Technical evidence |
|---|---|---|---|---|
| 1 | Vytte governs process, not universal clinical truth | Publisher owns purpose and methodology; Vytte records identity, provenance, validation, review, versioning, and audit | Governance Studio → Publisher & source | `content_publishers`, `ContentPublisherService`, frozen publisher manifest |
| 2 | Content origin never decides scoring eligibility | Original, reused, partner, or promoted questions can enter the same primary model when the publisher deliberately scores them | Governance Studio → Questions → Scoring & evidence | `scoring_model_versions`, `scoring_item_rules`, `ScoringModelService` |
| 3 | Question meaning and score interpretation are separate | Immutable question versions hold semantics; immutable item rules hold scoring interpretation | Question Library; Governance Studio → Scoring | `question_versions` and `scoring_item_rules` have separate version contracts |
| 4 | Publishers, distribution, and trust are independent | Publisher verification cannot imply source, subject, scoring, field-test, translation, or benchmark review | Platform Admin → Publishers; Governance Studio → Review | `content_governance_claims`; eight independent claim types |
| 5 | Human review has separation of duties | One admin assigns, another submits evidence, and someone other than that reviewer approves or requests changes | Governance Studio → Review | `content_review_assignments`; assignment/submission/decision audit events |
| 6 | Experts can contribute without contaminating the public bank | Workspace proposals remain private; accepted work creates a separate private, unscored draft with attribution | Workspace → Contribute Questions; Platform Admin → Contributions | `content_contributions`, `ContentContributionService` |
| 7 | Authors have one understandable workflow | Purpose → Publisher & source → Structure → Questions → Logic → Scoring → Review → Test → Publish; every step is clickable | Platform Admin → Governance Studio | `AssessmentBuilderController::STEPS`; dedicated routes and views |
| 8 | Responses preserve meaning | Single select, Likert, multi-select, numeric, and written answers are publishable; missing, unknown, declined, not observed, not assessed, and not applicable are explicit states | Governance Studio → Questions; staff and public assessment runners | `ResponseInputContract`, typed response envelope, both Livewire runners |
| 9 | Logic is safe and reproducible | Branching references only earlier frozen questions; one evaluator controls visibility, completeness, scoring, and preview | Governance Studio → Logic / Test | `AssessmentLogicService`, preview simulator, publication checks |
| 10 | Scoring is one immutable pipeline | Publisher declares construct, direction, missing policy, item rules, weights, numeric bands, multi-select method, domains, and criticality | Governance Studio → Scoring; per-question Scoring & evidence | versioned scoring models and frozen comparison signatures |
| 11 | Missing data never becomes a silent zero | Not applicable leaves the denominator; other missing states are disclosed or block completion according to the frozen policy | Governance Studio → Scoring; report measurement disclosure | `ScoringService`, `RespondentSubmissionService`, report coverage view |
| 12 | One report can show different honest measurements | Primary, domain, common-core where approved, context/need, qualitative, critical, coverage, limitations, evidence, and methodology coexist without being merged | Workspace → Reports → View report | `UnifiedReportViewService`, immutable report snapshot |
| 13 | Comparisons and progress cannot overclaim | Exact matching signatures permit deltas; incompatible instruments remain descriptive; exact issues retain stable keys and become new, persistent, or resolved | Reports → Progress / Compare / Benchmark; Actions | `ComparisonEligibilityService`, `IssueTrackingService`, `TrendService` |
| 14 | Intelligence assists decisions without replacing accountability | Deterministic diagnostics, recommendations, seven lens presets, tailored report focus, six grounded AI products, charts, exports, and sharing all read frozen findings; AI cannot approve or publish | Report page; Governance Studio → Review | report snapshot services, `ReportComposer`, `AiProductCatalog`, `AssessmentAuthoringAssistant` |

## Navigation contract

### Platform Admin

- **Governance Studio** — the main nine-step authoring and publication workflow.
- **Question Library** — stable identities and reusable question versions.
- **Publishing** — governed catalogue releases.
- **Contributions** — review expert proposals and promote accepted work to a governed draft.
- **Publishers** — accountable publisher identities.
- **Advanced Tools** — deep methodology and version inspection; not required for ordinary authoring.

### Workspace users

- **Projects / Assessments** — choose Comprehensive or Focused, configure, collect, and finalize.
- **Contribute Questions** — submit expert content privately for review.
- **Reports** — open the unified report, tailor its focus, export, share, compare, and track progress.
- **Actions** — turn exact findings into owned follow-up work.

## Expansion boundary

The following are additive specialist modules, not missing portions of the shipped workflow:

- signed third-party import/export standards packages, when an actual exchange partner and schema are approved;
- date/time, ranked-choice, computed-indicator, and nested repeating response types, each added only as a complete renderer-validation-storage-snapshot-report slice;
- psychometric field-test analytics after enough consented response data exists;
- predictive forecasts after sufficient comparable history exists;
- adaptive item selection only from an approved versioned pool under a validated selection policy;
- cross-tenant benchmarking only with explicit consent, privacy thresholds, and governance approval.

None may bypass immutable versions, human publication, tenant isolation, comparison eligibility, or the one-report architecture.
