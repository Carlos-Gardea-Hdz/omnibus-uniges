# Plan 007 — Dashboards (the HOW)

> **Phase:** Plan. Turns `specs/007-dashboards/spec.md` into the concrete
> architecture / files-to-touch. Gated on `CLAUDE.md` §No-negociables.
> **Read first:** `spec.md` (this folder), `specs/006-demo-mode/spec.md` +
> `plan.md` (the DemoScope/DemoContext/middleware the counts ride on),
> `specs/001-graduation-domain/spec.md`.
> **Stack:** Laravel 12 + Inertia 2 + React 19 + TS + PostgreSQL 18 (Sail).
> **Slice nature:** READ-ONLY — two anemic controllers + two Inertia pages + a
> one-line `RoleLandingRoute` edit + locale keys + tests. **No migration, no
> Action, no DTO, no domain class.**

## 1. Recon — what already exists (verified, do NOT recreate)

- **`GraduationStatus` enum** (`app/Domain/Graduation/Enums/GraduationStatus.php`)
  — 9 cases, `step(): int` (1-9), `labelKey(): string` (`status.<value>`),
  `color(): string` (`primary|warning|danger|success`), `cases()`. Reuse for the
  histogram normalisation + per-row label/color/step.
- **`Student` model** (`app/Domain/Graduation/Models/Student.php`) — `status` cast
  to `GraduationStatus`; **`DemoScope` global scope registered in `booted()`**
  (the counts ride this — never bypass). `user()` belongsTo; `User::student()`
  hasOne. `control_number`, `first_name`, `last_name`, `form_b_observations`.
- **`RoleLandingRoute`** (`app/Http/Support/RoleLandingRoute.php`) — the
  post-login map; exhaustive `match` over `UserRole`. EDIT two arms here.
- **Routes** (`routes/web.php`) — the student group
  `['auth','demo','role:student']` and the staff group
  `['auth','demo','role:admin,super_admin,secretary']` already exist. ADD one
  route to each.
- **Existing staff queue controllers** (`app/Http/Controllers/Graduation/`):
  `FormBReviewController` (lists `FormBReview`), `DocumentReviewController`
  (`AnnexIiiPending`), `JuryController` (`PaymentPending`), `CeremonyController`
  (`JuryAssigned` + `CeremonyScheduled`). Their `where('status', …)` predicates
  are the SSOT for the queue→status mapping (spec §2.2). They paginate + `->through`
  to a snake_case array — mirror that shaping style.
- **`StatusController`** (`app/Http/Controllers/Student/StatusController.php`) —
  the `$request->user()?->student` resolution pattern + the null-safe full-name
  composition. Mirror it in `Student\DashboardController`.
- **Frontend:** `GraduationProgress.tsx` (9-step bar, fed a `GraduationStatus`
  value + optional `progress`), `Status.tsx` (page chrome: header +
  `LanguageSwitcher` + `DemoBanner` + graduated celebration block + `#main`),
  `Review.tsx` (table/card + `useLocale` patterns), `DemoBanner.tsx`,
  `LocaleContext`/`useLocale`. `resources/js/types/generated.d.ts` (type-only
  `GraduationStatus`). `PageProps`/`SharedProps` in `resources/js/types/index.ts`
  (already carry `demo`).
- **Locale files:** `resources/locales/{es,en}.json` (FLAT dotted keys; frontend
  copy). `lang/{es,en}.json` (FLAT dotted keys; PHP `__()`). The 9 `status.<value>`
  labels already exist (reused).
- **Tests:** `tests/Feature/Graduation/{ReviewPagePropsTest,StatusPagePropsTest}.php`
  (the `assertInertia` prop-contract pattern), `tests/Feature/Demo/DemoIsolationTest.php`
  (the `demoStaffWithStudent()` + `->withSession([...])` fixture for demo-scope
  tests — reuse its shape), `tests/Arch/ArchitectureTest.php`. Vitest:
  `resources/js/Pages/Auth/__tests__/DemoChooser.test.tsx` (the `@inertiajs/react`
  mock + `vi.hoisted` pattern; provide `demo:null` so `DemoBanner` is inert).
