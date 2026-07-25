# Activation & Hub — Approved Build Plan

Status: **approved**, in build. Authoritative — the app must tally with it.

Makes Vytte clear and guiding whether or not the workspace has data: one golden path, one
adaptive home, zero dead-ends. Same rhythm as the reporting phases — each phase built, tested,
committed, docs in lockstep, then work stops for approval.

---

## Principles

- **One golden path:** Create Assessment Target → Run assessment → See report → Act → Track → Compare.
- **Every screen answers "what's my next step?"** Every empty state is a doorway with one primary
  action, never a wall.
- **Hub pages keep their scaffolding.** On the dashboard and hub pages the stat/start cards are
  **always present, showing zero when there is no data** — the shell never looks empty or broken.
- **Reuse the reporting engine.** All previews read the intelligence already produced (findings,
  risks, expected impact, AI) — no new engine work.

## Naming & navigation (decided)

- The assessed unit is the **Assessment Target** (a community, hospital, clinic, programme, or
  department — never assume "facility"). A Project holds one Assessment Target over time.
- The comparison view is **Compare** — "how your Assessment Targets score against each other" —
  **folded into the Reports hub**, not a lonely nav item. "Benchmark" and "facility" are retired.

**Sidebar (revised per owner preference):**
`Dashboard · Projects · Assessments · Reports · Compare · Actions`

- **Reports** is the data hub: report previews (score, biggest issue, top risk) + quick actions.
- **Compare** is its own sidebar page (kept, per owner preference) — how the workspace's
  assessment targets rank against each other — with a guided empty state, so it never dead-ends.
  (Progress stays contextual — a trend is one target over time — surfaced inside Reports/Dashboard.)
- **Actions** is promoted to a **workspace-level hub**: every action across all projects, with
  filters (project / owner / status / overdue). Per-project action lists still exist; the hub is
  the home. Rationale: "what do I need to do" naturally spans all projects.

## Phases

| Phase | Module | Delivers | Depends on |
|---|---|---|---|
| **A1** | Guided empty states + wayfinding | One reusable `x-empty-state` (icon · one sentence · one primary button = the next action · optional greyed preview) applied to every list/empty page so none dead-ends | — |
| **A2** | Adaptive Dashboard (the home) | Cards always present (zeros when empty) + a 3-step activation checklist when empty; KPI tiles + a "Latest report" intelligence preview (top finding, biggest risk, #1 action) + "Needs attention" + recent activity when there is data; links to the hubs | A1, reporting engine |
| **A3** | Reports hub (+ Compare + progress) | Reports becomes the central data/stats hub: a rich card per completed assessment (score, band, top finding, top risk, expected impact, quick actions) + filters; **Compare folded in**; per-target **progress previews**; retire the Benchmark nav item | A1, reporting engine |
| **A4** | Actions hub | Promote Actions to a **workspace-level sidebar hub** aggregating every action across projects, with filters (project / owner / status / overdue); per-project lists remain | A1 |
| **A5** | Plain-language & naming pass | Retire "Benchmark"/"facility" → "Compare"/"Assessment Target"; final sidebar to `Dashboard · Projects · Assessments · Reports · Actions`; sweep every label/button to action-first plain language | A1–A4 |

## Build status

- **A1 — Guided empty states: ✅ built.** Reusable `x-empty-state` component (icon · one
  sentence · one primary CTA · optional greyed preview). Applied to Reports, Benchmark,
  Projects, Assessments, Actions, and project Progress — every empty page now routes forward
  instead of dead-ending. Copy uses "Assessment Target".
- **A2 — Adaptive Dashboard: ✅ built.** The home now leads a new workspace through a 3-step
  activation checklist (Create project → Run assessment → See report), shown until the first
  report exists; once there is data it previews the latest report's intelligence (biggest
  issue, top risk, do-next) plus open/overdue action counts. Stat cards stay present (zeros
  when empty). `DashboardController` computes activation state, action attention, and the
  latest-report preview via the reporting engine.
- **A3 — Reports hub: ✅ built.** Reports is now the central data hub: each report card shows
  its intelligence at a glance (biggest issue + top risk, read from the frozen snapshot) and
  quick actions (View · PDF · Progress · Actions). **Compare is its own sidebar page** (kept per
  owner preference, renamed from "Benchmark" to "Compare", with a guided empty state) — showing
  the ranked league table + per-area averages. Progress is reachable per report card.
- A4–A5: pending.
