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
