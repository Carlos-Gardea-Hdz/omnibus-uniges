# Architecture — omnibus-uniges

This document records the **target structure and key decisions** for the
foundation. It is a concise companion to [`SPEC.md`](./SPEC.md) (the full,
authoritative spec) and the global vault rule modules
(`~/.claude/.agent-rules/{laravel-advanced,inertia-react-advanced,ui-ux-tailwind4,testing-sdd,security-2026}.md`).

## 1. DDD-Lite domain map

Business logic lives in `app/Domain/{Domain}/` with a per-domain layout of
`Models/ Actions/ Data/ Enums/ ValueObjects/ Events/ Exceptions/` (plus
`StateMachine/`, `Rules/`, `Services/`, `Policies/` where a domain needs them).
Shared, domain-agnostic code lives in `app/Domain/Shared/`.

| Domain      | Responsibility                                                  |
| ----------- | -------------------------------------------------------------- |
| Identity    | Users, 6-role RBAC, auth, login throttling                     |
| Academic    | Programs, Departments, Professors, StudyPlans, GraduationTypes |
| Graduation  | Students, the **9-state machine**, Form B, team members        |
| Documents   | Required/Student/Annex documents, generation                   |
| Jury        | Jury assignments and roles                                     |
| Ceremony    | Scheduling, diploma folio, record book                         |
| Reporting   | Efficiency, graduates, cohorts, certificates                   |
| Shared      | `Email` VO, services, traits                                   |

**Cross-domain isolation** is enforced by a Pest arch test: a domain must not
import another domain's internals. Shared value objects are the only universal
vocabulary.

## 2. Request lifecycle (the Action pattern)

```
HTTP request
  → Controller (anemic, ≤ 15 lines)
      → receives a Spatie Data DTO (validation = the DTO's attributes)
      → calls exactly one Domain Action: handle(SomeData $data): Entity
          → DB::transaction() for any multi-table write
          → emits Domain Events (e.g. StudentStatusChanged)
      → returns Inertia::render(...) with DTO-shaped props
```

Controllers never contain business logic and never touch Eloquent results to
make decisions. `$request->validate()` and FormRequests are **prohibited**:
Spatie Laravel Data is the single source of truth for validation rules *and*
the generated TypeScript types.

## 3. The 9-state graduation workflow

The core complexity. Modeled as:

- **`GraduationStatus`** (backed enum) — owns `step()`, `labelKey()`,
  `color()`, `allowedTransitions()`, `canTransitionTo()`, `isTerminal()`.
- **`GraduationStateMachine`** — a pure, dependency-free guard that asserts
  legality (`assertCanTransition`) at the domain level and throws
  `InvalidStatusTransitionException` on illegal moves.

Transitions are strict and sequential; the only branch is `FORM_B_REVIEW`
(reject → `FORM_B_REJECTED`, loopable back; or approve → `ANNEXES_PENDING`).
Skipping states is impossible by construction. Every transition will dispatch
`StudentStatusChanged`, broadcast over Reverb to the student's private channel.

## 4. Type safety: Laravel → React, one source of truth

DTOs and `#[TypeScript]` enums are transformed to
`resources/js/types/generated.d.ts` via `php artisan typescript:transform`.
The React frontend never re-declares server shapes. Shared Inertia props are
typed by augmenting `@inertiajs/react`'s `PageProps`
(`resources/js/types/index.ts`). The generated file is committed so a fresh
clone type-checks before the first transform.

## 5. Frontend

- **Inertia 2 + React 19** pages in `resources/js/Pages/` (one component per
  route, code-split by Vite). Entry: `app.tsx` (CSR/hydration) and `ssr.tsx`
  (SSR via `php artisan inertia:start-ssr`).
- **Dark/light mode** — class strategy on `<html>`, 3-state preference
  (light/dark/system) in `ThemeContext`, FOUC prevented by a synchronous
  inline script in `app.blade.php` that sets `.dark` before first paint.
- **Bilingual ES/EN** — `LocaleContext` + `resources/locales/{es,en}.json`.
  Spanish-first (`APP_LOCALE=es`), English fallback. No hardcoded UI strings.
- **Real-time** — `echo.ts` wires Laravel Echo to Reverb (Pusher protocol)
  using only the public app key (never the secret).

## 6. Design system (Tailwind v4, CSS-first)

`resources/css/app.css` defines a **3-tier token architecture**: institutional
green primitives (SPEC §1.5, expressed in OKLCH) → semantic tokens
(`surface`, `fg`, `border`, `accent`, …) → component usage. Dark mode swaps
**semantic token values**, so `bg-surface` / `text-fg` work in both themes with
near-zero `dark:` variants. No `tailwind.config.js` (v4 removes it). Targets
WCAG 2.2 AA: visible `:focus-visible` rings, ≥ 24px targets, skip link,
`prefers-reduced-motion` honored for decorative motion.

## 7. Security baseline

- Spatie Data DTOs validate all input server-side; Inertia props shaped with
  `->only()` so no secrets/unauthorized fields leak into `data-page`.
- `SecurityHeadersMiddleware` sets `nosniff`, `Referrer-Policy`, COOP,
  `X-Frame-Options`, `Permissions-Policy`; HSTS terminates at Traefik in prod.
- Sessions/cache/queues on Valkey; `Secure`+`HttpOnly`+`SameSite=Lax` cookies.
- Real PII (CURPs/RFCs/names) is **never** seeded, logged, or committed —
  `.gitignore` blocks `secrets/`, db dumps, sqlite, and letterhead assets.

## 8. Testing strategy

- **Pest 4** — `tests/Unit` (pure domain), `tests/Feature` (HTTP + Inertia
  assertions), `tests/Arch` (the OMNIBUS Law as executable rules: strict types,
  final classes, readonly VOs, backed enums, anemic controllers, domain↛HTTP,
  cross-domain isolation). DB-backed Feature tests run against PostgreSQL 18
  (the prod engine), never SQLite.
- **Vitest 4** — React component/logic tests (Testing Library, query by role).
- **Static** — Larastan/PHPStan level 9, Laravel Pint, ESLint (react-hooks),
  `tsc --noEmit`.

## 9. Deployment

`docker-compose.prod.yml` runs the full stack behind Traefik v3
(Let's Encrypt TLS): app (PHP-FPM), Reverb, Horizon queue worker, scheduler,
PostgreSQL 18, Valkey 8. The multi-stage `Dockerfile` builds frontend assets
(node:24-alpine) and vendor (composer:2) into a `php:8.5-fpm-alpine` runtime
with tuned OPcache. Octane/FrankenPHP is the documented upgrade path for the
HTTP server.
