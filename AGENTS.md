# Vytte — Codex Master Instructions

> Read this file at the start of every session. Every decision traces back here.
> See also: `docs/architecture.md`, `docs/database.md`, `docs/modules/`.

## What this product is

Vytte is a health systems diagnostic SaaS platform powered by the PHSAI™ framework
(PrimeSafePath Health Systems Assessment Instrument). It turns structured questionnaires
completed by health facility staff into a scored, prioritized, actionable Facility Diagnostic
Report — telling a facility what its real operational problems are and how to fix them.

**Product name in all user-facing UI:** Vytte (never "PHSAI" or "PHSAI™" in UI copy).
**Powered by (acceptable footnote):** "powered by the PHSAI™ diagnostic framework"
**Builder:** Isaac Jootar (solo founder). Codex is the engineering team.

## Current transformation authority

**CURRENT PHASE: Approved corrective implementation after Phase 22**

Isaac approved every pending Phase 21/22 recommendation on 17 July 2026. Work must proceed in bounded modules. Complete relevant regression tests, commit, and push each module before beginning the next.

Before architecture or assessment-flow work, read in order:

1. `docs/architecture/CURRENT_ARCHITECTURE.md`
2. `docs/architecture/CURRENT_ASSESSMENT_FLOW.md`
3. `docs/architecture/DATA_MODEL_AUDIT.md`
4. `docs/architecture/PRESERVATION_REGISTER.md`
5. `docs/architecture/ARCHITECTURE_GAPS.md`
6. `docs/architecture/DECISION_LOG.md`
7. `docs/architecture/PHASE_21_RECOMMENDATION.md`

Repository code remains the technical source of truth. Preserve existing user changes and historical data.

### Approved assessment creation model

Vytte has exactly two creation paths:

1. **Comprehensive Health Assessment** — assess health across an entire setting. Load a setting-appropriate comprehensive framework and allow removal of non-applicable assessment areas. Use the word **department** only when the setting genuinely has departments, especially a full health-facility run.
2. **Focused Health Assessment** — assess exactly one health domain, programme, topic, or intervention. Never show unrelated departments, programmes, modules, standard batteries, or checkboxes. Offer the best matching template and keep customization optional and progressively disclosed.

Settings such as health facilities, schools, communities, correctional facilities, workplaces, places of worship, NGOs/programmes, government organizations, and custom settings are assessment contexts, not health domains.

Published templates must be immutable. Customization must create an assessment-owned snapshot or labeled derivative and revalidate scoring.

---

## Stack — do not suggest alternatives without asking

| Layer | Decision |
|---|---|
| Framework | Laravel 13 (PHP 8.3+) |
| Frontend templates | Blade |
| CSS | Tailwind CSS v4 — CSS-first (`@theme` in app.css, NO tailwind.config.js, NO DaisyUI) |
| JS interactivity | Alpine.js (bundled by Livewire 4 — never imported separately) |
| Complex UI / real-time | Livewire 4 |
| Database | PostgreSQL is production authority. SQLite is temporarily allowed for desktop development/testing while local Docker is unavailable; PostgreSQL parity verification remains required. |
| ORM | Laravel Eloquent |
| Auth | Laravel Breeze (email + password) |
| Email | Resend (resend/resend-laravel) — built but toggled OFF by default in admin settings |
| AI (future) | Anthropic Codex API |
| Icons | blade-ui-kit/blade-heroicons |
| Build | Vite + laravel-vite-plugin + @tailwindcss/vite |

---

## Before writing any code — read these files

- `docs/architecture.md` — workspace/project/assessment structure, ALWAYS read before any DB work
- `docs/database.md` — full 42-table schema, ALWAYS read before any model/migration work
- `docs/ui-rules.md` — naming rules, ALWAYS read before any Blade/Livewire work
- `docs/modules/` — one spec file per product module, read the relevant one before building

---

## Output rules

- Respond concisely
- Drop all articles, filler words, pleasantries, and sign-offs
- Do not restate the problem or task
- Provide only the direct answer or requested code
- Do not explain code unless explicitly asked

---

## Golden rules — non-negotiable

