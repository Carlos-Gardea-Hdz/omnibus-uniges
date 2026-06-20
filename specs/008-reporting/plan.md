# Plan 008 — Reporting domain (the HOW)

> **Phase:** Plan. Turns `specs/008-reporting/spec.md` into the concrete
> architecture / files-to-touch. Gated on `CLAUDE.md` §No-negociables.
> **Read first:** `spec.md` (this folder), `specs/007-dashboards/{spec,plan}.md`
> (the read-only-dashboard + grouped-count + demo-isolation precedent this slice
> extends), `specs/006-demo-mode/spec.md` (the DemoScope the aggregates ride),
> `specs/003-jury-domain/spec.md` (JuryAssignment/JuryRole shape).
> **Stack:** Laravel 12 + Inertia 2 + React 19 + TS + PostgreSQL 18 (Sail).
> **Slice nature:** READ-ONLY — four read-model **services** + four (+ hub) anemic
> controllers + five Inertia pages + `reports.*` locale keys + tests. **No
> migration, no Action, no input DTO, no domain mutation, no event.** Excel/PDF/Word
> export DEFERRED.

## 1. Recon — what already exists (verified, do NOT recreate)

- **`app/Domain/Reporting/`** — currently only `.gitkeep` in `Models/`, `Actions/`,
  `Data/`, `Enums/`. This slice is the first real code here. We ADD a `Services/`
  dir; **no `Models/`** (Reporting reads other domains' models), **no `Actions/`**
  (read-only), **no `Enums/`** (reuses `GraduationStatus`/`JuryRole`); `Data/` only
  if Open question B adopts view-DTOs (recommend NOT).
- **`GraduationStatus`** (`app/Domain/Graduation/Enums/GraduationStatus.php`) —
  `cases()`, `step()` (1-9), `labelKey()` (`status.<value>`), `color()`,
  `Graduated`/`isTerminal()`. Reuse for the cohort 9-key map + the `Graduated`
  filter + per-status label/color.
- **`JuryRole`** (`app/Domain/Jury/Enums/JuryRole.php`) — `President`/`Secretary`/
  `Vocal`/`Substitute`, `labelKey()` (`jury_role.<value>`). Reuse to label the four
  judge roles.
- **`Student`** (`app/Domain/Graduation/Models/Student.php`) — DemoScope-scoped;
  columns `status` (cast `GraduationStatus`), `enrollment_date` (cast `date`),
  `graduation_date` (cast `date`), `diploma_folio`, `record_book`, `record_sheet`,
  `control_number`, `program_id`, `graduation_type_id`, `first_name`/`last_name`;
  relations `program()`, `graduationType()`, `juryAssignment()`.
- **`JuryAssignment`** (`app/Domain/Jury/Models/JuryAssignment.php`) —
  DemoScope-scoped; `student_id`, `president_professor_id`, `secretary_professor_id`,
  `vocal_professor_id`, `substitute_professor_id` (nullable); relations `student()`,
  `president()`/`secretary()`/`vocal()`/`substitute()`.
- **`Program`** (`code`,`name`), **`GraduationType`** (`name`), **`Professor`**
  (`first_name`,`last_name`,`mother_last_name`) — **unscoped catalogs** (no
  DemoScope; shared reference). Safe to join for display names.
- **`Graduation\AdminDashboardController`** (`app/Http/Controllers/Graduation/`) —
  the canonical "ONE `selectRaw('status, count(*)') groupBy('status') pluck` →
  normalise over `GraduationStatus::cases()` in PHP → snake_case fixed-shape array"
  pattern, with the `int`-cast-per-bucket + `array_sum(array_map('intval', …))`
  discipline. The four services mirror this query model.
- **`Graduation/AdminDashboard.tsx`** — page chrome (`Head`, header,
  `LanguageSwitcher`, `DemoBanner`, `#main`, `<h1>`), the `TILE_ACCENT: Record<
  string,string>` color-token→class map, count tiles, quick-link `<Link>` cards.
  The report pages reuse this chrome + tile pattern.
- **Routes** (`routes/web.php`) — the staff group
  `Route::middleware(['auth','demo','role:admin,super_admin,secretary'])->group(...)`
  already holds `admin.dashboard` + the queue routes. ADD the five report routes to
  this same group (or a nested `prefix('admin/reports')->name('admin.reports.')`
  group inside it — see §4).
- **Factories:** `StudentFactory` (pipeline states + the graduated fixture
  `ceremonyPassed()->state(['status'=>Graduated,'graduation_date'=>now()->toDateString()])`
  used across slice-007 tests), `JuryAssignmentFactory` (4 distinct professors,
  `withoutSubstitute()`), `ProfessorFactory`, `ProgramFactory`, `UserFactory`
  (`admin`/`superAdmin`/`secretary`/`student`/`assistantSecretary`/`schoolServices`).
  Reuse — do NOT add states (factory-hygiene memory rule: no `unique()` on
  non-unique small pools).
- **Tests:** `tests/Feature/Dashboard/{AdminDashboardPagePropsTest,
  AdminDashboardQueryCountTest,DashboardRoleGatingTest,DemoAdminDashboardIsolationTest}.php`
  — the exact templates (the `demoAdmin()` / `demoStudents()` fixture helpers, the
  `DB::listen` query-count technique, the `->assertInertia(fn($page)=>…)` prop
  assertions, the role-gating matrix). `tests/Arch/ArchitectureTest.php` (extend for
  `App\Domain\Reporting`).
- **Locale files:** `resources/locales/{es,en}.json` (flat dotted; `status.<value>`,
  `jury_role.<value>`, `app.name` present, plus the `dashboard.admin.*` namespace
  from slice 007 to mirror). `lang/{es,en}.json` — **NOT touched** (no `__()`).
- **`generated.d.ts`** — already emits `GraduationStatus` + `JuryRole` (type-only
  import in the pages).

## 2. Files to touch

### Create — domain services (`app/Domain/Reporting/Services/`)

- `app/Domain/Reporting/Services/GraduatesReportService.php`
- `app/Domain/Reporting/Services/TerminalEfficiencyService.php`
- `app/Domain/Reporting/Services/CohortsReportService.php`
- `app/Domain/Reporting/Services/JudgeCertificatesService.php`

### Create — controllers (`app/Http/Controllers/Reporting/`)

- `app/Http/Controllers/Reporting/ReportingHubController.php`
- `app/Http/Controllers/Reporting/GraduatesReportController.php`
- `app/Http/Controllers/Reporting/TerminalEfficiencyReportController.php`
- `app/Http/Controllers/Reporting/CohortsReportController.php`
- `app/Http/Controllers/Reporting/JudgeCertificatesReportController.php`

### Create — Inertia pages (`resources/js/Pages/Reporting/`)

- `resources/js/Pages/Reporting/Index.tsx`
- `resources/js/Pages/Reporting/Graduates.tsx`
- `resources/js/Pages/Reporting/TerminalEfficiency.tsx`
- `resources/js/Pages/Reporting/Cohorts.tsx`
- `resources/js/Pages/Reporting/JudgeCertificates.tsx`

### Create — tests

- `tests/Feature/Reporting/GraduatesReportTest.php` (data correctness + filters)
- `tests/Feature/Reporting/TerminalEfficiencyReportTest.php` (int-rate correctness)
- `tests/Feature/Reporting/CohortsReportTest.php` (9-key pivot correctness)
- `tests/Feature/Reporting/JudgeCertificatesReportTest.php` (inversion + roles)
- `tests/Feature/Reporting/ReportingHubPagePropsTest.php` (hub prop contract)
- `tests/Feature/Reporting/ReportPagePropsTest.php` (the 4 report page prop contracts)
- `tests/Feature/Reporting/ReportQueryCountTest.php` (single-aggregate per report)
- `tests/Feature/Reporting/ReportRoleGatingTest.php` (guest→login; wrong-role→403)
- `tests/Feature/Reporting/DemoReportIsolationTest.php` (CRITICAL demo isolation)
- `resources/js/Pages/Reporting/__tests__/Graduates.test.tsx`
- `resources/js/Pages/Reporting/__tests__/TerminalEfficiency.test.tsx`
- `resources/js/Pages/Reporting/__tests__/Cohorts.test.tsx`
- `resources/js/Pages/Reporting/__tests__/JudgeCertificates.test.tsx`
- `resources/js/Pages/Reporting/__tests__/Index.test.tsx`

> (Page-props tests may be split per page if the gate prefers; one
> `ReportPagePropsTest.php` with a section per report is acceptable and mirrors how
> 007 grouped its dashboard assertions. Keep each prop-contract falsifiable.)

### Edit

- `routes/web.php` — import the five `Reporting\*` controllers; add the five routes
  to the staff group (nested `prefix('admin/reports')->name('admin.reports.')`).
- `resources/locales/es.json` + `resources/locales/en.json` — add the `reports.*`
  keys (spec §2.7) + optional `reports.nav.link`.
- `tests/Arch/ArchitectureTest.php` — add the `App\Domain\Reporting` rules (§7).
- **(Optional, Open question F)** `resources/js/Pages/Graduation/AdminDashboard.tsx`
  — add a single `<Link href={route('admin.reports.index')}>` "Reports" nav link in
  the header.

### Do NOT touch

- No migration. No `lang/{es,en}.json` (no PHP `__()`). No new model/Action/event/
  input-DTO. No `DemoScope`/`DemoContext`/middleware change. No `Student`/
  `JuryAssignment`/catalog model change. No `RoleLandingRoute` change (staff still
  land on `admin.dashboard`). Slice 007's pages stay as-is (except the optional nav
  link).

