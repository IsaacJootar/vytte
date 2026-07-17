# Vytte Lifecycle State Machine

## Assessment execution

The canonical persisted assessment values are:

| State | Meaning | Allowed next state |
|---|---|---|
| `IN_PROGRESS` | The assessment can accept authorized responses. | `COMPLETE` |
| `COMPLETE` | Required responses were validated, scoring ran, and the immutable final report was captured. | None |

`COMPLETE` is the database value; **Completed** is the human-readable UI label. `COMPLETED` is not a valid assessment value.

Completion is terminal. Vytte does not currently support reopening, correction versions, cancellation, or archival. Adding any of those requires an explicit product rule for responses, scoring, audit history, and the final report snapshot.

## Assessment-area execution

Rows in `assessment_module_scope` use:

| State | Meaning |
|---|---|
| `PENDING` | Included area awaiting completion of the parent assessment. |
| `COMPLETED` | Included area completed with the parent assessment. |
| `EXCLUDED` | Area intentionally removed from a comprehensive framework with a reason. |

The different persisted words `COMPLETE` and `COMPLETED` are retained because they belong to different existing tables. Application constants prevent them from being interchanged.

## Template publication

Templates and template versions use:

| State | Meaning | Allowed next state |
|---|---|---|
| `DRAFT` | Mutable curator working content. | `PUBLISHED` |
| `PUBLISHED` | Governed content available for assessment creation. | None for that version |

A published template version is immutable and cannot be deleted. Corrections require a new version. Retirement and archival are not implemented and must not be simulated by mutating a published version.

## Legacy assessment publication fields

`assessments.publish_status`, `published_at`, and `published_by` are legacy/reserved fields. They do not control completion, report sharing, or template publication. Report access is governed by `assessment_share_links`; template availability is governed by template/version status.

No new workflow may depend on the legacy assessment publication fields without a separate migration and compatibility decision.