- **Factories:** `UserFactory` (`student/admin/superAdmin/secretary/
  assistantSecretary/schoolServices`), `StudentFactory`
  (`formBReview/formBRejected/documentsStage/annexesPending/paymentPending/
  paymentVerified/juryAssigned/ceremonyScheduled/ceremonyPassed`). Reuse — do NOT
  add states.

## 2. Files to touch

### Create

- `app/Http/Controllers/Student/DashboardController.php`
- `app/Http/Controllers/Graduation/AdminDashboardController.php`
- `resources/js/Pages/Student/Dashboard.tsx`
- `resources/js/Pages/Graduation/AdminDashboard.tsx`
- `tests/Feature/Dashboard/StudentDashboardPagePropsTest.php`
- `tests/Feature/Dashboard/AdminDashboardPagePropsTest.php`
- `tests/Feature/Dashboard/AdminDashboardQueryCountTest.php`
- `tests/Feature/Dashboard/DashboardRoleGatingTest.php`
- `tests/Feature/Dashboard/DemoAdminDashboardIsolationTest.php`
- `resources/js/Pages/Student/__tests__/Dashboard.test.tsx`
- `resources/js/Pages/Graduation/__tests__/AdminDashboard.test.tsx`

### Edit

- `routes/web.php` — add `GET /student/dashboard` to the student group; add
  `GET /admin/dashboard` to the staff group (import the two controllers).
- `app/Http/Support/RoleLandingRoute.php` — `Student` → `student.dashboard`;
  `Admin|SuperAdmin|Secretary` → `admin.dashboard`.
- `resources/locales/es.json` + `resources/locales/en.json` — add the
  `dashboard.student.*` + `dashboard.admin.*` keys (spec §2.5).

### Do NOT touch

- No migration. No `lang/{es,en}.json` (no PHP `__()` added). No domain file. No
  DTO/Action. No `DemoScope`/`DemoContext`/middleware change. The existing task
  screens (`Status.tsx`, `Review.tsx`, …) stay as-is.

## 3. Controller designs

### 3.1 `Student\DashboardController` (anemic, ≤15 lines)

```php
final class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $student = $request->user()?->student;          // DemoScope-isolated hasOne
        $status = $student?->status;                     // GraduationStatus|null (enum cast)

        return Inertia::render('Student/Dashboard', [
            'student_id' => $student?->id,
            'control_number' => $student?->control_number,
            'full_name' => $student === null ? null
                : trim("{$student->first_name} {$student->last_name}"),
            'status' => $status?->value,
            'step' => $status?->step(),
            'total_steps' => 9,
            'is_graduated' => $status === GraduationStatus::Graduated,
            'cta' => $this->cta($status),
            'form_b_observations' => $student?->form_b_observations,
        ]);
    }
}
```

- `cta(?GraduationStatus $status): ?array` — a private pure `match` returning
  `['route' => route('<name>'), 'label_key' => '<key>']` per spec §2.3, or `null`
  for `Graduated`/null. Keep `index` ≤15 lines by extracting `cta()`. The
  `route(...)` call resolves a URL string (relative is fine for Inertia `<Link>`).
- No write, no Action. `status` serialised as `?->value` (the enum value string).

### 3.2 `Graduation\AdminDashboardController` (anemic, ≤15 lines)

```php
final class AdminDashboardController extends Controller
{
    public function index(): Response
    {
        // ONE grouped-count aggregate, DemoScope-scoped (never bypass it).
        $counts = Student::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');                 // ['jury_assigned' => 3, ...]

        return Inertia::render('Graduation/AdminDashboard', [
            'status_breakdown' => $this->breakdown($counts),
            'queues' => $this->queues($counts),
            'totals' => $this->totals($counts),
        ]);
    }
}
```

- `breakdown(Collection $counts): array` — iterate **`GraduationStatus::cases()`**
  (guarantees all 9, in declaration = `step()` order), each row
  `['status' => $case->value, 'step' => $case->step(), 'label_key' =>
  $case->labelKey(), 'color' => $case->color(), 'count' => (int) ($counts[$case->value] ?? 0)]`.
