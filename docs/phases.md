# Vytte — Build Phases

> Every module must be fully built, tested, committed, and pushed before Isaac approves the next one.
> Isaac's explicit approval ("approved" / "go ahead") is required between every module.

---

## Status key

| Symbol | Meaning |
|--------|---------|
| ✅ | Complete — committed and pushed |
| 🔄 | In progress |
| ⬜ | Not started |

---

## Module 01 — Foundation ✅

**Commit:** `12a10b3`

- Laravel 13, PHP 8.3, PostgreSQL via Docker
- UUID primary keys throughout (`user_id`, `workspace_id`, `project_id`)
- Multi-tenancy: `BelongsToWorkspace` trait + `WorkspaceScope` global scope
- `ResolveWorkspace` middleware — sets `app('current.workspace')` on every request
- Auth: Laravel Breeze (email + password, no OAuth)
- Registration auto-creates a Workspace and assigns the user as OWNER
- Email notifications OFF by default (`PlatformSetting::get('email.notifications_enabled', false)`)
- Cross-workspace isolation tests (3 passing — required, non-negotiable)
- All 28 tests passing

---

## Module 02 — Design System & UI Shell ✅

**Commit:** `797a311`

- Ocean Blue `#0369A1` token system applied globally (vytte-* Tailwind tokens)
- Guest layouts: login, register, password reset
- Authenticated layout: dark navy sidebar (`#0C1929`) + mobile 4-tab bottom nav
- Blade components: `score-pill`, `score-arc`, `sidebar-nav-item`, `mobile-nav-item`, `skeleton`, `vytte-mark`, buttons, inputs, dropdown
- All layouts responsive from 375px
- Loading states on action buttons (Alpine.js `x-loading`)

---

## Module 03 — Projects ✅

**Commit:** `c342634`

- Project list (`/projects`) with paginated cards
- Create / edit / archive a project
- Project fields: name, description, target (name + type + category + state/LGA)
- Target co-created with project in a single DB transaction
- Project card: name, assessment count, avg score pill
- Workspace-scoped — all queries filtered to `current.workspace`
- Tests: CRUD, workspace isolation

---

## Module 04 — Assessment Module Library ✅

**Commit:** `4156a38`

- `AssessmentModule`, `ModuleDomain`, `Question`, `QuestionOption` models
- HIVAW full module seeded via `HivawQuestionsSeeder` (all domains, questions, options)
- `ReferenceDataSeeder` seeds target types, categories, assessment tiers
- Module library: `/modules` list + `/modules/{id}` detail
- Read-only for workspace users
- Tests: library list, module detail, domain/question structure — 12 tests

---

## Module 05 — Assessment Runner ✅

**Commit:** `c570562`

- Create Assessment (links Project + AssessmentModule via `AssessmentModuleScope`)
- Livewire 3 runner: one-domain-at-a-time layout, progress bar, back/next
- Response auto-save on every option click (no explicit save button)
- `Response` model: stores `assessment_id`, `question_id`, `selected_option_id`
- Draft state (`IN_PROGRESS`) until submit
- Submit locks assessment to `COMPLETE`
- Tests: create, save, submit, cannot submit incomplete — 13 tests

---

## Module 06 — Scoring Engine ✅

**Commit:** `9c2081a`

- `ScoringService::calculate()` runs on assessment submit
- Calculates: domain scores → sub-index scores → composite score
- Stores in `domain_scores`, `sub_index_scores`, `assessment_scores`
- Score bands: Strong ≥ 70 (`#15803D`), Moderate 45–69 (`#B45309`), Weak < 45 (`#B91C1C`)
- Uncalibrated flag: sub-index with no weights shows `null` score, flagged in UI
- `MaturityLevel` model maps score ranges to text labels
- Tests: scoring math, band assignment, cross-workspace isolation

---

## Module 07 — Results & Assessment Report ✅

**Commit:** `86407f3`

- Assessment results page with circular arc score meters
- Domain-level breakdown table with score pills
- Sub-index score breakdown
- Score history: trend graph when same module run multiple times on a project
- Findings section: auto-generated text highlighting weak domains
- Print-optimised CSS (browser print, no JS)
- Tests: results page loads, scores displayed, uncalibrated flag shown

---

## Module 08 — Dashboard ✅

**Commit:** `037ecc4`

