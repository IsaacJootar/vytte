# Plan Feature Catalogue

`PlanService::FEATURES` is the code authority. `PlanFeatureSeeder` initializes the matrix, and platform admins may change enabled values.

| Key | Purpose |
|---|---|
| `team_members` | Workspace team management |
| `shareable_public_links` | External respondent links |
| `shareable_report_links` | Governed public report links |
| `progress_maturity_tracking` | Project history and comparisons |
| `localization` | Multi-language assessment UI/content |
| `pdf_export_no_watermark` | Unwatermarked PDF export |
| `csv_export` | Project CSV export |

Community, patient, caregiver, and citizen assessment templates are not plan features. They use the normal template catalogue and assessment architecture.

Unknown feature keys fail closed. Plan checks supplement authorization; they never replace workspace or resource policies.

