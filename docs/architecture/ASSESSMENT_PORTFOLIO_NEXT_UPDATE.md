# Assessment Portfolio: Next Update

**Status:** Local product specification for the next implementation cycle  
**Scope:** Assessment Portfolio only  
**Implementation status:** Implemented. All nine correctness gaps, the six required views, and the
acceptance criteria in this document are built and covered by regression tests.

## Purpose

Assessment Portfolio should help a workspace understand its assessment work across time, domains,
settings, and follow-through. It must be clearly different from **Reports**, which explains one
completed assessment in detail.

The current page safely shows the latest completed result for each setting, grouped by compatible
assessment type. That is a useful foundation, but it does not yet fully deliver the original purpose
of showing progress across time and domains.

The next update should turn Assessment Portfolio into the workspace's long-term management view
without weakening scoring integrity or making unlike assessments appear comparable.

## Audit of the Existing Project Progress Page

Vytte already has a project-specific Progress page at `/projects/{project}/progress`. It should not
be replaced by Assessment Portfolio. It is the correct place to understand one setting over time,
while Assessment Portfolio should summarize patterns across the active workspace and link into this
deeper view.

The cited project, **TULA VANU COMMUNITY**, currently has one completed assessment and no recorded
performance targets, actions, or report schedules. The page therefore cannot yet calculate a real
trend or issue movement. This is a valid early state, but the page should explain it as a baseline and
make the next useful action obvious rather than appearing unfinished.

### What the Existing Page Already Does Well

- restricts access through the project policy and active workspace;
- reads completed assessments rather than unfinished responses;
- shows the assessment history and links back to each report;
- brings together overall score movement, domain movement, action follow-through, targets, and
  reassessment history;
- provides a separate two-run comparison page;
- correctly suppresses deltas on that comparison page when frozen comparison signatures differ;
- handles no-assessment and one-assessment states;
- prevents a comparison from using an assessment belonging to a different project.

These capabilities should be preserved and refined rather than rebuilt as a second progress system.

### Important Correctness Gaps

1. **The headline trend does not use the canonical comparison guard.** `TrendService` groups runs by
   `composition_hash`, while the dedicated comparison page uses the stronger frozen
   `comparison_signature`. A matching composition does not by itself prove that scoring and
   methodology are compatible. Every progress calculation should use one shared comparison-series
   service based on the frozen comparison signature, with only an explicitly labelled legacy rule
   where historical data predates signatures.
2. **The visible trend chart may mix incompatible runs.** The summary decides whether the latest
   composition has at least two matching runs, but the chart then plots every completed scored
   assessment in the project. The chart must plot only the selected compatible series.
3. **Domain Score History places all completed assessments in one matrix.** It does not separate
   incompatible assessment series, so visual proximity can imply a trend that Vytte is not permitted
   to claim. Domain history must be grouped by compatible series and governed domain identity.
4. **Issue movement is currently domain-band movement, not exact issue tracking.** Labels such as
   “Resolved,” “New issues,” and “Still weak” are derived from domain score bands. They do not prove
   that an exact finding disappeared, persisted, or improved. Domain movement should retain domain
   language; exact issue states must use stable finding or question identities and frozen issue
   traces from the reports.
5. **Targets are too broadly attached.** A project/domain target can currently survive across unlike
   assessment methods and appear to measure the same goal. A target must be tied to the compatible
   assessment series and, for domain targets, the governed domain measurement contract it applies
   to.
6. **The page promises more than one run can provide.** With one completed assessment, Vytte should
   call it a baseline, show what was measured, and explain that a compatible follow-up is needed.
   “Run the same assessment again” is not sufficient if a newer template or scoring version would
   create an incompatible series.
7. **The page is overloaded with management forms.** Trends, actions, targets, scheduled emails,
   assessment labelling, domain history, and comparison selection compete on one long page. The
   primary view should explain progress; editing targets, scheduling delivery, and changing
   descriptive assessment labels should use focused, progressively disclosed controls.
8. **Some language is obsolete or technical.** Plan messaging still refers to maturity trends, the
   comparison cards expose `L1`–`L5`, and “Band” competes with the performance-stage label. Generic
   assessment progress should use the approved performance-stage names and plain-language
   comparison status.
9. **Operational-domain filtering is inconsistent.** The controller limits the history matrix to
   `is_operational` domains, while the trend service intentionally reads domains that actually have
   scores. Both views need one governed rule based on the frozen scored domains in the compatible
   assessment series.

