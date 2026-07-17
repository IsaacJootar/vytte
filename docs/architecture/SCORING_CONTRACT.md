# Scoring Contract

## Authority

- Canonical output scale: 0–100.
- Current frozen-profile algorithm: `vytte-3.0-snapshot-profile`.
- Completed score rows and final report snapshots are historical records and are never silently recalculated.

## Calculation

1. Read the assessment's included areas.
2. Use the immutable snapshot scoring profile when present; use the isolated legacy profile only for legacy assessments.
3. Read authoritative responses for the scoring unit.
4. Normalize option scales whose maximum is at or below 1 to 0–100.
5. Calculate each sub-index as a weighted mean of answered scored questions.
6. Aggregate non-null sub-index results into domains and the overall score.
7. Assign maturity from the configured range containing the overall score.
8. Persist algorithm version and calculation time.

## Calibration

- `NOT_CALIBRATED`: no valid weighted answers for the unit.
- `PARTIAL`: at least one result exists but required scored content is incomplete.
- `CALIBRATED`: all expected scored content for the unit is represented.

Missing or uncalibrated data is `null`, never zero.

## Display bands

- Weak: below 45
- Moderate: 45 to below 70
- Strong: 70 or above

## Respondent roles

Respondent role must be part of the template/scoring profile and must reuse this engine. Before multi-respondent aggregation is enabled, a template must define its scoring unit, minimum completed respondents, aggregation method, missing-response behavior, and completion/finalization rule. Vytte must not infer those methodological choices or create a separate report.

## Change policy

Any formula, scale, weight interpretation, aggregation, maturity range, or respondent-unit change requires:

- a new algorithm/profile version;
- fixed independently calculated fixtures;
- comparison/recalculation policy;
- snapshot and report compatibility review;
- PostgreSQL and SQLite verification.

