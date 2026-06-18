# UNIGES — University Graduation Management System

Laravel 12 rebuild of the UNIGES titulación platform (Portfolio Project B,
Chapters 3.4–3.13 of the Biblia de Laravel). The heart of the system is a
strict **9-state graduation workflow** with real-time status updates over
WebSockets.

- **Production:** https://titulacion.carlosgardea.com
- **Specification:** [`SPEC.md`](./SPEC.md) is the single source of truth.
- **Agent law:** see [`AGENTS.md`](./AGENTS.md).

> Demo mode: the deployed app is fully explorable by guests (ephemeral,
> sandboxed sessions reset every 15 minutes). See SPEC §"Demo Mode".

## Stack

| Layer        | Technology                                                   |
| ------------ | ------------------------------------------------------------ |
| Runtime      | PHP 8.5, Laravel 12                                          |
| Database     | PostgreSQL 18                                                |
| Cache/Queue  | Valkey 8.0 (Redis protocol) + Horizon                        |
| Real-time    | Laravel Reverb (self-hosted WebSockets) + Laravel Echo       |
| Frontend     | React 19 + TypeScript 5.9 + Inertia.js 2 + Vite 7            |
| Styling      | Tailwind CSS v4 (Oxide, CSS-first) — dark/light + ES/EN      |
| Validation   | Spatie Laravel Data v4 (DTOs = rules + generated TS types)   |
| Testing      | Pest 4 (unit/feature/arch) + Vitest 4                        |
| Quality      | Larastan/PHPStan level 9, Laravel Pint, ESLint + Prettier    |

## Architecture

DDD-Lite: domain code lives in `app/Domain/{Identity,Academic,Graduation,
Documents,Jury,Ceremony,Reporting,Shared}`. Controllers are anemic
(DTO → Action → Inertia response). See [`ARCHITECTURE.md`](./ARCHITECTURE.md).

## Getting started (local)

This repo is built and verified with the real toolchain inside Docker
(the host carries Node + pnpm + Docker only). The canonical workflow is
Laravel Sail / Docker Compose.

### 1. Install dependencies

```bash
# PHP dependencies (via the composer container)
docker run --rm -v "$PWD":/app -w /app composer:2 \
  composer install --ignore-platform-req=ext-pcntl

# Frontend dependencies (pnpm — never npm)
pnpm install
```

### 2. Configure environment

```bash
cp .env.example .env
docker run --rm -v "$PWD":/app -w /app composer:2 php artisan key:generate
# Set REVERB_APP_ID / REVERB_APP_KEY / REVERB_APP_SECRET in .env.
```

### 3. Database (PostgreSQL 18)

```bash
php artisan migrate
```

### 4. Run

```bash
pnpm dev          # Vite dev server (HMR)
php artisan serve # or `composer dev` to run server+queue+vite together
php artisan reverb:start   # WebSocket server
php artisan horizon        # queue worker dashboard
```

Open http://localhost:8000.

## Quality gates

Run the full gate before every commit:

```bash
composer format       # Laravel Pint
composer analyse      # Larastan / PHPStan level 9
composer test         # Pest (unit + feature + arch)
composer types        # php artisan typescript:transform (regenerate TS types)

pnpm types            # tsc --noEmit
pnpm lint             # ESLint (react-hooks)
pnpm test             # Vitest
pnpm build            # production asset build
```

## Project layout

```
app/Domain/        DDD-Lite business domains (Models, Actions, Data, Enums, VOs)
app/Http/          Anemic controllers + middleware (Inertia, security headers)
resources/js/      React 19 + Inertia pages, components, contexts, types
resources/css/     Tailwind v4 CSS-first theme (institutional green, OKLCH)
resources/locales/ Bilingual ES/EN UI dictionaries
tests/Unit         Pure domain logic (VOs, enums, state machine)
tests/Feature      HTTP + Inertia assertions
tests/Arch         Executable DDD-Law boundaries (fails the build on violation)
docker-compose.prod.yml  Production stack (Traefik v3 + PostgreSQL + Valkey + Reverb)
```

## License

MIT — see [`LICENSE`](./LICENSE).