### Recommended Project Progress Experience

The page should be presented as **Setting Progress** or **Progress for this setting**, while retaining
the existing route for compatibility.

Its default workflow should be:

1. Show the setting, selected assessment series, latest assessment date, and current performance
   stage.
2. If only one compatible run exists, label it **Baseline recorded** and offer **Start a compatible
   follow-up**. The creation flow must either preserve compatibility or explain that a new series will
   begin.
3. If two or more compatible runs exist, show the overall trend, change since the previous run, and
   change since baseline using only that series.
4. Show compatible domain trends, followed by exact issue movement.
5. Show target progress and action follow-through as separate evidence: action completion never
   proves issue resolution.
6. Keep **All assessment runs** as a factual ledger. Clearly group or label runs that belong to other
   comparison series.
7. Allow a user to choose another compatible series when the project has used more than one
   assessment method over time.
8. Link every score, domain movement, issue state, and action summary to its supporting report or
   action record.

### Relationship to Assessment Portfolio

The two pages should form one drill-down workflow:

```text
Assessment Portfolio (whole active workspace)
    -> assessment series or setting summary
        -> Project Progress (one setting and one compatible series)
            -> completed Report or Actions (supporting detail and work)
```

Assessment Portfolio should reuse the same comparison-series, domain-trend, and issue-movement
services as Project Progress. It must not independently reimplement those calculations. This keeps a
portfolio claim consistent with the detail users see after drilling into a setting.

## Product Promise

From Assessment Portfolio, a user should be able to answer five questions:

1. Are our assessed settings improving over time?
2. Which health domains are improving, stable, or declining?
3. Which exact issues are new, persistent, improving, or resolved?
4. How do settings compare when they used genuinely compatible assessments?
5. Are identified problems being converted into completed and verified actions?

## Required Experience

### 1. Portfolio Overview

The first view should provide a simple workspace summary:

- settings assessed;
- completed assessments;
- repeated compatible assessments;
- improving, unchanged, and declining settings;
- open, overdue, completed, and verified actions;
- assessment coverage by setting type, country, and health domain.

Every count must be restricted to the active workspace. The overview must not create a single
workspace-wide score from unlike assessments.

### 2. Progress Over Time

Show progress when a setting has repeated an assessment under a compatible measurement contract.

The user should be able to:

- select a setting;
- select a compatible assessment series;
- see the score at each completed assessment date;
- see the absolute change and direction;
- open any underlying report;
- understand when comparison is unavailable and why.

A trend line or improvement claim may be shown only when the frozen comparison signatures match.
If an assessment's questions, scoring rules, aggregation method, or other comparison-defining
contract changed, Vytte must start a new series rather than silently join the results.

### 3. Domain Trends

Show how measured domains change across repeated compatible assessments.

Each domain trend must require:

- the same governed domain identity;
- compatible question and scoring contracts;
- the same relevant aggregation rules;
- completed immutable results.

The view should identify improving, stable, and declining domains and allow the user to open the
supporting assessment reports. A domain with the same display name but a different governed identity
or scoring contract must not be treated as the same measurement.

### 4. Issue Movement

Track exact assessed issues over time using stable finding or question identities and compatible
measurement contracts.

Classify each issue as:

- **New** — appears in the latest compatible assessment but not the previous one;
- **Persistent** — remains a problem across compatible assessments;
- **Improving** — remains present but its measured severity has reduced;
- **Resolved** — was present previously and is no longer a problem in the latest compatible result;
- **Not comparable** — cannot be tracked reliably because the underlying measurement changed.

Every issue should show the setting, domain, exact finding, current severity, previous state, latest
assessment date, linked actions, and a route to the supporting report. Vytte must not infer that an
unasked or removed question was resolved.

### 5. Safe Setting Comparisons

Allow comparisons only between settings whose assessments share the same frozen comparison
signature.

For a compatible group, show:

- each setting's latest result;
- the group average;
- difference from the group average;
- domain-level comparisons where those domain contracts also match;
- the assessment version and completion date;
- a clear explanation of why the group is comparable.

Assessments that are not compatible may remain visible in separate groups, but Vytte must suppress
rank, average, delta, and better-or-worse language between them.

### 6. Coverage and Action Follow-Through

Connect measurement to implementation.

Coverage should show:

- settings assessed and not yet assessed;
- assessment coverage by setting type, country, and health domain;
- recent and overdue reassessments where a repeat schedule exists;
- areas with insufficient compatible history for trend analysis.

