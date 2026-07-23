# Response-Collection Platform — Review & Decisions

Vytte is a response-collection and health-intelligence platform, not an assessment builder. This records the architectural review that confirmed the model already supports that, the three decisions taken, and the Distribution & Monitoring pass that wired the one real gap.

## The finding

The respondent-first *engine* already existed — projects holding many assessments, respondent tokens, public response sessions, consent, aggregation, share links, the Builder quarantined under `/admin`. What was missing was not architecture but **one unwired action (publish) and two read-views (monitoring, operational dashboard)**. A re-surfacing, not a rebuild.

## Decisions

### 1. Project = one Target

A project holds **one target** — one facility, community, school or programme — and all the assessments run on it over time. Kept as-is.

The deciding reason is reporting: Trend, Progress and Benchmarking all compare *the same target over time*. A project holding many targets would make "how is this project trending?" ambiguous. A portfolio of facilities is served by **multiple projects**, and a future optional **Programme** grouping layer above projects — additive, parked for the reporting phase.

### 2. Assessment = one distributable collection activity, with a wired lifecycle

An assessment is one data-collection activity, run once on the project's target. To measure again you create the next assessment in the project. Structure unchanged.

The lifecycle is now wired:

```
DRAFT  →  PUBLISHED  →  (collecting)  →  CLOSED  →  COMPLETE
set up     open for       responses      no new       scored,
           responses      flow in        responses    report ready
```

Two status fields, each with a distinct job, kept as-is (no migration):
- `publish_status` (DRAFT/PUBLISHED) — is it open for responses?
- `status` (IN_PROGRESS/COMPLETE) — is collection finished and scored?
- `closed_at` — collection window closed, reversible until finalisation.

**Publishing gates distribution.** A draft cannot generate respondent links and the public runner will not accept answers. This is what makes publishing a first-class act rather than a silent flag. Self-fill by the owner (the `run`/`submit` path) is intentionally *not* gated — a single self-inspection can go draft → complete without publishing, because publishing is about *sending it out*, not about answering it yourself.

### 3. Respondents — one, several, or many, off the same assessment

Three collection modes, all from one assessment:
- **Single** — one person (the org's own inspector) self-fills.
- **Consensus** (multi-respondent) — several people answer about the same facility; answers average into one score to reduce single-informant bias. Built.
- **Survey / population** — many people each give their own data point; you want the distribution, not an average. Collection is identical to consensus; the **distribution view is a reporting-phase addition**, not a collection change.

The organisation stays in control of who counts: every response session can be marked eligible, excluded or test before finalisation.

## What the Distribution & Monitoring pass wired

- **Publish / Close / Reopen actions** — `AssessmentController@publish|close|reopen`, with model helpers `markPublished`, `markClosed`, `reopen`, `isCollecting`, `isClosed`. Audited.
- **Distribution gate** — respondent-link creation and the public runner both check `isCollecting()`. Closing an assessment stops new answers even on links already shared.
- **Monitoring view** — `assessments.monitor`, read-only over `public_response_sessions`: started, in progress, completed, completion rate, eligible vs minimum, per-respondent status.
- **Operational dashboard** — the workspace homepage now leads with the daily work: awaiting publication, collecting now, responses in — over the same tables as the outcome figures.
- **Lifecycle-aware UI** — the project page shows each assessment's state (Draft / Collecting / Closed / Complete) and offers the right action (Publish, Monitor, Continue).

No migration. No new core table. `closed_at` already existed on `assessments`.

## Ready for the next phase

Diagnostics and Reporting now read a clean publish → collect → monitor → finalise stream. The survey-distribution view and the optional Programme rollup both belong to that phase, recorded here so they are not forgotten.
