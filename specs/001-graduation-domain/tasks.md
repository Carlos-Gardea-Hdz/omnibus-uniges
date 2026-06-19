# Tasks 001 — Graduation Domain (Form B vertical slice)

Derived from `plan.md`. **Phase 1 = sequential core** (dependency-ordered; each builds on
the prior). **Phase 2 = `[P]` parallelizable** (tests/docs/independent files). State in
`state.json`.

## Phase 0 — Feedback loop (do FIRST; nothing ships unverified)
- [ ] T00 — `docker-compose.yml` (Sail: `laravel.test` php8.5, `pgsql` postgres:18 + create
  `uniges_testing`, `valkey` 8). Bring up; confirm `sail artisan migrate` (baseline) green.
- [ ] T01 — `phpunit.xml` → `DB_CONNECTION=pgsql`, `DB_DATABASE=uniges_testing`,
  `BROADCAST_CONNECTION=null`; `.env.example` BROADCAST/REVERB/VITE_REVERB keys. ADR-001.

## Phase 1 — Sequential core (the contract spine; field names are law from plan §2)
- [ ] T10 — 5 catalog migrations (departments→programs→professors→graduation_types→study_plans).
- [ ] T11 — 5 catalog models (`final`, SoftDeletes, `$fillable`, `HasFactory`) + 5 factories.
- [ ] T12 — `add_role_to_users` migration; `User` model (`role` fillable+cast, `student()` HasOne).
- [ ] T13 — `students` migration (full SPEC §6.3.11 schema + CHECK constraints via DB::statement).
- [ ] T14 — `Address` VO (Shared) + unit-tested invariants.
- [ ] T15 — `Student` model (casts, relationships, VO accessors) + `StudentFactory` (fictional PII).
- [ ] T16 — `SubmitFormBData` + `ReviewFormBData` DTOs (`#[TypeScript]`, validation attrs).
- [ ] T17 — `StudentStatusChanged` event (ShouldBroadcast, channel/as/with per plan §3).
- [ ] T18 — `SubmitFormBAction`, `ApproveFormBAction`, `RejectFormBAction` (tx + state machine + dispatch).
- [ ] T19 — `EnsureRole` middleware + register alias in `bootstrap/app.php`.
- [ ] T20 — Controllers: `Student\FormBController`, `Graduation\FormBReviewController`,
  `Student\StatusController` (anemic ≤15 lines).
- [ ] T21 — `routes/web.php` (named, role mw) + `routes/channels.php` (`student.{id}` auth) +
  `HandleInertiaRequests` (`auth.user.role`).
- [ ] T22 — `php artisan typescript:transform` → regenerate `generated.d.ts`.

## Phase 2 — Parallel `[P]` (independent once the contract exists)
- [ ] T30 [P] — `Pages/Student/FormB.tsx` (typed `useForm`, i18n, a11y, rejection notes).
- [ ] T31 [P] — `Pages/Student/Status.tsx` + `Components/GraduationProgress.tsx` (Echo subscribe).
- [ ] T32 [P] — `Pages/Graduation/Review.tsx` (approve/reject, observations dialog).
- [ ] T33 [P] — `Components/form/{TextField,SelectField,FormError}.tsx`.
- [ ] T34 [P] — i18n keys `form.*`/`validation.*`/`admin.review.*` in `es.json`+`en.json`.
- [ ] T35 [P] — Pest Unit: `GraduationStateMachineTest`, `AddressTest`.
- [ ] T36 [P] — Pest Feature: `SubmitFormBTest`, `ReviewFormBTest`, `StatusBroadcastTest` (pgsql).
- [ ] T37 [P] — extend `tests/Arch/ArchitectureTest.php` (Actions/Events final; domain↛HTTP).
- [ ] T38 [P] — Vitest `FormB.test.tsx`.
- [ ] T39 [P] — `docs/decisions/ADR-001-id-strategy.md`.

## Phase 3 — Verify → Review → Ship
- [ ] T40 — Gates in order (plan §9): Pint → PHPStan L9 → ts:transform → migrate:fresh →
  Pest (Unit+Arch+Feature on pgsql) → tsc → eslint → vitest → vite build. Capture evidence.
- [ ] T41 — Adversarial review (@laravel-reviewer + verify) vs spec + §No-negociables.
- [ ] T42 — Commit (Conventional, no AI attribution), push `feature/graduation-domain`, open PR.
  **No self-merge.** Then archive (MEMORY.md/.agent-memory + changelog).
