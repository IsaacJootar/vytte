# Reporting, Diagnostics & Intelligence — Architecture Blueprint

Design only. No implementation. This is the blueprint for the phase that turns measured responses into actionable health intelligence.

It takes the product brief and applies engineering judgment: what to build, what to collapse, what to defer. The sections marked **KEEP / COLLAPSE / DEFER / DISCARD** say plainly where the brief's ambition is right and where it would build twenty things when it needs one.

---

## 1. The governing principle (the spine of the whole phase)

**Everything reads the frozen report snapshot; nothing recalculates. Derived intelligence freezes into the snapshot at finalisation.**

Scoring already froze the numbers. Diagnostics, insights and recommendations are *derived* from that snapshot and must freeze into it too. A report sent in March must read identically in June, even after the rules improve in May. Regenerating with better rules produces a **new report version**, never a mutation.

`assessment_report_snapshots.payload` (JSON) already exists. It is extended to hold the derived intelligence. This is the single most important decision in the phase, and it is settled here.

**The one exception — actions are living, not frozen.** See §9. Everything a *report* asserts is frozen; everything a *user does about it* is mutable. Keeping these apart is the cleanest line in the whole design.

---

## 2. The engine stack

Six layers, each a service with one responsibility and a defined contract. Lower layers never know about higher ones.

```
Measurement   → scores, domain rollups, calibration, critical failures   [BUILT — ScoringService]
Diagnostics   → findings: what is wrong, how bad, why it matters          [rule-based]
Insights      → classified interpretation using the 21 insight categories [rule-based]
Recommendations → cited actions: each names the finding it came from      [rule-based]
Intelligence  → lens-shaped reports, trend, benchmarking                  [composition]
AI            → narrative over the structured output above                [enhancement, last]
```

**The rule that makes this safe:** each layer is deterministic and testable on its own. AI sits *on top*, consuming the structured output — it never sits *inside* the diagnostic path. The rule-based version of every layer is both the product's floor and the AI's test oracle. If AI is unavailable, misconfigured, or wrong, the deterministic report still stands.

---

## 3. Frozen vs living — the distinction that prevents the mess

| Frozen at finalisation (in the report snapshot) | Living (own tables, user-edited) |
|---|---|
| Scores, domain scores, calibration | **Actions** (owner, due date, status) |
| Findings, pain points, critical failures | Action progress and verification |
| Insights (classified) | Comments / notes on a report |
| Recommendations (as generated) | Benchmark cohort membership |
| The lens views computed at finalisation | Report share/access grants |

A report is a photograph. An action plan is a to-do list that changes daily. Conflating them — storing mutable action state inside an immutable snapshot — is the classic mistake this table exists to prevent.

---

## 4. Diagnostics architecture

Diagnostics turn scores into **findings**. A finding is the atomic unit of the whole phase: *this specific thing, this bad, for this reason, and here is the evidence*.

A finding is generated deterministically from the snapshot:

| Field | Source |
|---|---|
| Subject | which question / indicator / domain |
| Type | Weakness, Gap, Critical Finding, Pain Point, Data Gap… (insight category) |
| Severity | derived from score band + criticality + whether it is a critical failure |
| Evidence | the exact recorded answer(s) — traceable, not inferred |
| Measurement domain | where it rolls up (Governance, Workforce…) |

**Root causes are NOT a stored, pre-computed table** — we retired that model in P4. A root cause is a **grouping of related findings under the Root Cause lens**: when governance findings cluster across several departments, that *pattern* is the cause, surfaced at read time, not stored as a guess. This is why the retired `root_causes` table stays retired.

- **KEEP:** findings, pain points, evidence, failed indicators, severity, priority.
- **COLLAPSE:** "Failed Standards" and "Failed Indicators" are the same finding type at different granularity — one mechanism.
- **DEFER:** "Dependencies", "Expected Impact" — these need either an action model (§9) or historical data (§10); they arrive with those, not before.

---

## 5. Insights architecture

Insights are findings **classified and ranked for a human**. The 21 insight categories are already seeded and carry `polarity` (good/bad/neutral) and `is_diagnostic`. The engine maps findings to categories by rule:

- domain score < 45 → **Weakness**; a required thing absent → **Gap**
- flagged option → **Pain Point**; critical failure → **Critical Finding**
- calibration NOT_CALIBRATED/PARTIAL → **Data Gap** (the honesty category)
- top-scoring domains → **Strength / High-Performing Area**

**KEEP** the brief's list (Major Strengths, Hidden Risks, Outliers, Bottlenecks…) — they map onto existing categories or are trivial additions. **DISCARD** the idea that insights are a separate store: an insight is a *view* of findings under a polarity+ranking rule, frozen with the report.

---