## 3. Service designs (read-only, one aggregate each)

> All services: `final`, in `App\Domain\Reporting\Services`, no constructor (or
> DI-only), each method `: \Illuminate\Support\Collection` (or `: array`) of plain
> snake_case row arrays. **Never** `withoutGlobalScopes()`. Build ints with
> `(int)` / `intval` guards. PHPStan L9: annotate every returned shape with
> `@return list<array{...}>` / `Collection<int, array{...}>`.

### 3.1 `GraduatesReportService`

```php
/** @return \Illuminate\Support\Collection<int, array{...}> */
public function graduates(?int $year, ?int $programId): Collection
{
    return Student::query()
        ->with(['program:id,name', 'graduationType:id,name'])
        ->where('status', GraduationStatus::Graduated->value)
        ->when($year !== null, fn ($q) => $q->whereYear('graduation_date', $year))
        ->when($programId !== null, fn ($q) => $q->where('program_id', $programId))
        ->orderByDesc('graduation_date')
        ->orderBy('control_number')
        ->get()
        ->map(fn (Student $s): array => [
            'id' => $s->id,
            'control_number' => $s->control_number,
            'full_name' => trim("{$s->first_name} {$s->last_name}"),
            'program_name' => $s->program->name,            // eager-loaded NOT NULL FK
            'graduation_type_name' => $s->graduationType->name,
            'diploma_folio' => $s->diploma_folio,
            'record_book' => $s->record_book,
            'record_sheet' => $s->record_sheet,
            'graduation_date' => $s->graduation_date?->toDateString(),
        ])->values();
}

/** @return list<int> distinct graduation years (desc) for the filter <select> */
public function availableYears(): array { /* one DISTINCT EXTRACT(YEAR…) query */ }
```

