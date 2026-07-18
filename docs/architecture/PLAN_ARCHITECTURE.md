# Plan Architecture

Vytte beta uses configurable licensing plans, not payment processing.

## Active beta plans

- Starter
- Professional
- Organization

All three are seeded as active and beta-unlocked for the public beta window. They currently receive identical access through `plan_features`, not hardcoded bypasses.

## Data model

- `subscription_plans` stores plan code, label, active state, beta-unlocked state, future pricing metadata, and usage limits.
- `plan_features` stores feature access per plan.
- `workspaces.plan` stores the workspace’s current plan code.

Legacy plan codes are normalized:

- `FREE` -> `STARTER`
- `PRO` -> `PROFESSIONAL`
- `AGENCY` -> `ORGANIZATION`

## Payment boundary

Payment providers, invoices, subscriptions, refunds, taxes, coupons, and ledgers are intentionally deferred. The customer plan page shows beta plan access and does not process money.

## Platform Admin control

Platform Admin can manage plan labels, descriptions, active state, beta-unlocked state, limits JSON, and feature access from the Plan Management screen.