- `queues(Collection $counts): array` — fixed 5 rows; each `['key' => …,
  'label_key' => "dashboard.admin.queue.<key>", 'count' => (int) ($counts[<status value>] ?? 0),
  'route' => route('<queue index name>')]`. Mapping (spec §2.2): `form_b` ↔
  `form_b_review`/`admin.graduation.review`; `documents` ↔
  `annex_iii_pending`/`admin.graduation.documents.index`; `jury` ↔
  `payment_pending`/`admin.graduation.jury.index`; `ceremony` ↔
  `jury_assigned`/`admin.graduation.ceremony.index`; `graduation` ↔
  `ceremony_scheduled`/`admin.graduation.ceremony.index`.
- `totals(Collection $counts): array` — `students = (int) $counts->sum()`,
  `graduates = (int) ($counts['graduated'] ?? 0)`, `in_progress = students - graduates`.
- **All three derive from the single `$counts`** — no extra query. NEVER
  `withoutGlobalScope`. The three private helpers keep `index` ≤15 lines.
- PHPStan note: `$counts` is `Collection<string,int>`; cast `(int)` when reading
  buckets. `selectRaw('status, count(*) as total')` is safe (no user input).

## 4. `RoleLandingRoute` edit (exhaustive match preserved)

```php
return match ($role) {
    UserRole::Student => 'student.dashboard',                                  // was student.status
    UserRole::Admin, UserRole::SuperAdmin, UserRole::Secretary => 'admin.dashboard', // was admin.graduation.review
    UserRole::AssistantSecretary, UserRole::SchoolServices => 'landing',       // unchanged
};
```

Real-login + demo-login both route through this (auth slice). The old routes stay
named/reachable. The match stays total over the 6-role enum (adding a role is a
compile-time obligation).

## 5. Frontend designs

### 5.1 `Student/Dashboard.tsx`

- Props interface = spec §2.3 student shape (snake_case). Type-only
  `import type { GraduationStatus } from '@/types/generated'`.
- Page chrome mirrors `Status.tsx`: `bg-surface text-fg`, header with
  `app.name` + `LanguageSwitcher`, `<DemoBanner />`, one `<main id="main">`, one
  `<h1>{t('dashboard.student.title')}</h1>`.