- `availableYears()` is **one** `selectRaw('distinct extract(year from graduation_date)
  as y')->whereNotNull('graduation_date')->orderByDesc('y')->pluck('y')` →
  `array_map('intval', …)`. (The program filter options come from the unscoped
  `Program` catalog in the controller — see §4.) Two scoped queries total for this
  report (the list + the year options); both are simple aggregates, no N+1.

### 3.2 `TerminalEfficiencyService`

```php
/** @return \Illuminate\Support\Collection<int, array{cohort_year:int,total:int,graduates:int,rate:int}> */
public function byCohort(): Collection
{
    $rows = Student::query()
        ->selectRaw("extract(year from enrollment_date)::int as cohort_year")
        ->selectRaw('count(*) as total')
        ->selectRaw("count(*) filter (where status = ?) as graduates", [GraduationStatus::Graduated->value])
        ->whereNotNull('enrollment_date')
        ->groupBy('cohort_year')
        ->orderByDesc('cohort_year')
        ->get();

    return $rows->map(fn ($r): array => $this->rateRow(
        ['cohort_year' => (int) $r->cohort_year], (int) $r->total, (int) $r->graduates,
    ));
}
```

- `byProgram()` — same `count(*) filter(...)` aggregate `groupBy('program_id')`,
  joined to `programs.name` (a `join('programs', …)->selectRaw('programs.name …')`
  or a `with`+pluck; prefer the join to keep ONE query), ordered by program name.
