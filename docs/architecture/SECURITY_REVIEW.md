# Security Review

## Overall status

Security foundations are good, but production hardening remains incomplete.

## Findings

| Severity | Finding | Evidence | Recommendation |
| --- | --- | --- | --- |
| HIGH | Production environment hardening is not verified. | `php artisan about` reports local environment, debug enabled, routes/config not cached. | Add preflight deployment checks and require production env variables before launch. |
| HIGH | Plan-to-content access control is incomplete. | Feature gates exist, but module/template visibility is not controlled by plan entitlements. | Add entitlement checks to module library and assessment creation. |
| MEDIUM | Public respondent and share-link routes need explicit rate limiting. | Public `/respond/{token}` and `/shared-reports/{token}` routes exist without route-specific throttle declarations. | Add throttles and abuse monitoring. |
| MEDIUM | Payment webhooks lack durable idempotency/reconciliation records. | Webhook handlers update workspace plans but do not persist provider event IDs. | Add billing event ledger before paid production launch. |
| MEDIUM | Evidence-file security lifecycle is absent. | Evidence is currently response-bound text only. | Do not claim evidence uploads until storage, access, virus scanning, retention, and privacy rules exist. |
| LOW | Platform Admin share-link emergency revoke exists but can be improved. | `admin.report-shares.*` exists. | Add bulk revoke/search by workspace/report later. |

## Strengths verified

- Platform Admin routes require `EnsurePlatformAdmin`.
- Workspace routes use membership and policy checks.
- Cross-workspace authorization denies as not found.
- Published question/framework objects are immutable by model guards.
- Reports and assessment snapshots are immutable through service/model behavior.
- Share links use random tokens and expiry; signed legacy shared-report route exists.
- Audit logging exists for key governance actions.

## Required before production

- Production env validation.
- Explicit public-route throttles.
- Plan-to-content entitlement checks.
- Durable payment event logging if payments are enabled.
- Final security regression test batch after hardening.
