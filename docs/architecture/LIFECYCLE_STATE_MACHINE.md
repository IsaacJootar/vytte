# Vytte Lifecycle State Machine

## Assessment Execution

| State | Meaning | Allowed next state |
|---|---|---|
| `IN_PROGRESS` | The assessment can accept authorized responses. | `COMPLETE` |
| `COMPLETE` | Required responses were validated, scoring ran, and the immutable final report was captured. | None |

`COMPLETE` is the database value. "Completed" is the UI label.

Completion is terminal. Reopening, correction versions, cancellation, and archival require a future approved lifecycle design.

## Assessment Area Execution

Rows in `assessment_module_scope` use:

| State | Meaning |
|---|---|
| `PENDING` | Included department or assessment area awaiting completion of the parent assessment. |
| `COMPLETED` | Included department or assessment area completed with the parent assessment. |
| `EXCLUDED` | Department or assessment area intentionally excluded during composition with a reason where required. |

## Department Framework Publication

| State | Meaning | Allowed next state |
|---|---|---|
| `DRAFT` | Mutable curator working version. | `PUBLISHED` |
| `PUBLISHED` | Immutable official department framework version. | None |

Published department framework versions cannot be edited or deleted.

## Facility Profile Publication

| State | Meaning | Allowed next state |
|---|---|---|
| `DRAFT` | Mutable platform profile. | `PUBLISHED` |
| `PUBLISHED` | Official profile available for catalogue releases and project creation. | None in the current implementation |

## Catalogue Release Publication

| State | Meaning | Allowed next state |
|---|---|---|
| `DRAFT` | Mutable catalogue release under curation. | `PUBLISHED` |
| `PUBLISHED` | Immutable release available for assessment creation. | None |

Published catalogue releases cannot be edited. Corrections require a new release.

## Report Finalization

A completed assessment has one immutable final report snapshot. Report routes, exports, share links, dashboards, and analytics read that same report architecture.