- `overall()` — `{total, graduates, rate}` across all scoped students: **one**
  `selectRaw('count(*) total, count(*) filter(where status=?) graduates')` query
  (no group by). (3 scoped queries total for this report — cohort, program, overall
  — each a single grouped/ungrouped aggregate; none is a loop.)
- **`rateRow()` private helper** — `['... , 'total'=>$t, 'graduates'=>$g, 'rate'=>
  $t === 0 ? 0 : (int) round($g / $t * 100)]`. The **int-percent** SSOT (D-RATE-INT).
- **PHPStan / Postgres mixed:** cast `(int) $r->cohort_year` / `(int) $r->total` /
  `(int) $r->graduates` explicitly — never `(int)` on a raw mixed expression sum.
  The `filter (where status = ?)` bindings are parameterised (safe; no user input —
  the value is the enum constant).

### 3.3 `CohortsReportService`

```php
/** @return \Illuminate\Support\Collection<int, array{cohort_year:int,total:int,graduated:int,in_progress:int,by_status:list<array{...}>}> */
public function overview(): Collection
{
    // ONE grouped aggregate: year × status.
    $raw = Student::query()
        ->selectRaw("extract(year from enrollment_date)::int as cohort_year, status, count(*) as total")
        ->whereNotNull('enrollment_date')
        ->groupBy('cohort_year', 'status')
        ->get();                                  // rows: {cohort_year, status, total}

    // Pivot in PHP into per-cohort 9-key maps (slice-007 normalise technique, 2D).
    return $raw->groupBy('cohort_year')
        ->map(fn ($cohortRows, $year): array => $this->cohortRow((int) $year, $cohortRows))
        ->sortKeysDesc()
        ->values();
}
```

- `cohortRow(int $year, $rows)` — build a `[status_value => count]` lookup from
  `$rows`, then map `GraduationStatus::cases()` into the fixed 9-row `by_status`
  (`status`/`step`/`label_key`/`color`/`count`, missing → 0); `total` =
  `array_sum(array_map('intval', $lookup))`, `graduated` =
  `(int) ($lookup[Graduated->value] ?? 0)`, `in_progress` = `total - graduated`.
- **One** scoped query; the pivot is pure PHP. No per-cohort/per-status query.

### 3.4 `JudgeCertificatesService`

```php
/** @return \Illuminate\Support\Collection<int, array{professor_id:int,professor_name:string,assignment_count:int,assignments:list<array{...}>}> */
public function byProfessor(?int $professorId): Collection
{
    // ONE query for the juries + eager loads (scoped JuryAssignment).
    $assignments = JuryAssignment::query()
        ->with([
            'student:id,control_number,first_name,last_name,status,ceremony_date,graduation_date,program_id',
            'student.program:id,name',
            'president:id,first_name,last_name,mother_last_name',
            'secretary:id,first_name,last_name,mother_last_name',
            'vocal:id,first_name,last_name,mother_last_name',
            'substitute:id,first_name,last_name,mother_last_name',
        ])
        ->get();

    // Invert the four role columns → per-professor rows IN PHP (D-INVERT).
    // For each assignment, emit up to 4 (professor, role) tuples (substitute may be null),
    // group by professor id, optional ?professor_id filter, build the row shape.
}
```

- The inversion: for each `JuryAssignment`, iterate the four `(relation, JuryRole)`
  pairs `[[president, President],[secretary, Secretary],[vocal, Vocal],
  [substitute, Substitute]]`; skip a null substitute; key the resulting tuples by
  `professor->id`; filter to `$professorId` if given; for each professor build
  `professor_name` (`trim(first last [mother])`), `assignment_count`, and the
  `assignments[]` (each with `jury_assignment_id`, `role` value, `role_label_key`,
  the student fields, `program_name` from `student.program->name`,
  `student_status` = `$student->status->value`, ISO dates). Sort professors by name.
