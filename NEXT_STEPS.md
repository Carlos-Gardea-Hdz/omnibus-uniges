# NEXT_STEPS — omnibus-uniges

Foundation scaffolded 2026-06-18 (commit `75298b7`, 135 files). This file lists what was verified and what remains; build the domain out via `/sdd`.

## Verified at scaffold time

Verified the real toolchain in Docker (composer:2 / PHP 8.5.7) and host pnpm/node 24, twice — once in the build dir and again from a clean checkout of the merged repo (fresh composer install + fresh pnpm install --frozen-lockfile). Backend (all PASS from clean): Laravel Pint --test 45/45 files; PHPStan/Larastan level 9 — No errors; Pest 28 passed / 51 assertions (Unit + Feature with Inertia assertions + 8 Arch tests). php artisan typescript:transform successfully generated 3 TS types (GraduationStatus, LoginData, UserRole) proving the Laravel→React contract. Frontend (all PASS from clean): tsc --noEmit clean; ESLint clean; Vitest 2/2; vite production build succeeds (Tailwind v4 CSS compiled, React/Inertia bundle, code-split Welcome page). composer audit + pnpm: No security vulnerability advisories. react/react-dom resolved to 19.2.7 (patched, > 19.2.1 per CVE-2025-55182 rule). PII scan: no real CURP/RFC/names — only a composer.lock hash false-positive. Confirmed no .env/secrets/vendor/node_modules staged.

## Known gaps / issues

- The repo was NOT freshly git-initialized: an existing git repo with history (3 prior commits) was already present on branch main; I committed on top of it rather than re-initializing (re-init would have destroyed the user's history). gitInitialized=false reflects this honestly.
- The committed CLAUDE.md (in HEAD) was missing from the working tree (staged as deleted) and contains an OUTDATED 9-state workflow (PENDING→DOCUMENTS_SUBMITTED→...→GRADUATED) and stale domain names (Committees/Synod, 'T-Soft') that CONTRADICT the authoritative SPEC.md and AGENT.md (FORM_B_PENDING→...→GRADUATED, Jury domain). I restored CLAUDE.md from HEAD to avoid destroying user content and built GraduationStatus to follow SPEC.md (the SSOT), but the user's own CLAUDE.md is internally inconsistent and should be reconciled.
- There are now THREE doc files with overlapping roles: AGENT.md (untracked, 99 lines, canonical project doc), the new AGENTS.md (vault pointer I created), and the stale CLAUDE.md. The user should decide which is canonical (AGENT.md vs AGENTS.md naming).
- Horizon and Inertia config publishing via artisan failed inside the composer container due to a DB-cache lookup (CACHE_STORE needs a DB); I hand-wrote config/inertia.php and the packages work on defaults, but config/horizon.php was not published. Reverb config/routes were published.
- Feature/DB tests currently inherit the default phpunit.xml SQLite :memory: connection; per testing-sdd rule, DB-backed Feature tests should run against PostgreSQL 18 in CI. The current foundation tests are DB-free so this is not yet exercised, but the CI env must point DB_CONNECTION at Postgres before real Feature tests land.
- PHPStan scope was narrowed to app/routes/database (excluding config/ and tests/) because the default Laravel config/filesystems.php has a known L9 false-positive and Pest's fluent arch DSL is not statically analyzable. This is standard practice but means config/ is not type-checked.
- composer.json php requirement was set to ^8.3 (the framework/Pest floor) rather than ^8.5; production runs PHP 8.5 (Dockerfile uses php:8.5-fpm-alpine). pnpm install needs onlyBuiltDependencies:[esbuild] approved (configured in pnpm-workspace.yaml).

## Next steps

- [ ] Reconcile the stale CLAUDE.md against SPEC.md/AGENT.md: fix the 9-state workflow names and domain list, or replace CLAUDE.md content, and decide AGENT.md vs AGENTS.md as the canonical doc name.
- [ ] Run `php artisan vendor:publish --tag=horizon-config` and configure Horizon environments/queue split (default/mail/exports/webhooks) per laravel-advanced §3 once a DB is available.
- [ ] Generate Reverb credentials (REVERB_APP_ID/KEY/SECRET) into .env and wire createEcho() into the app for the real-time graduation channel.
- [ ] Point CI/test DB at PostgreSQL 18 (override phpunit.xml DB_CONNECTION=pgsql) before adding RefreshDatabase Feature tests; never test on SQLite.
- [ ] Build out the domain incrementally via the SDD flow (/sdd): Students model + 9-state migrations (FK restrict + SoftDeletes, only student_documents cascades), Actions (SubmitFormB/ApproveFormB/...) each transactional and dispatching StudentStatusChanged, and the broadcasting Reverb event.
- [ ] Add the DemoSessionMiddleware + `php artisan demo:cleanup` scheduled command and 6 demo presets (fictional data only — no real PII) per the Demo Mode spec.
- [ ] Wire a CI pipeline (GitHub Actions, SHA-pinned) running: pint --test, phpstan L9, pest --coverage --min, composer audit, pnpm audit, tsc, eslint, vitest, vite build, and axe a11y checks; add Pest mutation testing on Domain/Actions.
- [ ] Add favicon.ico/logos and OG assets (currently a placeholder favicon from the Laravel skeleton).
