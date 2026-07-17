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

Respondent role is part of the template/scoring profile and reuses this engine. Multi-respondent collection is disabled unless an immutable published template version explicitly enables it and defines:

- a minimum eligible completed respondent threshold;
- the approved aggregation method;
- structured eligibility rules, when applicable;
- the frozen scoring-profile version.

Each submitted durable session is independently scored against the assessment snapshot and retains an immutable response snapshot, response hash, score payload, and score hash. Incomplete, test, revoked, expired, ineligible, unreviewed, missing-score, or integrity-failing sessions are excluded.

`ARITHMETIC_MEAN` is the only initially supported aggregation method. It is the arithmetic mean of non-null results from eligible completed sessions at sub-index, domain, and overall levels. Missing data remains `null`; it is never silently converted to zero. Weighted mean, median, stratification, consensus, role weighting, and indicator-specific methods are future governed versions, not part of the current contract.

Reaching the threshold does not complete the assessment. An OWNER or ADMIN reviews the provisional calculation and manually finalizes it. Finalization freezes the exact contributing and excluded session reference sets, input/result hashes, finalizer, timestamp, template version, and scoring version into the ordinary immutable Vytte report. Late sessions cannot alter the completed report.

Shared reports disclose aggregate results, eligible count, and method only. Session references remain in the immutable audit payload for authorized traceability and are not rendered to report recipients. Vytte does not create a separate respondent or community reporting subsystem.

## Change policy

Any formula, scale, weight interpretation, aggregation, maturity range, or respondent-unit change requires:

- a new algorithm/profile version;
- fixed independently calculated fixtures;
- comparison/recalculation policy;
- snapshot and report compatibility review;
- PostgreSQL and SQLite verification.