- **One** scoped query (+ eager loads). The professor filter narrows the PHP
  grouping (or add `->where(fn ($q) => $q->where('president_professor_id',$id)->or…)`
  to push the filter to SQL — but the PHP filter keeps it one query and is simpler;
  the data volume is small, D-INVERT).
- **`filter_options.professors`** (professors who served on ≥1 jury) is derived in
  PHP from the same `$assignments` result (the distinct professors across all four
  columns) — **no extra query**.

## 4. Controller designs (anemic, ≤15 lines each)

> All: `final extends Controller`, `App\Http\Controllers\Reporting`, method
> `index(...)` returning `Inertia\Response`, inject the service via the signature,
> shape props via the service result + `route()` URL resolution. NO Eloquent write,
> NO Action, NO input DTO.

### 4.1 `ReportingHubController`

```php
public function index(): Response
{
    return Inertia::render('Reporting/Index', [
        'reports' => [
            ['key' => 'graduates', 'title_key' => 'reports.graduates.title', 'description_key' => 'reports.graduates.description', 'route' => route('admin.reports.graduates')],
            ['key' => 'terminal_efficiency', 'title_key' => 'reports.terminal_efficiency.title', 'description_key' => 'reports.terminal_efficiency.description', 'route' => route('admin.reports.terminal-efficiency')],
            ['key' => 'cohorts', 'title_key' => 'reports.cohorts.title', 'description_key' => 'reports.cohorts.description', 'route' => route('admin.reports.cohorts')],
            ['key' => 'judge_certificates', 'title_key' => 'reports.judge_certificates.title', 'description_key' => 'reports.judge_certificates.description', 'route' => route('admin.reports.judge-certificates')],
        ],
    ]);
}
```

### 4.2 `GraduatesReportController`

```php
public function index(Request $request, GraduatesReportService $service): Response
{
    $year = $request->filled('year') ? $request->integer('year') : null;
    $programId = $request->filled('program_id') ? $request->integer('program_id') : null;
    $rows = $service->graduates($year, $programId);

    return Inertia::render('Reporting/Graduates', [
        'graduates' => $rows->all(),
        'filters' => ['year' => $year, 'program_id' => $programId],
        'filter_options' => [
            'years' => $service->availableYears(),
            'programs' => Program::query()->orderBy('name')->get(['id', 'name'])->all(), // unscoped catalog
        ],
        'total' => $rows->count(),
    ]);
}
```

- `$request->integer()` coerces a garbage `?year=abc` to `0` → `filled` is true but
  the year matches nothing (empty result, 200) — D-FILTER (read-only, no validation,
  never 422). (Keep ≤15 lines: the `programs` catalog read is the only non-service
  query, a trivial catalog list; acceptable in an anemic read-only controller, same
  as the queue controllers reading their own list.)

### 4.3 `TerminalEfficiencyReportController`

```php
public function index(TerminalEfficiencyService $service): Response
{
    return Inertia::render('Reporting/TerminalEfficiency', [
        'by_cohort' => $service->byCohort()->all(),
        'by_program' => $service->byProgram()->all(),
        'overall' => $service->overall(),
    ]);
}
```

### 4.4 `CohortsReportController`

```php
public function index(CohortsReportService $service): Response
{
    return Inertia::render('Reporting/Cohorts', ['cohorts' => $service->overview()->all()]);
}
```

### 4.5 `JudgeCertificatesReportController`

```php
public function index(Request $request, JudgeCertificatesService $service): Response
{
    $professorId = $request->filled('professor_id') ? $request->integer('professor_id') : null;
    $rows = $service->byProfessor($professorId);

    return Inertia::render('Reporting/JudgeCertificates', [
        'professors' => $rows->map(fn ($p) => Arr::except($p, ['_options']))->all(), // or shape directly
        'filters' => ['professor_id' => $professorId],
        'filter_options' => ['professors' => $service->professorOptions()], // distinct from the same result
    ]);
}
```

