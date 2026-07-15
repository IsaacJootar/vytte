# Vytte — UI Rules

## Design system

- Tailwind CSS v4 — CSS-first configuration via `@theme {}` in `resources/css/app.css`
- NO `tailwind.config.js`, NO DaisyUI
- Alpine.js bundled by Livewire 4 — NEVER import it separately in app.js
- Icons: blade-ui-kit/blade-heroicons (`<x-heroicon-o-*>`)

## Tailwind v4 configuration location

All design tokens live in `resources/css/app.css` under `@theme {}`.
Do not put configuration anywhere else.

## Color tokens (defined in app.css @theme)

```css
@theme {
  --color-vytte-50:  #f0fdf4;
  --color-vytte-100: #dcfce7;
  --color-vytte-200: #bbf7d0;
  --color-vytte-300: #86efac;
  --color-vytte-400: #4ade80;
  --color-vytte-500: #22c55e;
  --color-vytte-600: #16a34a;
  --color-vytte-700: #15803d;
  --color-vytte-800: #166534;
  --color-vytte-900: #14532d;

  --color-slate-50:  #f8fafc;
  /* ... standard slate palette ... */

  --color-danger-500: #ef4444;
  --color-warning-500: #f59e0b;
  --color-info-500: #3b82f6;
}
```

## Typography

- Font: Inter (loaded via Vite/CSS, not Google Fonts CDN — CSP blocks external fonts)
- Headings: font-semibold or font-bold
- Body: font-normal, text-slate-700 (light) / text-slate-200 (dark)

## Layout rules

- Mobile-first: 375px minimum. Test at 375px before marking UI complete.
- All layouts use `sm:` `md:` `lg:` Tailwind prefixes on every layout element.
- Main content area: `max-w-7xl mx-auto px-4 sm:px-6 lg:px-8`
- Cards: `bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700`

## Component naming

- Blade layouts: `resources/views/layouts/app.blade.php`, `layouts/guest.blade.php`
- Blade components: `resources/views/components/` — kebab-case filenames
- Livewire components: `app/Livewire/` — PascalCase classes, kebab-case view filenames
- Livewire views: `resources/views/livewire/` — mirror the class namespace in kebab-case

## Livewire component rules

- Every Livewire component that holds a workspace or project ID must declare it `#[Locked]`
- Use `mount(Model $model)` for DI — let route model binding do the work
- Tenant identifiers must be `#[Locked]` to prevent client-side tampering

## Navigation labels (shown to user)

| Internal name | UI label |
|---|---|
| Dashboard | Dashboard |
| Projects | Projects |
| Assessments | Assessments |
| Responses | Responses |
| Reports | Reports |
| Question Bank | Question Bank (curator only) |
| Platform Settings | Platform Settings (admin only) |

## Language rules

- Every label must be understood by a first-time user with no PHSAI training
- Never show PHSAI module codes (e.g., OPD.D1.Q1) to end users
- Show "Awaiting calibration" wherever an uncalibrated score would appear
- Errors in plain English — never show stack traces or raw exception messages

## Status badges

- `calibration_status = NOT_CALIBRATED` → amber badge "Awaiting calibration"
- Assessment `status = DRAFT` → slate badge "Draft"
- Assessment `status = IN_PROGRESS` → blue badge "In progress"
- Assessment `status = COMPLETED` → green badge "Completed"
- Assessment `status = ARCHIVED` → slate badge "Archived"

## Forms

- Required fields: no asterisk — use `required` HTML attribute
- Validation errors: shown inline below each field, text-danger-500 text-sm
- Submit buttons: `bg-vytte-600 hover:bg-vytte-700 text-white`
- Destructive buttons: `bg-danger-500 hover:bg-danger-600 text-white`
- Secondary/cancel: `bg-white border border-slate-300 text-slate-700`
