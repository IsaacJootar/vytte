# Scoring Contract

## Authority

- Canonical output scale: 0-100.
- Current algorithm: `vytte-4.0-numeric-bands`.
- The assessment snapshot is the scoring authority.
- Final score rows and final report snapshots are historical records and are never silently recalculated.

## Official Scoring Boundary

Official scoring uses only:

- selected department framework versions pinned by the catalogue release;
- frozen snapshot payload;
- frozen snapshot scoring profile;
- frozen snapshot aggregation policy;
- authoritative response set.

Local custom sections are excluded from official scoring.

## Calculation

1. Read the assessment's included departments from `assessment_module_scope`.
2. Read sub-index membership, question weights, option weights, numeric bands, and domain identity from `assessment_snapshots.payload`.
3. Read authoritative responses for the scoring unit.
4. Normalize option scales whose maximum is at or below 1 to 0-100.
5. Calculate each sub-index as a weighted mean of answered scored questions.
6. Aggregate non-null sub-index results into domains and the overall score.
7. Apply the frozen aggregation policy.
8. Assign maturity from the configured range containing the overall score.
9. Persist algorithm version and calculation time.

## Calibration

- `NOT_CALIBRATED`: no valid weighted answers for the unit.
- `PARTIAL`: at least one result exists but required scored content is incomplete.
- `CALIBRATED`: expected scored content is represented.
- `CRITICAL_FAILURE`: a frozen aggregation policy rule forced the final outcome.

Missing or uncalibrated data is `null`, never zero.

## Display Bands

- Weak: below 45
- Moderate: 45 to below 70
- Strong: 70 or above

## Critical Failures

Catalogue aggregation policy may enable critical-failure behavior. The initial implemented rule can treat a configured flagged or zero-score option as a critical failure and set the overall score to zero.

Future critical-failure methods require a new governed policy version and tests.

## Multi-Respondent Scoring

Multi-respondent collection is disabled unless published content explicitly enables it and freezes:

- minimum eligible completed respondent threshold;
- approved aggregation method;
- eligibility rules;
- scoring profile version.

Each submitted durable session is independently scored against the assessment snapshot and retains immutable response and score snapshots.

`ARITHMETIC_MEAN` is the only initially supported multi-respondent aggregation method. Future weighted mean, median, stratification, consensus, role weighting, and indicator-specific methods require governed versions.

Manual finalization by an authorized workspace user is required. Finalization freezes contributing/excluded session references, hashes, method, scoring version, finalizer, timestamp, and creates the ordinary immutable report.

There is no separate respondent or community reporting subsystem.

## Change Policy

Any formula, scale, weight interpretation, aggregation, maturity range, respondent-unit behavior, or critical-failure rule requires:

- new versioned methodology;
- fixed test fixtures;
- snapshot compatibility review;
- report compatibility review;
- PostgreSQL verification.
