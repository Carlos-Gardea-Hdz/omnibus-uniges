# CLAUDE.md — omnibus-uniges

This file is intentionally thin to avoid drift. The authoritative docs are:

- **AGENTS.md** — canonical agent rulebook + project context for this repo.
- **SPEC.md** — single source of truth for domain rules (the 9-state graduation
  workflow, RBAC, document/jury/ceremony logic). Read it before any architectural
  decision.
- **ARCHITECTURE.md** — target architecture.
- `~/.claude/AGENTS.md` — global OMNIBUS coding law (the law; read first).

Quick facts: Laravel 12 + PostgreSQL 18 + React 19/Inertia/TS. DDD-Lite + Action
Pattern, Spatie Laravel Data DTOs only (no FormRequests), `declare(strict_types=1)`
everywhere, Pest with arch tests enforcing domain boundaries.

> Do not duplicate domain rules here — they live in SPEC.md. If you find this file
> describing workflow states or domains, it is stale: defer to SPEC.md.
