# ADR-001 — Primary key / ID strategy

- **Status:** Proposed (needs Carlos's decision — project-wide)
- **Date:** 2026-06-18
- **Context spec:** `specs/001-graduation-domain/` (surfaced while building the Graduation slice)

## Context

Three authorities disagree on how primary keys / IDs should be modeled:

1. **The foundation as built** — `users` uses `$table->id()` (auto-increment **bigint**);
   `sessions.user_id` is a `foreignId` (bigint). No `HasUlids`/`HasUuids` trait exists
   anywhere in `app/` or `database/`.
2. **SPEC.md §6.3.11** — domain tables (`students`, catalogs, jury, documents) specify
   `CHAR(26)` **ULID** primary keys and FKs.
3. **Global law** (`~/.claude/CLAUDE.md` → "The Law — Value Objects") — **"UUIDs: UUIDv7
   only (chronologically sortable)."**

A feature slice must not silently pick one and create a third, incompatible precedent.
FK column types must match their referenced PK, so this decision ripples across every
table in the system.

## Decision (for the Graduation slice — tactical)

For spec 001 the new tables (`students`, the 5 catalogs) use **bigint `$table->id()`**, and
`students.user_id` is a `foreignId` matching `users.id`. This keeps the slice internally
consistent with the existing foundation and ships without touching the `users` PK.

This is a **tactical** choice to unblock the slice — **not** the project-wide ruling.

## Open decision (strategic — Carlos to choose)

Pick ONE and apply it project-wide in a dedicated foundation PR before more domains land:

- **Option A — UUIDv7 everywhere** (follows the global law). Add `HasVersion7Uuids` (or
  `HasUuids` with a v7 generator) to a base model; migrate `users` + all domain tables to
  `uuid` PKs/FKs. Chronologically sortable, no enumeration leakage, matches the law.
  *Cost:* touches the foundation (auth, channels' `(int)` casts) and every migration.
- **Option B — ULID everywhere** (follows SPEC §6.3.11). `HasUlids` on a base model;
  `CHAR(26)` PKs/FKs. Sortable, compact, Laravel-native. *Cost:* same foundation churn;
  diverges from the global "UUIDv7 only" law (would need the law amended for this program).
- **Option C — keep bigint** (follows the foundation). Simplest, fastest joins; but
  violates both SPEC and the global law, and leaks row counts. *Not recommended.*

**Recommendation: Option A (UUIDv7)** — it satisfies the global law, is the modern default,
and SPEC's ULID intent (sortable, non-sequential) is preserved by v7's time-ordering. Then
update SPEC §6.3.11 to say UUIDv7, and this slice's bigint PKs get migrated in that same
foundation PR.

## Consequences

- Until the strategic decision lands, spec 001 ships with bigint IDs (reversible: the
  foundation PR will recreate these tables with the chosen ID type before any production data
  exists).
- `state.json` for spec 001 records this as a known deviation, not an oversight.