- `professorOptions()` may be a second tiny method on the service that reuses the
  same `byProfessor(null)` result's distinct professors, OR the controller derives
  it from the unfiltered call. Keep it **one** jury query — pass the unfiltered set
  once and filter/derive in PHP. (Implementation detail for the tasks phase; the
  contract is: one `JuryAssignment` query for this report.)

### Routes (`routes/web.php`) — nested in the existing staff group

```php
Route::middleware(['auth', 'demo', 'role:admin,super_admin,secretary'])->group(function (): void {
    // … existing admin.dashboard + queue routes …

    Route::prefix('admin/reports')->name('admin.reports.')->group(function (): void {
        Route::get('/', [ReportingHubController::class, 'index'])->name('index');
        Route::get('/graduates', [GraduatesReportController::class, 'index'])->name('graduates');
        Route::get('/terminal-efficiency', [TerminalEfficiencyReportController::class, 'index'])->name('terminal-efficiency');
        Route::get('/cohorts', [CohortsReportController::class, 'index'])->name('cohorts');
        Route::get('/judge-certificates', [JudgeCertificatesReportController::class, 'index'])->name('judge-certificates');
    });
});
```

## 5. Frontend designs

> All pages mirror `Graduation/AdminDashboard.tsx` chrome: `Head`, header with
> `app.name` + the page title + `LanguageSwitcher`, `<DemoBanner />`, one
> `<main id="main">`, one `<h1>`. Theme via tokens (`bg-surface text-fg`), locale
> via `useLocale().t`. Type-only `import type { GraduationStatus, JuryRole }`. WCAG
> 2.2 AA: semantic `<table>`/`<dl>`/`<ol>`, text labels on counts/rates, `<Link>`s.

- **`Index.tsx`** — props `{ reports }`; a responsive grid of 4 cards, each a
  `<Link href={r.route}>` with `t(r.title_key)` + `t(r.description_key)`.
- **`Graduates.tsx`** — props per §2.4; a filter bar (year `<select>` from
  `filter_options.years`, program `<select>` from `filter_options.programs`, a
  "clear" `<Link>`), each change `router.get(route('admin.reports.graduates'),
  {year, program_id}, {preserveState:true})`; a `<table>` of the graduate rows; a
  `t('reports.graduates.count', {n: total})` line; an empty state when `total===0`.
- **`TerminalEfficiency.tsx`** — props per §2.4; `overall.rate` as a headline stat
  card (`{rate}%`); a by-cohort `<table>` and a by-program `<table>`, each row
  showing `graduates`/`total` and `rate%` (optionally a thin progress bar whose
  width is `rate%` — color is a redundant cue, the `N%` text is always present).
- **`Cohorts.tsx`** — props per §2.4; per cohort a section with the `cohort_year`
  heading + the `total`/`graduated`/`in_progress` `<dl>` + the 9-state tile grid
  reusing the dashboard `TILE_ACCENT[color]` map, `step` order, `t(label_key)`.
- **`JudgeCertificates.tsx`** — props per §2.4; a professor `<select>` filter
  (driving `router.get`); per professor a section (name + `assignment_count`) with
  a `<table>` of their assignments (`t(role_label_key)`, student control number +
  name, program, `t('status.'+student_status)`, ISO dates rendered locale-aware). A
  static `t('reports.judge_certificates.export_deferred')` note where the print
  button will later live (NO working button this slice).
- **(Optional) `AdminDashboard.tsx`** — one `<Link href={route('admin.reports.index')}>`
  `t('reports.nav.link')` in the header (Open question F).

## 6. i18n keys to add

Add to **both** `resources/locales/es.json` + `resources/locales/en.json` (flat
dotted, ES + EN values) every `reports.*` key in spec §2.7 (+ optional
`reports.nav.link`). The `status.<value>` (9) + `jury_role.<value>` (4) + `app.name`
keys are already present (reused). **No** `lang/{es,en}.json` change (controllers
flash nothing) → **no** PHP lang-key resolution test (memory rule: read-only,
flash-nothing controllers need no `lang/` keys). The `reports.*` keys are exercised
by the Vitest page tests via `useLocale`.

## 7. Tests (build exactly the spec §3 + §2 list)

