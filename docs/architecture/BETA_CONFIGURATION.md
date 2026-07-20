# Beta Configuration

Public beta is configured as a free-access beta through plan configuration.

## Enabled

- Starter plan: active, beta unlocked.
- Professional plan: active, beta unlocked.
- Organization plan: active, beta unlocked.
- All plan features: enabled for all beta plans.
- Payments: disabled by absence of payment routes and customer payment UI.

## Local seed command

Run:

```bash
php artisan migrate --force
php artisan db:seed --class=SubscriptionPlanSeeder --force
php artisan db:seed --class=PlanFeatureSeeder --force
php artisan db:seed --class=DemoAccountSeeder --force
```

## Demo accounts

- `starter@vytte.test`
- `professional@vytte.test`
- `organization@vytte.test`
- `admin@vytte.test`

All demo passwords remain `password`.

## Email

Email notifications are **off** by default, controlled by the platform setting `email.notifications_enabled` at `/admin/settings`.

Notifications still reach people while email is off: every notification returns `['database']` from `via()` and appends `'mail'` only when the setting is true. Only the emails stop.

The mail transport is Resend (`resend/resend-laravel`, `mail.default=resend`, key `RESEND_API_KEY`). Turning the setting on without that key configured sends nothing and reports nothing at send time, so Platform Health flags the combination and the Settings screen warns before the switch is flipped.

`.env.example` still ships `MAIL_MAILER=log`, which writes emails to a log file instead of sending them. Set this properly at deploy time.

## Link delivery during beta

Because email is off, links are shared by hand and must therefore be retrievable rather than shown once:

- Workspace invitations, respondent links and report share links are all listed permanently on their screens through `x-share-link`.
- Each offers copy and a WhatsApp share. WhatsApp is a `wa.me` link with the message encoded server-side — no Business API, no account, no per-message cost.
- Copying falls back to selecting the text when the Clipboard API is unavailable, which is the normal case on plain `http` and therefore normal for a beta on a local network.

See DEC-2026-07-19-022.