## 6. Recommendations architecture

The governing rule, unchanged from `RECOMMENDATION_FRAMEWORK.md`: **a recommendation must name the finding it came from.** No cited finding, no recommendation. Generic advice is banned by construction.

Generation reads the eleven inputs already designed (objective, lens, scores, responses, pain points, evidence, history, benchmark, risk, context) and emits recommendations that each point at a finding, carry a type (Operational, Clinical, Training…) and a horizon (Immediate/Medium/Long — derived from effort and dependency, not severity).

- **Rule-based first**, deterministic, testable.
- **Lens-dependent:** the same assessment yields a different recommendation set per lens, because the lens is an *input*, not a filter.
- **Frozen** with the report; regenerating = new report version.

---

## 7. Reports = lens × template — the brief's twenty report types collapse to one engine

This is the biggest judgment in the blueprint.

**The brief lists ~20 report types. Do NOT build 20 report generators.** A report is not a thing you code once per name. A report is:

```
Report  =  one Diagnostic Result  ×  an Analysis Lens  ×  an audience template
```

- **Executive Report** = the Executive lens over the findings.
- **Clinical Report** = the Clinical lens.
- **Risk Report** = the Risk lens.
- **Donor Report** = the Executive lens with a donor template skin.

All 20 named "types" are **combinations of the 20 lenses already seeded and a handful of layout templates.** Build *one* report engine and a lens/template registry. Adding a "new report type" becomes a config row, not a code change — exactly as the methodology layer made "new assessment type" a catalogue entry.

- **COLLAPSE:** Executive/Operational/Clinical/Programme/Quality/Compliance/Risk/Capacity/Research/Donor/Community → the 20 analysis lenses.
- **COLLAPSE:** Baseline/Endline/Trend → the Trend/Progress lenses over history (§10), not separate report code.
- **KEEP as distinct *scopes*, not types:** Facility / Department / Question drill-down — these change *what data the report covers*, not how it's read. Scope × lens is the real matrix.
- **DEFER:** Respondent Analysis is the monitoring/distribution view we already scoped; Comparative/Benchmark reports need §10.

### The real report catalogue