- **Feature (PostgreSQL 18, `uses(RefreshDatabase::class)`, `use function
  Pest\Laravel\actingAs`):**
  - **`GraduatesReportTest`** — seed a known graduate set (e.g. 3 graduated in 2025
    program A, 2 graduated in 2024 program B, plus non-graduated noise); acting as
    admin, `GET admin.reports.graduates` lists **only** the 5 graduates with the
    exact row shape; `?year=2025` → only the 3; `?program_id=<B>` → only B's;
    `?year=abc` → 200, unfiltered/empty (D-FILTER, never 422/500); a non-graduated
    student never appears.
  - **`TerminalEfficiencyReportTest`** — seed 10 students enrolled 2019, 4
    graduated → `by_cohort` has `{cohort_year:2019,total:10,graduates:4,rate:40}`
    with `rate` a strict PHP **int** (`expect($rate)->toBeInt()->toBe(40)`); a
    second cohort + a 0-graduate cohort (`rate:0`); `by_program` mirrors per
    program; `overall.rate` = int percent across all. Assert no float, no string.
  - **`CohortsReportTest`** — seed two enrollment-year cohorts with a status spread;
    each `cohorts[]` row has `total`/`graduated`/`in_progress` correct and a
    `by_status` of **exactly 9** rows in `step` order (empty states 0) with the
    right `count`/`label_key`/`color`.
  - **`JudgeCertificatesReportTest`** — seed e.g. 2 juries (8 distinct professors,
    or some shared across juries so a professor shows multiple assignments); assert
    each professor appears once with the right `assignment_count` and one
    `assignments[]` entry per jury they sat, each with the correct `role` +
    `role_label_key` + student fields + program + dates; `?professor_id=X` narrows
    to X; a `withoutSubstitute()` jury yields 3 (not 4) tuples for that jury.
  - **`ReportingHubPagePropsTest`** — `GET admin.reports.index` → `Reporting/Index`,
    `reports` has exactly 4 rows with the fixed keys + resolved routes.
  - **`ReportPagePropsTest`** — the 4 report page prop contracts (component name +
    exact snake_case shape; `status`/`role` as value strings; the 9-row cohort
    breakdown; int rates). One section per report (or split per file).
  - **`ReportQueryCountTest`** — the slice-007 `DB::listen` technique, per report:
    assert the report's data comes from the expected single aggregate/list query
    (e.g. graduates → one list query over `students` + the years query; efficiency →
    the cohort + program + overall aggregates each once; cohorts → one
    `group by … status` over `students`; judge → one query over `jury_assignments`)
    — **no** per-group/per-status/per-professor loop, no N+1 (eager loads counted,
    not flagged as N+1). Falsifiable.
  - **`ReportRoleGatingTest`** — for each of the 5 routes: guest → redirect `login`;
    `student` → 403; `assistant_secretary` → 403; `school_services` → 403; `admin`
    / `super_admin` / `secretary` → 200. (Mirror `DashboardRoleGatingTest`.)
  - **`DemoReportIsolationTest` (CRITICAL)** — reuse the `demoAdmin()` /
    `demoStudents()` fixture shape from `DemoAdminDashboardIsolationTest`. Provision
    a demo staff session A with N demo students/juries at assorted statuses,
    alongside real students/juries AND another demo session B's rows. Acting as A
    `->withSession(A demo keys)`, GET each report: every count/rate/list reflects
    **only A's** rows — never real, never B's. Symmetric: a real admin's reports
    exclude every demo row. Falsifiable guard: `Student::withoutGlobalScopes()
    ->count()` (and `JuryAssignment::withoutGlobalScopes()->count()`) exceed the
    demo report's totals (rows physically exist; the scope, not deletion, hides
    them); assert the services never call `withoutGlobalScopes`.
