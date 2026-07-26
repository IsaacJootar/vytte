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

## Custom-assessment depth (model agreed 2026-07-26 — see DEC-2026-07-26-037)

The three-layer model is agreed and the fully-custom (Layer 3) surface is folded into
Assessments. The deeper engineering is deferred:

- **In-context question editor (Layer 2):** a UI to add your own questions/sections to a
  governed assessment run, stored as `LocalCustomSection`, private to the workspace. The
  data model exists; the interface does not.
- **Separate custom scoring:** scoring those local sections in their own lane and rendering a
  "Your custom section" block in the report, distinct from the official Vytte score. Touches
  the scoring and report engines, so scheduled as its own focused build rather than bundled
  with UI work.