| Axis | Values |
|---|---|
| **Lens** (how it reads) | the 20 seeded analysis lenses |
| **Scope** (what it covers) | whole assessment · department · question drill-down |
| **Horizon** (over time) | single · trend · benchmark |
| **Template** (who it's for) | standard · executive one-pager · donor · clinical |

Every named report in the brief is a coordinate in this grid. That is the catalogue.

---

## 8. Trend, Progress & Benchmarking

**Trend / Progress** — the "same target over time" lenses. The project-holds-one-target decision is what makes these unambiguous. Trend needs ≥2 finalised assessments of the same target (composition-hash matched, so like compares with like). Progress reads against **agreed actions** (§9) — which is why Progress Tracking depends on the action model existing first.

**Benchmarking — the one architecturally hard item, flag it loudly.** Every other layer is workspace-scoped. Benchmarking is inherently **cross-tenant**: "your facility vs the national average" means reading across workspaces. This is the single biggest risk in the phase. It must be:
- **opt-in** (a workspace chooses to contribute),
- **anonymised** (no workspace can identify another's facility),
- **aggregate-only** (cohorts, not rows),
- a **deliberate, audited exception** to multi-tenancy — designed once, not bolted on.

Recommend building benchmarking **last** (R6), on its own, after single-report intelligence is proven, because it is the only piece that touches the tenancy boundary.

---

## 9. Action Management — the one genuinely new living domain

This is where a report stops being a document and becomes organisational learning. It is **the only substantial new persistent model** the phase needs.

An **Action** is created *from* a recommendation but lives independently:

| Field | Note |
|---|---|
| source recommendation | the finding it traces to (frozen) |
| owner | a workspace member |
| priority, due date | set by the org |
| status | Open → In Progress → Done → Verified |
| progress notes, evidence | living |
| verification | closed by whom, when |

Actions are **mutable, workspace-scoped, and the input to Progress Tracking**: next assessment's Progress lens reads "were the agreed actions done?" This is PS-4 from the backlog, and it lands here.

**KEEP** the brief's action fields (owner, priority, due, status, evidence, verification, escalation) — they are right. This is real new table work: `assessment_actions` (+ maybe `action_updates`). The only new migrations in the phase live here.

---

## 10. AI layer — contracts and the hard boundary

AI is the **last** layer and consumes the structured output of §4–§8. It never participates in scoring or deterministic diagnostics.

Outputs (narrative only): Executive Narrative, Diagnostic Summary, Root Cause explanation, Strategic Recommendations phrasing, Donor/Clinical/Operational summaries.

**The boundary, stated as a rule:** AI may *rephrase, summarise and prioritise* structured findings; it may not *invent* a finding, a score, or a recommendation that has no underlying structured source. Every AI sentence must trace to a frozen finding — the same citation rule that governs recommendations. This makes AI output auditable and keeps the deterministic report as the source of truth.

- **DEFER hard:** Risk Forecast, Improvement Forecast — these are *predictive* and need longitudinal depth the platform will not have for several cycles. Design the seam, build nothing.
- Uses `claude-sonnet-4-5` per the stack; prompt-level, not model-level, work.

---

## 11. Data-model implications

**Reused, unchanged:** scores, domain scores, report snapshots (payload extended — no new column), analysis lenses, insight categories, pain-point flags, calibration states.

**New (the whole phase's migrations):**
- `assessment_actions` + `action_updates` — the living action domain (§9).
- Benchmark cohort membership + anonymised aggregate store (§8) — cross-tenant, built last, designed with care.

**Extended, not added:** the report snapshot `payload` grows a `diagnostics`, `insights`, `recommendations`, and per-lens `views` section, frozen at finalisation with a content hash. No schema migration — it is JSON.

That is deliberately small. The intelligence is mostly *computed and frozen*, not *stored in new tables*. Only the living things (actions) and the tenancy-crossing thing (benchmarks) need real tables.

---

## 12. Service boundaries

```
ScoringService              (built)   → freezes scores
DiagnosticsService          (new)     → snapshot → findings
InsightService              (new)     → findings → classified insights
RecommendationService       (new)     → findings + context → cited recommendations
ReportComposer              (new)     → lens × scope × template → a report view
TrendService                (new)     → history → longitudinal view
BenchmarkService            (new, last)→ cross-tenant cohorts (audited exception)
ActionService               (new)     → recommendations → living actions
ReportSnapshotService       (extend)  → freezes diagnostics+insights+recs into payload
AiNarrativeService          (last)    → structured intelligence → prose
```

Finalisation orchestrates: Scoring → Diagnostics → Insights → Recommendations → freeze into report snapshot. Lens views and AI narrative are computed on read (from the frozen data) or frozen at finalisation — a decision per view, but always *from* the frozen findings.

---

## 13. Security & permissions

Layer reporting permissions over existing workspace roles; do not invent a parallel system.

| Role | Sees |
|---|---|
| Platform Admin | governs the engine; not customer reports by default |
| Workspace Owner/Admin | all reports, all actions, benchmark opt-in |
| Member | reports for their projects; assigned actions |
| **External Reviewer** (new, light) | a single shared report, read-only, via a scoped link — reuses the existing share-link mechanism |
| Respondent | never sees reports — answers only |

The only new concept is **External Reviewer**, and it is just a read-scoped share link, which already exists. Everything else is the current role model.

---

## 14. Performance — right-sized, not over-built

The brief says "millions of responses." **Design for it; do not build for it in beta.**

The frozen-snapshot principle already does the heavy lifting: a report **reads a pre-computed frozen payload** — no live recompute, no N+1 over responses, cheap at any scale. That is the performance architecture.

- Single-report reads: already cheap (frozen JSON).
- Trend: bounded (a handful of assessments per target).
- **Benchmarking is the only real scale concern** — aggregating across thousands of facilities. Solve it with pre-computed periodic aggregates, not live cross-tenant queries. Built last, so there is time.
- AI: async/queued (Horizon exists), never blocking a report render.

**DISCARD** premature sharding/warehouse design for beta. The snapshot model defers that honestly.

---

## 15. Exports

One report structure, many format adapters. Do not build separate report logic per format.

- **KEEP:** interactive (web), PDF (partially exists).
- **COLLAPSE:** Word / Excel / PowerPoint / CSV are *renderers* over the same frozen report payload.
- **DEFER:** Scheduled / Email reports — depend on email, which is off for beta. Design the seam, build when email goes live.
- API export: read the frozen payload; trivial once the payload is the source of truth.

---

## 16. What I would discard or defer from the brief

| Item | Verdict | Why |
|---|---|---|
| 20 separate report types | **COLLAPSE** to lens × scope × template | one engine, not twenty generators |
| Root-cause as stored table | **DISCARD** | it is a lens over live findings; retired in P4 |
| Risk/Improvement Forecast | **DEFER hard** | predictive, needs longitudinal depth we lack |
| "Millions of responses" build | **DEFER** | snapshot model makes reads cheap; benchmark is the only scale risk |
| Scheduled/Email reports | **DEFER** | email is off for beta |
| Geographic views | **DEFER** | basic geo exists; rich geo is post-beta |
| Respondent Analysis | **MERGE** | it is the monitoring/distribution view already built |

Everything else in the brief is kept.

---

## 17. Implementation phases

Each independently shippable, each verified against PostgreSQL, each committed.

| Stage | Delivers | New tables? |
|---|---|---|
| **R1** | Readable report over the frozen snapshot: scores, domains, calibration honesty, critical failures surfaced | no |
| **R2** | **Diagnostics + Insights engine** — findings classified into the 21 categories. *The load-bearing stage.* | no (freezes into payload) |
| **R3** | **Lens-driven reports** — one assessment read through the 20 lenses; the report engine + registry | no |
| **R4** | **Recommendations** — cited, lens-dependent, frozen | no |
| **R5** | **Action Management** — the living action domain; Progress Tracking reads it | **yes** — actions |
| **R6** | **Trend + Benchmarking** — longitudinal, then the cross-tenant benchmark exception | **yes** — benchmark cohorts |
| **R7** | **AI narrative layer** — over the frozen structured output; deterministic core stays the source of truth | no |
| **R8** | **Export adapters** — Word/Excel/PPT over the one payload | no |

R1–R4 need **zero migrations** — they compute and freeze. Real tables appear only at R5 (actions) and R6 (benchmarks). AI is R7, isolated. This ordering means the whole intelligence core ships before any schema risk.

---

## 18. Decisions needed before R1

1. **Freeze derived intelligence into the report snapshot payload.** (Recommended and assumed above.)
2. **Reports are lens × scope × template, not 20 coded types.** (Recommended.)
3. **Actions are the only new living domain; benchmarking the only tenancy exception, built last.** (Recommended.)
4. **AI is R7, over frozen output, never in the diagnostic path.** (Recommended.)

If these four hold, R1 can begin with no schema change.

---

## 19. Risks

| Risk | Severity | Mitigation |
|---|---|---|
| Building 20 report generators instead of one engine | HIGH (wasted months) | §7 — collapse to lens × template |
| Storing mutable action state in an immutable snapshot | HIGH (data-integrity) | §3 — frozen vs living split |
| Benchmarking breaching multi-tenancy | HIGH | §8 — opt-in, anonymised, aggregate, audited, built last |
| AI inventing findings not grounded in data | HIGH (credibility) | §10 — every AI sentence cites a frozen finding |
| Un-reviewed clinical content driving recommendations | HIGH | PS-1 methodologist review, a launch gate |
| Premature scale engineering | MEDIUM | §14 — snapshot model defers it honestly |
| Trend comparing unlike compositions | MEDIUM | composition-hash match, already in place |

---

## 19a. R6 status — Trend built, Benchmark seam only (deferred for launch)

**Trend / Progress is built.** `TrendService` (`app/Services/Reporting/TrendService.php`)
computes the longitudinal summary — overall score trajectory, per-domain movement between
the latest two composition-matched runs, and **action follow-through** (did the agreed
actions get done?), reading the R5 action plan. Surfaced on the project Progress page. Fully
in-tenant; no schema change.

**Benchmark is deferred — the seam, not the build.** The cross-tenant comparison is designed
but deliberately not implemented before go-live. When it is built, it slots in here without
disturbing anything above it, honouring the §8 contract:

- a `BenchmarkService` alongside `TrendService`, the *only* service permitted to read across
  workspaces, and doing so through a single audited path — never an ad-hoc cross-tenant query;
- **opt-in**: a workspace setting a benchmark-contribution flag is the sole trigger for its
  anonymised data entering a cohort;
- **anonymised + aggregate-only**: cohorts store aggregates (means, counts, bands), never
  workspace-identifiable rows; the store is a new table (`benchmark_cohort_aggregates`), the
  only cross-tenant table in the system;
- **matched cohorts**: comparison is composition-hash + facility-profile scoped, so a facility
  is only ever compared with genuinely like facilities;
- **a deliberate, audited exception**: every cohort read and every contribution is written to
  the audit log, and the tenancy exception is documented here, not discovered in code.

Nothing cross-tenant exists yet. Trend ships; Benchmark is a named, contracted follow-on.

---

## 20. In one paragraph

Build a deterministic engine — Diagnostics → Insights → Recommendations — that reads the frozen assessment snapshot and freezes its output back into an extended report payload. Reports are not twenty coded types but one engine reading that output through the twenty analysis lenses already seeded, at a chosen scope, in a chosen template. Actions are the one new living domain and the bridge to Progress Tracking. Benchmarking is the one tenancy-crossing feature and is built last, opt-in and anonymised. AI is the final layer, narrating the structured output and forbidden from inventing anything the deterministic engine did not find. R1–R4 need no migrations; only actions (R5) and benchmarks (R6) touch the schema. The frozen-snapshot spine makes reports cheap at scale and keeps every report reproducible for the life of the platform.