- Workspace home: active project count, total assessments, avg composite score
- Recent projects list (last 5 active)
- Recent assessments (last 5 submitted)
- Score distribution: Strong / Moderate / Weak counts
- Quick-action "+ New Project" button
- Skeleton loaders on all data cards
- Tests: stats accurate, workspace isolation

---

## Module 09 — Team Members ✅

**Commit:** `626483f`

- Invite team member by email (sends invite link via DB-backed token)
- Accept invite → login or register → added to workspace
- Member list: name, email, role, joined date
- Role management (OWNER only): ADMIN ↔ MEMBER
- Remove member (OWNER / ADMIN only)
- Roles: `OWNER`, `ADMIN`, `MEMBER`
- Tests: invite flow, role gates, duplicate invite rejected, owner cannot remove self

---

## Module 10 — Settings ✅

**Commit:** `eb40f7f`

**Workspace settings (OWNER / ADMIN)**
- Workspace name
- Danger zone: delete workspace (with confirmation prompt)

**User profile settings**
- Name, email
- Change password
- Delete account

---

## Module 11 — Notifications & Email ✅

**Commit:** `4c9cb3c`

- Email service: Resend
- Platform toggle: `email.notifications_enabled` via `PlatformSetting` — OFF by default
- When ON: assessment completion email to workspace OWNER/ADMIN
- When OFF: all email methods return early silently
- In-app notification bell with unread count badge
- DB notification channel for all notification types
- Tests: email not sent when OFF, notification records created

---

## Module 12 — Export & Sharing ✅

**Commit:** `7d0ef41`

- PDF export: server-side via `barryvdh/laravel-dompdf` v3.1.2
- CSV export: all assessments + scores for a project
- Shareable read-only report link: Laravel signed URL, expiry configurable via platform admin (default 30 days — see Module 15)
- Public shared report view (no auth required, resolves via signed middleware)
- Tests: PDF content-type, CSV column structure, shared link resolves, expired link rejected, cross-workspace isolation — 16 tests

---

## Module 13 — Platform Admin ✅

**Commit:** `5a48664`

- Admin gate: `platform_role = 'PLATFORM_ADMIN'` on `users` table
- `EnsurePlatformAdmin` middleware guards all `/admin/*` routes
- Workspace list: search by name (ilike), filter by plan, pagination
- Workspace detail: members, projects, assessments (read-only)
- Platform settings: email notifications toggle
- Module library management: edit module, edit domain labels, edit question text, toggle active
- JSON import for new assessment modules (validates, detects duplicates)
- Tests: admin gate, all CRUD operations, import — 26 tests

---

## Module 14 — Billing ✅

**Commit:** `e3bb111`

- Plans: Free (1 project, 3 assessments/project), Pro (10 projects, unlimited assessments), Agency (unlimited everything)
- `PlanService` — centralised limit logic; `projectLimit()` and `assessmentLimit()` return `null` for unlimited
- Limit enforcement in `ProjectController::store()` and `AssessmentController::store()` → redirect to billing page
- Billing page: current plan badge, plan cards with feature lists, upgrade buttons
- Paystack popup JS integration (inline, no npm package)
- `PaystackWebhookController`: HMAC-SHA512 signature validation, `charge.success` upgrades workspace plan
- Webhook route CSRF-exempt via `bootstrap/app.php validateCsrfTokens(except:)`
- Tests: plan limit enforcement, webhook signature validation, plan upgrade via webhook — 14 tests

---

## Module 15 — Platform Configurability + Dark/Light Theme ✅

**Commit:** `dd3c368` / `c80ebec`

- Dark/light theme toggle — server-rendered via `users.theme` column; `<html class="dark">` set per user; toggle is a POST form reload
- Full dark mode sweep across every Blade view: user app + admin panel
- Project search by name — GET `?search=` param, `whereRaw LOWER(name) LIKE LOWER(?)` (works on PostgreSQL and SQLite)
- Share link expiry — configurable via `PlatformSetting::get('sharing.link_expiry_days', 30)`, no longer hardcoded
- Plan limits (FREE projects, FREE assessments, PRO projects) — all read from `PlatformSetting`, overridable from admin without a code deploy
- Payment gateway toggles — Paystack and Flutterwave each independently enabled/disabled from platform admin
- `FlutterwaveWebhookController` — SHA256 `verif-hash` header validation, `charge.completed` event upgrades workspace plan
- Flutterwave route added to `routes/web.php` (CSRF-exempt, signature-validated)
- `config/services.php` + `.env.example` updated with Flutterwave keys
- Platform admin settings page expanded: Email, Shared Reports, Payment Gateways, Plan Limits sections
- Test DB switched from Docker PostgreSQL to SQLite in-memory (`phpunit.xml`) — no Docker required to run tests
- All `ilike` queries replaced with `whereRaw('LOWER(name) LIKE LOWER(?)')` for cross-DB compatibility
- Tests: `ThemeTest` (4), `ProjectSearchTest` (5), `ConfigurabilityTest` (11) — 231 total passing

