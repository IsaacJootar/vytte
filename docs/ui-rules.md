# Vytte UI Rules

## Design system

- Tailwind CSS 4 with CSS-first configuration in `resources/css/app.css`
- No `tailwind.config.js` and no DaisyUI
- Alpine.js is bundled by Livewire 4; do not import it separately
- Icons use `blade-ui-kit/blade-heroicons`
- Mobile-first at a 375px minimum viewport

## Brand tokens

`resources/css/app.css` is authoritative.

```css
@theme {
  --color-vytte-50:  #F0F9FF;
  --color-vytte-100: #E0F2FE;
  --color-vytte-200: #BAE6FD;
  --color-vytte-300: #7DD3FC;
  --color-vytte-400: #38BDF8;
  --color-vytte-500: #0EA5E9;
  --color-vytte-600: #0284C7;
  --color-vytte-700: #0369A1;
  --color-vytte-800: #075985;
  --color-vytte-900: #0C4A6E;
  --color-vytte-950: #082F49;

  --color-navy:      #0C1929;
  --color-navy-deep: #060F1C;
}
```

Ocean Blue is the Vytte brand family. Navy is reserved for the app shell/sidebar. Green, amber, and red communicate result state or alerts; they are not brand-primary colors.

## Typography and layout

- Font: Inter with system sans-serif fallback; do not depend on an external font CDN
- Headings: `font-semibold` or `font-bold`
- Body: slate text with a dark-mode counterpart
- Main content: responsive horizontal padding and a bounded readable width
- Cards: white/slate surface, subtle slate border, rounded corners
- Use responsive stacking before horizontal scrolling
- Every interactive control needs a visible focus state and meaningful text

## Components

- Layouts: `resources/views/layouts/`
- Blade components: kebab-case files in `resources/views/components/`
- Livewire classes: PascalCase under `app/Livewire/`
- Livewire views: kebab-case under `resources/views/livewire/`
- Reuse existing buttons, score displays, navigation items, and form components before adding variants

## Livewire security

- Client-visible tenant, project, assessment, token, and respondent identifiers must be locked
- Route binding is not sufficient authorization; mutations re-check workspace and resource authority
- Question and option IDs are revalidated against the immutable assessment snapshot or active in-scope content
- Completed assessments are read-only

## Product language

- Use plain language understood without internal methodology training
- User-facing product name is Vytte
- Use **Comprehensive Health Assessment** and **Focused Health Assessment**
- Use “department” only where the selected setting genuinely has departments
- Do not show internal question codes to ordinary end users
- Show “Awaiting calibration” for `NOT_CALIBRATED`
- Evidence UI says “optional supporting evidence” and remains progressively disclosed
- Community and patient feedback are templates, not a separate product area

## Canonical status labels

| Stored value | Scope | UI label |
|---|---|---|
| `IN_PROGRESS` | Assessment | In progress |
| `COMPLETE` | Assessment | Completed |
| `PENDING` | Included assessment area | Pending |
| `COMPLETED` | Included assessment area | Completed |
| `EXCLUDED` | Assessment area | Excluded |
| `DRAFT` | Template/version | Draft |
| `PUBLISHED` | Template/version | Published |

Do not persist assessment `COMPLETED`, `DRAFT`, or `ARCHIVED`; they are not assessment-execution states.

## Forms and feedback

- Use native `required` semantics and inline validation messages
- Primary action: Ocean Blue background with white text
- Destructive action: red semantic color
- Secondary action: neutral surface and border
- Never expose stack traces or raw provider exceptions
- Destructive actions must state what will happen
- Empty states should explain the next useful action

## Navigation

Primary workspace navigation is Dashboard, Projects, Assessments, Reports, Modules, Team, and Notifications. Reports always opens the shared completed-assessment report index; it must not point to a placeholder.
