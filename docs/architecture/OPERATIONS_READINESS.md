# Operations Readiness

## Overall status

Operational readiness is incomplete for production.

## Current operational capabilities

- PostgreSQL is the database authority.
- Database queue driver is configured.
- Health endpoints exist: Laravel's `/up` and the application's `/health` JSON endpoint.
- `php artisan vytte:preflight` validates environment, key, URL, database, queue, mail, and filesystem configuration.
- Audit logs exist.
- Notifications exist.
- Mail can use Resend.
- Plan settings and feature flags are configurable.
- Payment webhooks exist for Paystack and Flutterwave.

## Findings

| Severity | Finding | Evidence | Recommendation |
| --- | --- | --- | --- |
| MEDIUM | Production environment validation is partial. | `php artisan vytte:preflight` checks that environment, key, URL, database, queue, mail, and filesystem are configured, but does not assert production-specific values such as `APP_ENV=production`, `APP_DEBUG=false`, a real `APP_URL`, or payment keys. | Extend the preflight command to assert production values, and add a deployment checklist. |
| HIGH | Backups are not defined. | No backup runbook or automated backup integration is present. | Define PostgreSQL backup/restore and storage backup procedures. |
| HIGH | Queue worker supervision is not documented. | Queue is database-backed, but no worker/supervisor runbook exists. | Add worker process, retry, failed-job, and alerting instructions. |
| HIGH | Monitoring/alerting hooks are incomplete. | `/up` exists, but no monitoring provider, uptime alert, queue alert, failed-job alert, or log alert is documented. | Add monitoring and incident runbooks. |
| MEDIUM | File storage production policy is not finalized. | Default disk is local/private; evidence upload is absent. | Decide local vs S3-compatible storage before file uploads. |
| MEDIUM | Error handling is mostly framework-default. | Laravel defaults exist. | Add production error reporting target and incident triage process. |
| LOW | Maintenance mode uses default settings. | Laravel maintenance mode exists. | Document maintenance procedure for releases. |

## Production go/no-go

Operations are not ready for production until deployment, backup, monitoring, queue, mail, logging, and incident-response procedures are implemented and rehearsed.