---

## Module 16 — Consent Capture ✅

**Commit:** `23145b5`

- `requires_consent` boolean on `assessment_modules` (default false); HIVAW flagged true
- `respondent_consents` table: `consent_id` UUID, `assessment_id`, `module_id`, `consent_text` (verbatim), `consented_by` (user_id), `consented_at`
- `RespondentConsent` model with UUID PK, FK to assessment + module + user
- `AssessmentRunner`: detects consent requirement on `mount()`, shows consent screen before first question, blocks `selectOption` without consent, persists consent across page reloads
- `giveConsent()` action creates the DB record and sets `$consentGiven = true`; idempotent, no-ops for complete assessments
- Consent text constant stored verbatim in DB for audit trail
- Dark mode on all consent UI elements
- Gate is general: any future module can set `requires_consent = true` without code change
- Tests: 11 new in `ConsentCaptureTest`, 2 updated in `AssessmentTest` — 242 total passing

---

## Module 17 — Progress & Maturity Tracking ✅

**Commit:** `54ba7fa`

- Per-project progress page showing all completed assessment runs in chronological order
- Runs table: #, Date, Module, Maturity Level (L1–L5 + name), Score, Band, View link
- Domain score matrix (≥2 runs): domains as rows, assessment runs as columns, colour-coded score pills
- Compare form on progress page: select any two runs → GET compare route
- Compare page: side-by-side header cards (date, module, score, maturity level, band) + domain delta table (A score | ↑↓ change | B score); delta computed as B − A; positive = green ↑, negative = red ↓, zero = grey
- Score history table on results page updated: now shows Maturity Level column + "Full progress →" link
- "Progress" button on project show page (visible only when ≥1 complete assessment)
- `ProjectProgressController`: `index()` + `compare()` methods; compare scopes both assessments to the same project (404 on cross-project IDs)
- Workspace isolation enforced via existing `WorkspaceScope` on Project route binding
- Tests: 14 new in `ProgressTrackingTest` — 256 total passing

---

## Module 18 — UI Localization Infrastructure ✅

**Commit:** `4b7417f`

- `locale` column on `users` table (string, default 'en')
- `lang/en/runner.php` + `lang/fr/runner.php` — 18 translation keys covering all assessment runner UI strings
- `SetLocale` middleware — reads `user->locale` (preferred) or `session('locale')`, calls `App::setLocale()`; falls back to 'en' for unsupported locales; appended to the web middleware group
- `LocaleController::store()` — validates locale against allowlist, writes to session + updates user; `POST /locale` route inside auth group
- Assessment runner view — all hardcoded strings replaced with `__('runner.*')` helper calls (incl. parametrised strings for question counter and saved-at timestamp)
- Locale switcher — EN / FR toggle rendered above the Livewire component on `assessments/run.blade.php`; active locale highlighted in vytte-700; POST form with redirect-back
- Locales supported: `en` (English), `fr` (French); architecture is open — adding a new locale requires only a new `lang/{code}/runner.php` file and adding the code to the allowlist
- Tests: 11 new in `LocalizationTest` — 267 total passing

---

## Build sequence summary

```
01 Foundation        ✅  (12a10b3)
02 UI Shell          ✅  (797a311)
03 Projects          ✅  (c342634)
04 Module Library    ✅  (4156a38)
05 Assessment Runner ✅  (c570562)
06 Scoring Engine    ✅  (9c2081a)
07 Results           ✅  (86407f3)
08 Dashboard         ✅  (037ecc4)
09 Team Members      ✅  (626483f)
10 Settings          ✅  (eb40f7f)
11 Notifications     ✅  (4c9cb3c)
12 Export            ✅  (7d0ef41)
13 Platform Admin    ✅  (5a48664)
14 Billing           ✅  (e3bb111)
15 Configurability   ✅  (c80ebec)
16 Consent Capture   ✅  (23145b5)
17 Progress Tracking ✅  (54ba7fa)
18 UI Localization    ✅  (4b7417f)
```

**All 18 modules complete — 267 tests passing.**
