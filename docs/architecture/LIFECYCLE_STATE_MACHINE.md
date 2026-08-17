# Vytte Lifecycle State Machine

## Assessment lifecycle has separate axes

An assessment has an immutable content snapshot at creation, but its operational lifecycle uses
separate execution, publication, and collection-window fields.

### Execution state (`assessments.status`)

| State | Meaning | Allowed next state |
|---|---|---|
| `IN_PROGRESS` | Setup, collection, or self-completion is not finally scored. | `COMPLETE` |
| `COMPLETE` | Required responses were validated, scoring ran, and the immutable report was captured. | None |

Completion is terminal. A correction requires a future approved correction/version contract; a
completed assessment is never reopened or silently recalculated.

### Publication state (`assessments.publish_status`)

| State | Meaning | Allowed next state |
|---|---|---|
| `DRAFT` | Setup remains private to authorized workspace users. | `PUBLISHED` |
| `PUBLISHED` | All respondent-facing setup, including local questions, is locked and governed collection may begin. | None |

### Collection window (`closed_at`)

A published, incomplete assessment collects only while `closed_at` is null. An OWNER or ADMIN may
close the window before finalization and may reopen that window while the assessment remains
incomplete. Closing is not completion and does not calculate a result.

An assessment normally has one reusable active respondent link. Deactivation or expiry blocks
future access but does not invalidate an already-submitted session. Completed-session eligibility
is governed by its durable session review, not by the link's later operational state.

## Assessment area execution

| State | Meaning |
|---|---|
| `PENDING` | Included department or area awaiting parent completion. |
| `COMPLETED` | Included department or area completed with the parent assessment. |
| `EXCLUDED` | Area intentionally excluded during composition, with a reason where required. |

## Question version lifecycle

`DRAFT -> INTERNAL_REVIEW -> APPROVED -> PUBLISHED -> SUPERSEDED or ARCHIVED`

Draft, internal-review, and approved versions remain mutable only within their authorized workflow.
Published, superseded, and archived question versions are immutable. Supersession clones a successor
draft and preserves the predecessor.

## Framework version lifecycle

`DRAFT -> PUBLISHED -> SUPERSEDED or ARCHIVED`

Published, superseded, and archived framework versions are immutable. Corrections require a successor.

## Catalogue release lifecycle

`DRAFT -> PUBLISHED -> SUPERSEDED or ARCHIVED`

Published releases pin exact framework versions and cannot be edited. Supersession preserves every
assessment that already points at the predecessor.

## Facility profile lifecycle

`DRAFT -> PUBLISHED`

Published profiles are official reference content. Changes affect future composition only and never
rewrite an existing assessment snapshot.

## Independent trust review lifecycle

`ASSIGNED -> SUBMITTED -> APPROVED or CHANGES_REQUESTED`

The assigned reviewer submits evidence and a recommendation. A different Platform Admin makes the
decision. `CHANGES_REQUESTED` is not an approval and returns work for further evidence.

## Contribution lifecycle

A workspace contribution remains tenant-private through submission and review. Acceptance does not
publish it. Promotion creates a private, unscored draft question version which then enters the normal
question/framework/scoring/publication lifecycle.

## Report finalization

A completed assessment has one immutable final report snapshot. Results, exports, shares, dashboards,
actions, and analytics read that report contract rather than reconstructing facts from mutable content.