Action follow-through should show:

- open, in-progress, done, and verified actions;
- overdue actions;
- actions connected to new or persistent issues;
- resolved issues whose actions are complete or verified;
- links to the Actions workspace for management and updates.

Action completion must not automatically prove that an issue is resolved. Resolution comes from a
later compatible assessment; action status describes implementation follow-through.

## Proposed User Workflow

1. Open **Assessment Portfolio** from the workspace sidebar.
2. See a plain-language overview of coverage, progress, issues, and actions.
3. Choose one of four views: **Progress**, **Domains**, **Issues**, or **Compare settings**.
4. Narrow the view by setting, assessment type, domain, or date where relevant.
5. See only valid trends or comparisons; incompatible results remain separate with a clear reason.
6. Open the underlying report or action whenever more detail is needed.

The default experience should be useful without filters. Advanced methodology and comparison
details should remain available through progressive disclosure.

## Navigation Boundary

- **Reports:** find and open the detailed report for one completed assessment.
- **Assessment Portfolio:** understand change, patterns, comparisons, coverage, and follow-through
  across the active workspace.
- **Project Progress:** understand one setting over time within one compatible assessment series.
- **Actions:** assign, manage, complete, and verify improvement work.

Assessment Portfolio may link to Reports and Actions, but it must not duplicate either workflow.
It should also link to Project Progress whenever a user selects a setting or series.

## Data and Integrity Rules

1. Scope every row, count, chart, and query to the active workspace.
2. Read completed immutable assessments and report snapshots; never recalculate historical results.
3. Use frozen comparison signatures as the primary comparison guard.
4. Require governed domain and finding identities for domain and issue tracking.
5. Never convert missing, uncalibrated, incompatible, or not-applicable data into zero.
6. Never describe an issue as resolved merely because it is absent from an incompatible assessment.
7. Suppress rankings and averages when compatibility cannot be proven.
8. Keep respondent identities and individual answers out of portfolio-level views.
9. Show the underlying assessment date and version so users can trace every claim.
10. Explain unavailable comparisons in plain language rather than exposing technical codes.

## Visual Direction

Use visuals only where they answer a management question:

- line charts for compatible progress over time;
- small domain trend charts or a carefully labelled heatmap for compatible domain series;
- status movement summaries for new, persistent, improving, and resolved issues;
- comparison bars for compatible settings;
- coverage and action-status charts for operational follow-through.

Charts must not repeat the same information decoratively. Exact values and links to supporting
reports should remain available.

## Empty and Incompatible States

The page must remain useful when data is limited:

- one completed assessment: explain that a future compatible assessment will create a trend;
- several incompatible assessments: show them separately and explain why no comparison is made;
- no domain history: show the latest domain results without claiming change;
- no actions: invite the user to create actions from report findings;
- no completed assessments: guide the user to start or complete an assessment.

## Acceptance Criteria

The next update is complete when:

1. Users can view a compatible score history for a repeatedly assessed setting.
2. Users can view compatible trends by governed health domain.
3. Exact issues are classified as new, persistent, improving, resolved, or not comparable.
4. Settings are compared only inside compatible assessment groups.
5. Coverage and action follow-through are visible and link to the appropriate workflows.
6. Reports and Assessment Portfolio have clearly different purposes and labels.
7. Every portfolio result, count, and chart is isolated to the active workspace.
8. Incompatible and insufficient data receive clear, non-technical explanations.
9. Mobile layouts remain understandable at 375 pixels and all charts have accessible text
   equivalents.
10. Regression tests prove workspace isolation, comparison safeguards, historical immutability,
    issue-state classification, and empty states.
11. Project Progress charts, domain matrices, targets, and issue claims use the same canonical
    comparison-series guard as Assessment Portfolio.
12. A project with one completed assessment is clearly presented as a baseline with an understandable
    compatible-follow-up path.
13. Projects containing several incompatible assessment series never display those runs on one trend
    line or imply domain movement between them.
14. Portfolio summaries drill down to the same setting-level facts shown on Project Progress.

## Explicit Non-Goals

This update will not:

- compare data across unrelated workspaces;
- publish national or global benchmarks;
- combine unlike assessment scores into one portfolio score;
- rewrite completed assessments or report snapshots;
- infer clinical outcomes that the assessment did not measure;
- expose individual respondent answers in aggregate portfolio views.
