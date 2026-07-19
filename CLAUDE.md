# Vytte — Repository Entry Point

**`AGENTS.md` is the authoritative engineering guide. Read it before changing anything.**

This file is a pointer only. It exists because some tooling loads `CLAUDE.md` automatically. It
deliberately holds no rules of its own, so that engineering guidance has exactly one home.

## What Vytte is

Vytte is a platform-governed health assessment system. Vytte authors and publishes immutable,
versioned, provenance-tracked assessment methodology. Customer workspaces consume that published
methodology to run assessments and receive scored, reproducible reports.

There are exactly two assessment creation paths: **Comprehensive Health Assessment** (a composition
orchestrator over a published catalogue release) and **Focused Health Assessment** (one health
domain, programme, topic, or intervention).

## Where to read next

| Question | Document |
|---|---|
| How do I work in this repository? | `AGENTS.md` |
| What is the implemented architecture? | `docs/architecture/CURRENT_ARCHITECTURE.md` |
| How does an assessment run end to end? | `docs/architecture/CURRENT_ASSESSMENT_FLOW.md` |
| How is official content structured? | `docs/architecture/QUESTION_BANK_ARCHITECTURE.md` |
| What decisions control the design? | `docs/architecture/DECISION_LOG.md` |
| What must never be broken? | `docs/architecture/PRESERVATION_REGISTER.md` |
| How do I set the project up? | `README.md` |

Repository code and migrations are the technical source of truth. Historical records are preserved
under `docs/architecture/archive/` and describe past states, not the current system.

## Git identity

```bash
git config user.name "Isaac Jootar"
git config user.email "jootarisaac@gmail.com"
```
