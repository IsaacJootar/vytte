# Deferred Features

The following features are intentionally deferred until after beta.

## Billing and commerce

- Subscriptions
- Payment providers
- Billing
- Invoices
- Payment ledger
- Idempotent payment processing
- Refunds
- Taxes
- Coupons
- Trials
- Usage-based billing
- Enterprise licensing
- Custom contracts

## Advanced administration

- Multi-organization administration
- Dependency graph visualization
- Version comparison UI
- Advanced workflow builder
- Rich framework visual designer
- Bulk editing tools
- Marketplace
- Public template library
- Template import/export

## Platform integrations

- API platform
- Public REST API
- Webhooks
- Partner integrations
- SSO
- SCIM
- Azure AD
- Google Workspace provisioning

## Analytics and intelligence

- Advanced audit analytics
- Data warehouse integration
- Business intelligence connectors
- Advanced analytics
- Advanced dashboards
- Benchmark intelligence
- Advanced reporting

## Experience expansion

- White labeling
- Localization beyond current foundational controls
- Multi-language assessment publishing at scale
- Offline/mobile applications
- AI-assisted framework authoring
- AI-assisted recommendations
- Workflow automation

## Custom-assessment depth — DELIVERED 2026-07-26 (see DEC-2026-07-26-037, DEC-2026-07-26-041)

The three-layer model is implemented. Custom "Tailored by your team" sections are built:
a workspace adds its own questions (add-only; governed questions are never removed, so the
official score stays comparable), answers them, and they are scored on the same 0-100 scale in
a private lane via `CustomSectionScoringService`, shown as a "Tailored by your team" block on
the results page, shared report and PDF. Answers and the private score live on
`local_custom_sections`; the official snapshot and score are untouched.

Possible later polish (not scheduled): per-question weighting (currently equal weight), and
free-text/number response types (currently Yes/No and 1-5 scale).
