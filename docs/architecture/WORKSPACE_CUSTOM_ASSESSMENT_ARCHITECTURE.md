# Workspace Custom Assessment Architecture

Date: 2026-07-18

## Implementation status

**Design artifacts only. There is no execution path.**

A workspace can create, list, view, and change the status of a custom assessment design. A design cannot be run: there is no runner, no scoring, and no report. The scoring behavior described below is a boundary specification for future work, not implemented behavior.

Do not present workspace custom assessments as a usable assessment capability until an execution path exists and this section is updated. See `GO_LIVE_CHECKLIST.md`, which lists deciding this as required before beta.

## Purpose

Workspace Custom Assessment exists for customer scopes that Vytte does not yet officially support.

It is separate from:

- Official Vytte Comprehensive Health Assessment;
- Official Vytte Focused Health Assessment.

## Data model

Workspace custom assessment designs live in:

- `workspace_custom_assessment_designs`
- `WorkspaceCustomAssessmentDesign`

They are workspace-owned and start as drafts.

They may store:

- title;
- purpose;
- scope;
- setting;
- target population;
- respondent type;
- sections;
- indicators;
- questions;
- evidence requests;
- descriptive outputs;
- private scoring config;
- AI drafting context.

## Product boundary

Workspace custom assessments must be labelled:

- customer-created;
- not official Vytte methodology;
- not eligible for the official Vytte Health Score;
- not eligible for official Vytte benchmarking.

Customer custom content cannot be published into the official Vytte catalogue.

## Scoring boundary

This section specifies boundaries for future work. None of it is implemented.

Workspace custom assessments may support local:

- counts;
- percentages;
- completion rates;
- descriptive summaries;
- private user-defined scoring;
- local findings;
- local recommendations.

Custom scoring must never appear as:

- official Vytte score;
- official critical failure;
- official benchmark;
- official methodology;
- facility-wide Vytte Health Score.

## Authorization

Workspace members can create and edit their own unpublished custom assessment designs.

They cannot:

- modify official questions;
- create official question versions;
- publish official frameworks;
- publish catalogue releases;
- assign official Vytte scoring;
- add custom content to official benchmarks;
- impersonate Vytte-approved methodology.

