# Operations Readiness

## Current production contract

Vytte runs at `https://klickitsystems.com/vytte` under the `klickit` account with PHP 8.3 and
PostgreSQL 17 on `127.0.0.1:5433`. PostgreSQL 10 on port 5432 belongs to other applications and must
never be changed by Vytte deployment or backup operations.

The production release must provide:

- cached configuration, events, routes, and views;
- database-backed queue processing under a Vytte-only supervised service;
- Laravel scheduling once per minute under a Vytte-only timer;
- a daily Vytte-only database/application backup with retention;
- `/up` and `/health` monitoring;
- failed-job, backup-age, disk, certificate, and application-error checks;
- secure response headers and HTTPS-only production cookies;
- a fresh backup before every deployment.

## Vytte-only service names

The repository deployment templates define:

- `vytte-queue.service`
- `vytte-scheduler.service` and `vytte-scheduler.timer`
- `vytte-backup.service` and `vytte-backup.timer`

Every unit runs only `/home/klickit/vytte` as user `klickit` with cPanel PHP 8.3. These units must not
restart, reconfigure, or inspect the queues, databases, or files of other hosted projects.

## Backup and restore rule

Daily backups are stored below `/home/klickit/backups` with a UTC timestamp. A backup is complete only
when it contains a PostgreSQL custom-format dump, application metadata, and a manifest. Retention may
delete only timestamped Vytte backups created by the Vytte backup script and older than the configured
retention period.

A restore drill must use a separate temporary database and path. Never restore over production to
“test” a backup. Record the drill date, dump verification, migration status, and application boot check.

## Deployment procedure

1. Verify GitHub commit, local full sequential suite, Pint, Blade compilation, official seed checks,
   and frontend build.
2. Create and verify a fresh Vytte backup.
3. Enter maintenance mode.
4. Fast-forward `master`; never reset or overwrite production changes blindly.
5. Install locked production dependencies and build locked frontend dependencies.
6. Run migrations with `--force` using PHP 8.3 and PostgreSQL 17.
7. Rebuild Laravel caches, restart only `vytte-queue.service`, and leave maintenance mode.
8. Verify commit, migrations, health, login, assets, security headers, queue, scheduler, backup timer,
   and recent logs.

## Incident minimum

- Application unhealthy: enter maintenance mode, preserve logs, and roll back only to the verified
  previous commit and compatible database state.
- Queue failure: stop only `vytte-queue.service`, inspect `failed_jobs`, correct the cause, then retry
  selected jobs deliberately.
- Database failure: do not initialize, drop, or restore anything until the exact port and database are
  confirmed as PostgreSQL 17 `vytte`.
- Suspected data exposure: revoke affected tokens/links, preserve audit records, and notify the product
  owner before destructive cleanup.

## External evidence still required

Code and service installation do not prove ongoing readiness. Public launch still requires an uptime
alert destination, an error-reporting destination, a named incident owner, and a recorded restore drill.
