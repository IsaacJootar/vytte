# Domain Scoring and Reporting

## Scoring contract

Official framework scoring remains controlled by frozen framework placements and the frozen scoring profile.

Domain scores are derived analytical views over scored question contributions that carry an explicit frozen analytical-domain mapping.

Missing answers are not treated as zero. A domain is:

- `CALIBRATED` when all expected scored mapped questions in that domain are answered;
- `PARTIAL` when at least one but not all expected scored mapped questions are answered;
- `NOT_CALIBRATED` when no expected scored mapped question has a usable score.

## Storage

`domain_scores` stores:

- assessment ID;
- domain ID;
- taxonomy version ID;
- taxonomy content hash;
- score;
- calibration status;
- expected/answered counts;
- contributing question trace;
- scoring version;
- calculation time.

## Reports

Final immutable reports include domain scores from the frozen assessment snapshot and persisted domain-score records. Context-only domains may appear with no official score when the mapped content was intentionally unscored.

## Critical failures

Critical failures remain part of the aggregation policy and may affect the overall score. Domains do not introduce independent hidden critical-failure rules.

## Local/custom content

Workspace-local sections and workspace custom assessments do not create official Vytte domain scores.
