# Admin Security and Governance

## Role boundary

Vytte has two administration levels.

- **Vytte Platform Admin:** platform-wide methodology, official content, publication, roles, operational oversight, audit, and compliance.
- **Workspace Admin:** workspace-only people, assessments, respondents, reports, sharing, custom local content, and workspace settings.

`PLATFORM_ADMIN` is the only platform governance role used in active source. Older platform-content role terminology has been removed from active authorization paths.

## Authorization

All Platform Admin routes use `auth` plus `EnsurePlatformAdmin`. Workspace routes remain governed by workspace membership, route policies, and workspace-scoped models.

## Immutable content

Published question versions, framework versions, catalogue releases, assessment snapshots, respondent score results, aggregation results, and final reports are not edited in place.

## Audit logging

Consequential Platform Admin actions record audit events through `AuditService`, including:

- question-group create/update/archive;
- question-version approval/publication;
- framework publication;
- catalogue publication;
- platform-user role changes;
- workspace status changes;
- report share-link revocation;
- report link creation/view/revocation;
- assessment creation/finalization/report generation.

## Privacy

Platform Admin oversight pages expose operational metadata and governed artifact status. Shared reports remain governed through share-link controls. Multi-respondent reports show aggregate results by default; traceability of contributing sessions remains restricted to authorized audit access.

## Payments and plans

Plan-feature controls are administered through Platform Admin. Payment events are accepted through signed/verified provider webhooks and must not become a separate assessment-governance path.

## Access removal

Losing access takes effect immediately, not whenever a session happens to expire.

| Action | What it does | What it does not do |
| --- | --- | --- |
| Workspace put on hold (`SUSPENDED`) | `EnsureWorkspaceIsActive` blocks every member; existing sessions are ended | Delete anything; reversible at any time |
| Workspace closed (`ARCHIVED`) | Same block, presented as the end of the relationship | Delete anything; reopening is a support decision |
| Account suspended | `LoginRequest` blocks sign-in; existing sessions are ended | Remove memberships, authored content, or history |

The suspension check in `LoginRequest` runs **after** credentials pass, so a wrong password never reveals that an account exists and is suspended.

`users.suspension_reason` is required. It is shown to the person at sign-in and carried in their notification, so an account is never locked out without a stated reason.

### Session revocation limits

`SessionRevocationService` deletes rows from the `sessions` table. It only works while `SESSION_DRIVER=database`. On any other driver `canRevoke()` returns false and the service reports doing nothing rather than silently failing. **Changing the session driver removes this protection.** The test suite runs with the array driver, so the revocation tests set the database driver explicitly to assert production behaviour.

## Notification channels

Notifications are in-app first. `via()` always returns `['database']` and appends `'mail'` only when the platform setting `email.notifications_enabled` is true, so in-app notification never depends on a third-party service being configured.

Platform Health reports a configured Resend transport with no `RESEND_API_KEY` as a warning, because that combination fails at send time rather than at configuration time. See DEC-2026-07-19-023.