- Body: name/control-number `<dl>` (like `Status.tsx`); a status badge coloured
  by mapping `color` → the existing token classes (`primary→bg-accent`,
  `warning→bg-warning`, `danger→bg-danger`, `success→bg-success`; reuse the
  `Status.tsx`/`DocumentStatusBadge` styling); `<GraduationProgress status={status} />`
  for the bar; a semantic `<ol>` step list (9 items) where each item is `done`
  (`step_number < step`), `current` (`=== step`), or `pending` (`> step`) with a
  **non-color** textual cue (`t('dashboard.student.step.done|current|pending')`)
  plus the `t('status.<value>')` label for that step — derive the per-step status
  value from a static `STEP_STATUSES` array mirroring `GraduationProgress`'s
  `STEP_BY_STATUS` (keep them in lockstep). Primary CTA: when `cta` is non-null an
  Inertia `<Link href={cta.route}>` styled as the prominent button with
  `t(cta.label_key)`; when `cta` is null (graduated) render the celebration block
  (copy from `Status.tsx`'s graduated section, keys `dashboard.student.graduated.*`).
  When `status === ('form_b_rejected' as GraduationStatus)` and
  `form_b_observations`, show an observations callout.
- a11y: `<ol>` for steps, CTA is a real link, badge has text not just color,
  dark/light via tokens, locale via `useLocale`.

### 5.2 `Graduation/AdminDashboard.tsx`

- Props interface = spec §2.3 admin shape. No generated-enum import needed
  (everything arrives as plain strings/numbers).
- Chrome mirrors `Review.tsx` (header + `LanguageSwitcher` + `DemoBanner` +
  `#main` + `<h1>{t('dashboard.admin.title')}</h1>` + subtitle).
- **Totals row:** a `<dl>` of 3 stat cards (`totals.students/graduates/in_progress`)
  with `t('dashboard.admin.totals.*')` labels.
- **9-state breakdown:** a responsive grid of count tiles, one per
  `status_breakdown` row, each showing `t(row.label_key)`, the `count`, and a
  color accent from `row.color` token (text label always present — never
  color-only). In `step` order (the array already is).
- **Queues:** a row of 5 quick-link cards; each `t(queue.label_key)`, a count
  badge, and an Inertia `<Link href={queue.route}>{t('dashboard.admin.queue.open')}</Link>`.
- Empty state: when `totals.students === 0`, show `t('dashboard.admin.empty')`.
- a11y: stat/breakdown as labelled lists (`<dl>`/`<ul>`), counts have text labels,
  links are real links, dark/light + locale.

## 6. i18n keys to add

Add to **both** `resources/locales/es.json` and `resources/locales/en.json` (flat
dotted, ES + EN values) every key in spec §2.5 (`dashboard.student.*` and
`dashboard.admin.*`). The `status.<value>` labels are already present (reused).
**No** `lang/{es,en}.json` change (the controllers flash nothing). A Vitest/Pest
note: there is **no** PHP lang-key resolution test for this slice (nothing to
resolve); the frontend keys are exercised by the Vitest page tests via
`useLocale`.

## 7. Tests (build exactly the spec §2.6 list)

- Feature (PostgreSQL 18, `uses(RefreshDatabase::class)`,
  `use function Pest\Laravel\actingAs`): the 5 `tests/Feature/Dashboard/*` files.
  - `AdminDashboardQueryCountTest`: use `DB::enableQueryLog()` /
    `DB::listen(fn ($q) => …)` around the request; assert exactly one query whose
    SQL contains `group by` against `students` produced the histogram (no per-state
    loop). Falsifiable.
  - `DemoAdminDashboardIsolationTest`: reuse the `DemoIsolationTest`
    `demoStaffWithStudent()` fixture shape (mint a demo admin + tagged students via
    `forceFill(['demo_session_id' => $id])`, act `->withSession([is_demo, demo_session_id,
    demo_preset, demo_expires_at])`), plus real students; assert the demo
    dashboard `totals`/`status_breakdown` count only the demo session's students,
    and a real admin counts only real ones; falsifiable guard via
    `Student::withoutGlobalScopes()->count()`.
  - `DashboardRoleGatingTest`: guest→`login`; cross-role→403; right role→200, for
    both routes (incl. `assistant_secretary`/`school_services`→403 on admin).
- Arch: confirm the two new controllers pass the existing `final` /
  `declare(strict_types=1)` / `App\Domain ↛ Illuminate\Http` rules (no new rule).
- Vitest: the 2 page tests; mock `@inertiajs/react` (`Link`, `usePage` returning
  `{ props: { demo: null } }`) per the `DemoChooser.test.tsx` pattern; wrap in
  `ThemeProvider`+`LocaleProvider`.

## 8. Quality gates (per CLAUDE.md; run by the implement/verify phase, not here)

`composer format` → `composer analyse` (Larastan 9/10) → `composer test` (Pest on
PostgreSQL) → `pnpm test` (Vitest) → `pnpm build` (tsc). The orchestrator note:
do NOT run pest/migrate/build on the shared DB during generation; the architect
may run phpstan + tsc (read-only) only.

## 9. Risks / watch-list

- **Scope-bypass leak (CRITICAL):** the single biggest risk is someone adding
  `withoutGlobalScope(DemoScope::class)` to "see all students" on the admin
  dashboard — that would leak real counts into a demo dashboard. The
  `DemoAdminDashboardIsolationTest` is the guard; the controller MUST NOT bypass.
- **N+1 regression:** computing queue sizes with separate `->count()` calls would
  break the single-query guarantee. Derive every count from the one histogram.
  `AdminDashboardQueryCountTest` guards.
- **Prop drift:** snake_case + `status` as value string + the fixed 9-row
  breakdown are easy to get subtly wrong; the prop-contract tests lock them.
- **`route()` in props:** resolve queue/CTA URLs in PHP via `route(name)` so the
  pages never hardcode paths (keeps "no dead-end button"; one source of truth).
- **Exhaustiveness:** keep `RoleLandingRoute` a total `match` (PHPStan/level 9
  will flag a missing arm).
```
