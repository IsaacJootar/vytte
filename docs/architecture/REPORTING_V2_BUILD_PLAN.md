# Reporting Module v2 — Approved Build Plan

Status: **approved**, in build. This document is authoritative — the app must tally with it.
It upgrades the R1–R8 reporting skeleton into a deep reasoning engine, streamlining the
original vision where it was oversized without dropping its intent.

Related: [REPORTING_INTELLIGENCE_BLUEPRINT.md](REPORTING_INTELLIGENCE_BLUEPRINT.md) (the R1–R8
foundation this builds on).

---

## Principles

- **Not shallow, not rushed.** Depth over surface. Each phase is fully built, tested,
  committed, and pushed before the next; then work stops for approval.
- **Streamline, don't ignore.** Where the original brief was oversized (20 report types, 11
  lenses), it is reduced and merged — never silently dropped.
- **Deterministic core, AI on top.** Every number, finding, and recommendation is computed
  deterministically and frozen. AI only retells the frozen structure and may never invent.
- **AI provider is OpenAI (ChatGPT).** Behind the `AiChatClient` interface
  (`app/Services/Ai/`), configured via `OPENAI_API_KEY` / `OPENAI_MODEL` (default `gpt-4o`).
  Optional: with no key the AI features are absent and the deterministic report is unaffected.

---

## Build status

- **P1 — Diagnostics depth: ✅ built.** Failed indicators (frozen into the report payload per
  domain), `RootCauseService`, `RiskService`, `DomainRiskProfile` (criticality + consequence),
  and expected-impact / consequence fields on findings. Wired into `ReportComposer.intelligence()`
  (`root_causes`, `risks`) and rendered on the results page. Engine version `vytte-reporting-2.0`.
- **P2 — Insights engine: ✅ built.** `InsightCatalog` (the 21 governed categories as a pure
  constant, verified against the seeded `insight_categories` table by `InsightCatalogTest`).
  `InsightService` rebuilt to classify each finding into the real categories — one weak domain
  can surface as Weakness + Low-Performing + Pain Point + Systemic Issue + a domain-specific
  Risk + Strategic Priority at once. Rendered as an Insights section on the results page.
  Trend-only categories (emerging/decline/no-change) arrive with P4.
- **P3 — Lens engine + recommendations: ✅ built.** `LensCatalog` — 7 lenses (EXECUTIVE,
  OPERATIONS, QUALITY, RISK, COMPLIANCE, PROGRAMME_EFFECTIVENESS, EFFICIENCY) each wired to a
  seeded `analysis_lens` (verified by `LensCatalogTest`) and each declaring the domains it
  foregrounds, the insight categories it leads with, and its ordering. `ReportComposer.throughLens`
  now *reinterprets* — the Clinical lens ignores financing, the Value lens leads with strengths —
  rather than re-sorting. `InterventionLibrary` (curated action per domain × severity) makes
  `RecommendationService` contextual: specific interventions aimed at the concrete failing items,
  carrying expected impact; the citation rule is preserved. Default lens is EXECUTIVE.
- P4–P7: pending.

## Phases

| Phase | Module | Delivers | Depends on |
|---|---|---|---|
| **P1** ✅ | Diagnostics depth | Failed indicators, root-cause layer, risk objects, consequence ("what if nothing changes"), priority, light dependencies, expected impact | — |
| **P2** ✅ | Insights engine | Real classification into the 21 seeded insight categories: strengths, weaknesses, priority areas, quick wins, pain points, systemic issues, domain risks, good practice | P1 |
| **P3** ✅ | Lens engine + recommendations | 7 reinterpreting lenses (wired to seeded analysis lenses); contextual recommendations + a curated intervention library | P1, P2 |
| **P4** | Trend & progress depth | Assessment typing (baseline/midline/endline/follow-up); resolved/persistent/new/regressed matching; target/goal tracking; in-tenant benchmarking | P1 |
| **P5** | Visualisation | Radar, heat map, trend line, risk matrix, comparison tables, question drill-down (self-contained SVG/CSS) | P1–P4 |
| **P6** | AI products | Executive briefing, diagnostic summary, root-cause narrative, donor/clinical/operational summaries (OpenAI, boundary-enforced) | P1–P3 |
| **P7** | Delivery | Email report, scheduled reports | P5 |

---

## Scope decisions — what is built, streamlined, and deferred

### Diagnostics engine (P1)
**Build deep:** findings tied to the actual failed questions/indicators (Failed Indicators);
rule-based **Root-Cause** layer grouping correlated failures; **Risk objects**
(severity × domain criticality); **Consequence** statements (what happens if nothing changes);
**Priority** ranking; light **Dependencies** (e.g. data-gap blocks scoring); **Expected Impact**
as qualitative High/Med/Low from score headroom.
**Reduce:** *Failed Standards* → folded into Failed Indicators (no separate standards library exists).

### Insights engine (P2)
**Build real:** actually use the 21 seeded insight categories with polarity. Produce major
strengths/weaknesses, priority areas, outliers (far from mean), capacity gaps, bottlenecks,
hidden risks (domain looks fine but hides a critical failed indicator). Trend-based insights
(emerging/positive/negative) arrive with P4.

### Lens engine (P3)
**Reinterpret, don't re-order.** Reduce 11 lenses → **7**: Executive, Operational,
Clinical/Quality, Risk, Compliance, Programme, Donor (merge Quality→Clinical, Capacity→
Operational; defer Research & Community). Each lens foregrounds different domains, language,
and insight types, wired to the seeded analysis lenses.

### Recommendation engine (P3)
**Make contextual:** recommendations consider objective, health domain/area, measurement
domain, failed-indicator evidence, risk, and target gap — not just domain+severity. Build a
**seeded intervention library** (curated statements per domain × severity × failure pattern).
Keep the citation rule: every recommendation names the finding it came from.

### Trend & progress (P4)
**Build:** assessment typing; resolved/persistent/new/regressed issue matching across runs;
target/goal tracking (current-vs-target).
**Defer:** auto quarterly/annual/rolling cadence (typing + chronological covers beta).

### Benchmarking (P4)
**Build in-tenant:** facility-vs-facility (same workspace), department comparison,
current-vs-previous, current-vs-target.
**Defer:** anonymous cross-tenant national-average benchmarking (the multi-tenancy exception).

### Visualisation (P5)
**Build (self-contained SVG/CSS, no external libraries):** radar (domain profile), heat map
(domain × severity), trend line, risk matrix, comparison tables, question drill-down.
**Defer:** geographic maps (need per-facility geo-coordinates + a mapping engine).

### AI layer (P6)
**Build distinct products over the frozen intelligence:** executive briefing, diagnostic
summary, root-cause narrative, donor/clinical/operational summaries — prompt variants, all
cite findings, all via OpenAI behind `AiChatClient`.
**Defer:** risk/improvement forecasts (predictive — need cycles of history).

### Exports & delivery (P7)
**Add:** email report, scheduled reports. **Keep:** interactive, PDF, Word, Excel, PPT, CSV.
**Defer:** public API (post-beta).

---

## Report catalogue — the streamlined model

The original ~20 named report types collapse into **lens × scope × template**, of which v2
builds the **lens** axis (7 lenses) and **question drill-down** scope. Additional scopes
(department) and templates (one-pager, donor) grow from the same engine, not as separate
generators.