- **Arch (`tests/Arch/ArchitectureTest.php`)** — add, mirroring the Jury/Ceremony
  block (spec Open question J):
  - `arch('cross-domain isolation: Reporting does not import Identity')
    ->expect('App\Domain\Reporting')->not->toUse('App\Domain\Identity');`
  - `arch('reporting services are final')->expect('App\Domain\Reporting\Services')
    ->classes()->toBeFinal();`
  - (the broad `App\Domain` final + strict-types rules already cover the rest; the
    broad `controllers never touch Eloquent directly` rule — the existing
    `Graduation\AdminDashboardController` already queries `Student::query()` and
    passes, so the read-only report controllers querying `Program`/the services are
    fine; the **`GraduatesReportController` reads `Program` directly** — confirm it
    does not trip `not->toUse('Illuminate\Database\Eloquent\Model')`: it uses the
    `Program` model, a subclass, exactly as the dashboard uses `Student`. If the arch
    rule flags subclasses, move the program-catalog read into a tiny service method;
    **decide in tasks** by running the arch suite. Recommended: add a
    `GraduatesReportService::programOptions()` so the controller touches no model
    directly, keeping it symmetric with the other three controllers.)
  - **Do NOT** adopt a literal "Reporting only uses Shared" rule (Reporting reads
    Graduation/Jury/Academic models — that is the point).
- **Vitest** — the 5 page tests; mock `@inertiajs/react` (`Link`, `usePage`
  returning `{ props: { demo: null } }`, `router.get`) per the slice-007
  `DemoChooser.test.tsx`/`AdminDashboard.test.tsx` pattern; wrap in
  `ThemeProvider`+`LocaleProvider`; assert each page renders its rows/tiles/filters,
  rates as `N%`, the empty states, and (judge) the export-deferred note + no working
  export button.

> **Arch note resolved (recommended):** to keep every report controller symmetric
> and avoid any controller-touches-Model arch ambiguity, push the **program catalog
> read** and the **professor options** into service methods
> (`GraduatesReportService::programOptions()`, `JudgeCertificatesService::
> professorOptions()`), so all five controllers touch **only services + `route()` +
> `Inertia::render`** — the cleanest anemic shape. (Tasks phase confirms against the
> live arch suite.)

## 8. Quality gates (per CLAUDE.md; run by the implement/verify phase, not here)

`composer format` → `composer analyse` (Larastan 9/10) → `composer test` (Pest on
PostgreSQL: arch + the new Feature/contract/aggregate/isolation tests) → `pnpm test`
(Vitest) → `pnpm build` (tsc). **Orchestrator note:** do NOT run pest/migrate/build
on the shared DB during generation; the architect may run phpstan + tsc (read-only)
only.

## 9. Risks / watch-list

- **Scope-bypass leak (CRITICAL):** the single biggest risk is a service calling
  `withoutGlobalScopes()` to "see all data" in a report — that leaks real
  students/graduates into a demo coordinator's report (and vice versa).
  `DemoReportIsolationTest` is the guard; the services MUST NOT bypass.
- **N+1 regression:** computing cohort/program/professor breakdowns with per-group
  loops (or lazy-loading `student.program` / the four professors per row) breaks the
  single-aggregate guarantee. Use `count(*) filter(...)` + the PHP pivot + eager
  loads. `ReportQueryCountTest` guards.
- **Float/rate drift:** a `rate` that ships as a float (`40.0`) or a pre-formatted
  string (`"40%"`) breaks the contract. Cast to int via `round` + `(int)`; assert
  `is_int`. Postgres `count(*) filter` returns mixed via the magic accessor — cast
  each bucket with `(int)`, never `(int)` on a raw mixed sum (PHPStan L9).
- **Year-axis confusion:** graduates filter = graduation year; cohort/efficiency
  grouping = enrollment year (D-YEAR). Mixing them silently mis-buckets; the tests
  pin each axis with deterministic enrollment vs graduation dates.
- **Prop drift:** snake_case + `status`/`role` as value strings + the fixed 9-row
  cohort breakdown + int rates are easy to get subtly wrong; the prop-contract tests
  lock them.
- **Controller-touches-Model arch rule:** keep all five controllers touching only
  services (push the program/professor catalog reads into service methods) so none
  trips the arch rule and all stay anemic (§7 note).
- **`route()` in props:** resolve report/hub URLs in PHP via `route(name)` so the
  pages never hardcode paths (no dead-end link; one source of truth).
- **Export-deferral honesty:** ship NO working export/download button this slice;
  the services return exactly the export-ready rows so the later export slice adds
  only a generator + a download route (no re-query).
