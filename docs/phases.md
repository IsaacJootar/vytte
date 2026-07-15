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

## Module 02 — Design System & UI Shell ⬜

**Waiting for design proposal approval.**

- Ocean Blue `#0369A1` token system applied globally
- Guest layouts: login, register, password reset
- Authenticated layouts: desktop sidebar + mobile bottom tab bar
- Sidebar: dark navy `#0C1929`, Ocean Blue active state, Vytte logo mark
- Mobile: sticky top bar + 4-tab bottom nav (Home / Projects / Assess / More)
- Reusable Blade components: btn, card, input, badge, score-pill, skeleton loader, arc meter (empty placeholder)
- All layouts responsive from 375px
- Loading states on all action buttons (Alpine.js)
- Lazy-load skeleton on dashboard cards
- Dark/light theme toggle support

---

## Module 03 — Projects ⬜

- Project list (dashboard + /projects)
- Create / edit / archive a project
- Project fields: name, type (Facility / Community Program / Organisation / Other), location (free text), description
- Project card shows: name, type chip, assessment count, avg score pill (Uncalibrated if no assessments yet)
- Workspace-scoped — all queries filtered to `current.workspace`
- Tests: CRUD, workspace isolation, project card data

---

## Module 04 — Assessment Module Library ⬜

> An "assessment module" is a diagnostic instrument (e.g., "Community HIV Awareness", "Service Delivery Quality"). The library is seeded by the platform — workspace users pick from it, they do not create their own.

- `AssessmentModule` model: name, code (e.g., `HIVAW`), description, sub-index it feeds, status (active/inactive)
- `Domain` model: belongs to a module, has name, order, description
- `Question` model: belongs to a domain, has text, type (single_choice), order
- `AnswerOption` model: belongs to a question, has text, score_weight (0.0–1.0)
- Database seeds with at least 2 complete modules (from PHSAI docs)
- Admin-only: view/toggle module active status
- Tests: module library read, domain/question structure

---

## Module 05 — Assessment Runner ⬜

> The field-facing flow. Optimised for mobile use in clinics, outdoors, and low-connectivity environments.

- Create an Assessment (link a Project + AssessmentModule)
- One-question-at-a-time flow: progress bar, domain grouping, back/next
- Auto-save response on each answer (no explicit save button — avoids data loss)
- Draft state until all questions answered, then user submits
- `Response` model: belongs to assessment + question, stores selected `answer_option_id`
- Offline-resilient: save button visible, shows last-saved time
- Tests: create assessment, save responses, submit, cannot submit incomplete

---

## Module 06 — Scoring Engine ⬜

> Scores are calculated on submission. Uncalibrated modules are flagged, never silently zeroed.

- On assessment submit, calculate:
  - Domain scores (avg weighted score of questions in domain)
  - Module score (weighted avg of domain scores)
  - Sub-index score (avg of all module scores feeding that sub-index)
  - Composite Health System Index (weighted avg of all sub-index scores)
- `Score` model: stores calculated scores per assessment per domain
- If a sub-index has no calibrated weights → status `uncalibrated`, score `null`, flag shown in UI
- Score bands: Strong ≥ 70 (`#15803D`), Moderate 45–69 (`#B45309`), Weak < 45 (`#B91C1C`)
- Scores recalculate if responses are updated before final lock
- Tests: scoring math, uncalibrated flag, band assignment, cross-workspace score isolation

---

## Module 07 — Results & Assessment Report ⬜

- Assessment results page: full score breakdown with circular arc meters
- Domain-level breakdown table
- Score history: if same module run multiple times on same project, show trend
- Findings section: auto-generated text based on weak domains
- Printable view (browser print CSS)
- Tests: results page loads, scores displayed correctly, uncalibrated shown as flagged

---

## Module 08 — Dashboard ⬜

- Workspace home: active project count, total assessments, avg composite score
- Recent projects list (last 5 active)
- Recent assessments (last 5 submitted)
- Score distribution chart: count of Strong / Moderate / Weak assessments
- Quick-action button: "+ New Project"
- Lazy-load skeletons on all data cards
- Tests: dashboard renders, stats accurate, workspace isolation

---

## Module 09 — Team Members ⬜

- Invite team member by email (sends invite link)
- Accept invite → account creation or login → added to workspace
- Member list: name, email, role, joined date
- Change member role (OWNER only): ADMIN ↔ MEMBER
- Remove member (OWNER / ADMIN only)
- Roles:
  - `OWNER` — full access, billing, delete workspace
  - `ADMIN` — manage members, projects, assessments
  - `MEMBER` — run assessments, view results only
- Tests: invite flow, role gates, cannot invite duplicate, owner cannot remove self

---

## Module 10 — Settings ⬜

**Workspace settings (OWNER / ADMIN)**
- Workspace name
- Timezone preference
- Danger zone: delete workspace (with confirmation)

**User profile settings**
- Name, email
- Change password
- Delete account

---

## Module 11 — Notifications & Email ⬜

- Email service: Resend (already in stack)
- Platform toggle: `PlatformSetting::set('email.notifications_enabled', true/false)` — OFF by default
- When ON: send email on assessment submission, new member invite
- When OFF: all email methods return early silently, no errors
- In-app notification bell (unread count badge on sidebar)
- Notification types: assessment complete, member joined, report ready
- Tests: email not sent when OFF, queued when ON, notification records created

---

## Module 12 — Export & Sharing ⬜

- Export assessment report as PDF (generated server-side with `barryvdh/laravel-dompdf`)
- Export project data as CSV (all assessments + scores for a project)
- Shareable read-only report link (signed URL, no auth required, expires in 30 days)
- Tests: PDF generated, CSV column structure, shared link resolves, expired link rejected

---

## Module 13 — Platform Admin ⬜

> Super-admin panel. Accessible only to users where `is_platform_admin = true` on the central `users` table.

- Workspace list (search, filter by plan)
- View any workspace's projects and assessments (read-only)
- Toggle platform email ON/OFF (`PlatformSetting`)
- Module library management: add/edit/deactivate modules, domains, questions
- Seed new assessment modules from JSON/CSV upload
- Tests: admin gate (non-admin cannot access), module management CRUD

---

## Module 14 — Billing (v2, not v1) ⬜

> Paystack integration. Not in v1 — all workspaces on free plan during beta.

- Plans: Free (1 project, 3 assessments), Pro (10 projects), Agency (unlimited + team)
- Paystack webhook handler
- Plan limits enforced at project creation and assessment start
- Upgrade flow: Paystack Popup → webhook → update workspace plan
- Tests: plan limit enforcement, webhook signature validation

---

## Build sequence summary

```
01 Foundation        ✅  (done)
02 UI Shell          ⬜  (waiting design approval)
03 Projects          ⬜
04 Module Library    ⬜
05 Assessment Runner ⬜
06 Scoring Engine    ⬜
07 Results           ⬜
08 Dashboard         ⬜
09 Team Members      ⬜
10 Settings          ⬜
11 Notifications     ⬜
12 Export            ⬜
13 Platform Admin    ⬜
14 Billing           ⬜  (v2)
```

**v1 target: Modules 01–12** (complete functional platform, no billing)
**v2 target: Module 13–14** (admin panel, monetisation)