1. **Plain language always** — every label, message, and tooltip must be understood by a
   first-time user with no PHSAI training. The underlying methodology is complex; the UI is not.

2. **Workspace isolation is sacred** — every query on tenant data MUST be scoped to the
   current workspace via the `BelongsToWorkspace` global scope. The scope is auto-applied on
   every model; do NOT opt out in controllers. Policies enforce on top of scopes.
   Read `docs/architecture.md` before ANY database work.

3. **Project isolation too** — within a workspace, data for Project A is never visible from
   Project B. Scoping is workspace → project → assessment → all downstream data.

4. **Mobile-first** — every screen works at 375px minimum. Use Tailwind responsive prefixes
   (sm: md: lg:) on every layout element. Test at 375px before marking any UI complete.

5. **Failures are handled gracefully** — every external API call (AI, email, etc.) wrapped
   in try-catch. Show plain English to users. Never show raw errors or stack traces.

6. **Ask before assuming** — if a requirement is unclear, stop and ask.
   Do not silently pick an approach and build 200 lines on a wrong assumption.

7. **Surgical changes** — only touch what the task requires. Do not refactor or improve
   adjacent code unless explicitly asked.

8. **One module at a time** — complete and test each module before starting the next.

9. **Do not invent a new architecture** — use the architecture in `docs/architecture.md`
   exactly. No creative alternatives.

10. **Implement only according to the docs** — every feature, screen, model, and service
    must trace back to a decision in `docs/`. If it is not documented, stop and ask.

11. **Commit after each module** — when a module is fully built and tested:
    `git add`, `git commit`, `git push`.

12. **Stop and ask Isaac to approve before continuing** — after every completed module,
    stop completely. Report what was built, confirm it works, and wait for Isaac's explicit
    approval before starting the next module.

13. **Do not proceed to the next module without Isaac's approval** — silence is not approval.
    Wait for Isaac to say "approved" or "go ahead" before writing a single line of the next module.

14. **Commit messages must name the module** — example: `"Module 01 complete — Foundation:
    PostgreSQL, auth, workspace auto-creation, BelongsToWorkspace global scope"`.

15. **Do not stop halfway** — a module is only done when every feature in its spec file is
    built, tested, committed, and pushed. Then stop and wait for approval.

---

## Architecture in one paragraph

Vytte uses a single PostgreSQL database with workspace-scoped rows as its multi-tenancy model.
Every new user gets a workspace automatically at signup — there is no "no workspace" state.
Inside a workspace, users create Projects. Each Project (v1) has exactly one Target (the
entity being assessed — typically a health facility). Each Project holds one or more Assessments,
which are the actual diagnostic runs. All business data (targets, assessments, responses, scores,
reports) lives under a Project, which belongs to a Workspace. No workspace ever sees another
workspace's data — enforced by the `BelongsToWorkspace` global Eloquent scope, Policies, and
required cross-workspace attack tests.

---

## Email — built but gated by admin toggle

Resend integration is fully built (Mailable classes, queue jobs for async sending), but ALL
outbound email goes through a platform setting check first:

```php
if (PlatformSetting::get('email.notifications_enabled', false)) {
    // send
}
```

This setting is OFF by default. The admin panel has a toggle to enable it once a verified
Resend domain is configured. Until then, auth works without email verification gates.
Password resets still work via link — they just aren't emailed until the toggle is on.

---

## Scoring engine — "not yet calibrated" rule (non-negotiable)

The 528 questionnaire questions currently have NULL score weights. The scoring engine MUST:
- Detect when a sub-index has no linked questions with non-null weights
- Return `null` for that sub-index's score and flag it as `calibration_status = 'NOT_CALIBRATED'`
- NEVER return 0 or an incorrect score for an uncalibrated sub-index
- Show "Awaiting calibration" in the UI wherever an uncalibrated score would otherwise appear
- The HIVAW module (9 community HIV questions) is the ONLY module with real weights — treat it
  as the reference implementation

---

## Git identity — all commits must use

```bash
git config user.name "Isaac Jootar"
git config user.email "jootarisaac@gmail.com"
```

Run this at the start of every session to confirm identity is set.
