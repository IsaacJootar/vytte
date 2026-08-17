# Vytte production services

These files define the Vytte-only background services used on the current cPanel server. They do not replace or modify cPanel, Apache, PostgreSQL, or services belonging to other applications.

## Fixed production contract

- Application: `/home/klickit/vytte`
- Application user: `klickit`
- PHP: `/opt/cpanel/ea-php83/root/usr/bin/php`
- Database: PostgreSQL `vytte` on `127.0.0.1:5433`
- Backups: `/home/klickit/backups/vytte-YYYYMMDD-HHMMSS`

Install the unit files as `root`, then run:

```bash
systemctl daemon-reload
systemctl enable --now vytte-queue.service
systemctl enable --now vytte-scheduler.timer
systemctl enable --now vytte-backup.timer
systemctl start vytte-backup.service
```

The backup service refuses to run if the application path, database name, host, or port differ from this contract. It creates a PostgreSQL custom-format dump, verifies that dump, archives the application state, copies the production environment with owner-only permissions, and records a manifest. It does not delete old backups automatically.

