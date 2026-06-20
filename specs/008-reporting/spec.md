# Spec 008 — Reporting domain (staff-facing, READ-ONLY analytics over the pipeline)

> **Phase:** Specify (the WHAT and WHY, not the HOW).
> **SSOT:** `SPEC.md` §3.7 Reporting Domain — REPORT-01 *terminal efficiency*
> (Graduated / Total ratio), REPORT-02 *graduates report* (all graduated students
> with full data), REPORT-03 *cohort report* (enrollment-date grouping), CERT-01
> *judge certificates* (Word `.docx` per professor, the data behind them); §5.1 the
> `Reporting/` domain box ("Report generation actions + services"); §7.3 admin
> routes — `Admin\ReportController` ("reports"); §RBAC (the report screens are
> staff-only). The pipeline data — `Student` (`status`, `enrollment_date`,
> `graduation_date`, `diploma_folio`, `record_book`, `record_sheet`, `program_id`,
> `graduation_type_id`, `control_number`), `GraduationStatus` (9 states, `Graduated`
> terminal, `step()` 1-9), `JuryAssignment` (the four professor roles via the
> `JuryRole` enum), `Professor`/`Program`/`GraduationType` (Academic catalogs).
> **Builds on (reuse VERBATIM — do NOT duplicate):**
> - The **dashboards slice (007)** — the **`Graduation\AdminDashboardController`**
>   grouped-count query model (ONE `select('status', count(*)) group by status`
>   aggregate, normalised in PHP to a fixed-shape array, NEVER a per-state loop),
>   the **read-only-controller pattern** (anemic ≤15 lines, snake_case props shaped
>   in private helpers, NO Action / NO DTO / NO migration / NO mutation), the
>   `Graduation/AdminDashboard.tsx` page chrome (header + `LanguageSwitcher` +
>   `<DemoBanner />` + `#main` + one `<h1>` + count-tile/quick-link components), the
>   `dashboard.admin.*` locale namespace, and the **five Pest test shapes**
>   (prop-contract, query-count single-aggregate, role gating, demo isolation).
> - The **demo slice (006)** — the symmetric **`DemoScope`** global scope on
>   `Student` / `StudentDocument` / `JuryAssignment` (so every report aggregate is
>   automatically correct per-mode: a demo staff session counts ONLY its own demo
>   rows, a real session ONLY real rows), `DemoContext`, the `demo` middleware, the
>   shared `demo` Inertia prop + `DemoBanner.tsx`. **`Program` / `GraduationType` /
>   `Professor` are catalogs and are NOT demo-scoped** — they are shared baseline
>   reference data, joined into reports for display only.
> - The **auth slice (005)** — `EnsureRole`, the `['auth','demo','role:…']` route
>   groups, `UserFactory` role states.
> - The **graduation/jury/ceremony slices (001-004)** — `Student` +
>   `GraduationStatus`, `JuryAssignment` + `JuryRole`, the `StudentFactory`
>   pipeline-stage states (`formBReview`/…/`ceremonyPassed`) and the graduated
>   fixture pattern (`ceremonyPassed()->state(['status' => Graduated,
>   'graduation_date' => …])` used across the slice-007 tests), the
>   `JuryAssignmentFactory`, `ProfessorFactory`, `LocaleContext`/`useLocale`,
>   `LanguageSwitcher`.
>
> **This slice mirrors slices 006/007 conventions VERBATIM:** controllers ≤15 lines,
> anemic, `final extends Controller`, snake_case props shaped with `->only()` /
> `->through()` / explicit array; named routes / no closures; a Pest Inertia
> prop-contract test per page; aggregate-correctness tests against a seeded cohort;
> role-gating + demo-isolation tests; arch tests; Pest on PostgreSQL 18.
>
> **READ-ONLY slice: no migration, no mutating Action, no state transition, no
> `Spatie\LaravelData` input DTO** (nothing is validated/submitted/mutated — these
> are GET-only reports). Small **read-model query services** in
> `app/Domain/Reporting/Services/` (stateless, read-only — SPEC §5.6 "if it reads
> without writing → Service") and **`#[TypeScript]` view-DTOs** in
> `app/Domain/Reporting/Data/` (output row shapes, optional — see Open question B)
> are the only new domain code. **Excel / PDF / Word EXPORT
> (`maatwebsite/excel` + `barryvdh/laravel-dompdf` / `phpoffice/phpword`) is
> explicitly DEFERRED to a later slice** (SPEC §3.7 says "Excel export" / "Word
> document per professor"; this slice ships the on-screen reports + the exact data
> a future export will serialise — see §2.7).
>
> **Status:** DRAFT — awaiting human review gate before `/sdd-plan`.

## 1. Problem & why

Slices 001-004 produced the data a registrar actually reports on: graduated
students with diploma folios, cohorts spread across the 9 states, juries seated
with four professors each. Slice 007 gave staff a *live operational* home (the
admin dashboard: how many students sit in each state right now, quick links into
the work queues). **What is missing is the *analytical / archival* layer** SPEC
§3.7 calls for — the read-only reports a coordinator runs to answer institutional
questions, none of which exist yet:

- **Who graduated** (REPORT-02) — the registrar's roster of `Graduated` students
  with the full record (`control_number`, name, program, graduation type, diploma
  folio, record book + sheet, graduation date), filterable by year / program.
- **How efficient is each cohort/program** (REPORT-01) — *terminal efficiency*:
  graduates ÷ total students in a cohort (enrollment year) and per program, as a
  **percentage**. The institution's headline KPI.
- **How is each cohort progressing** (REPORT-03) — students grouped by enrollment
  year, each cohort split by current `GraduationStatus` (in-progress vs graduated),
  so staff see where each generation stands.
- **Which juries a professor served on** (CERT-01) — per professor, the jury
  assignments they sat (which student, which `JuryRole`, the ceremony/graduation
  context) — the exact data a printable judge certificate draws from. (The PDF
  itself is deferred; this slice ships the data + the screen.)

These are **read-only aggregates over existing tables** — no schema change, no
mutation. Because the aggregates run through the **DemoScope-scoped** `Student` /
`JuryAssignment` models, **every report is automatically correct per-mode** with
**zero extra code**: a demo coordinator's reports count ONLY their demo sandbox; a
real coordinator's count ONLY real data. We must **NOT** `withoutGlobalScopes()`
anywhere (it would leak real students/graduates into a demo report and vice-versa
— the same load-bearing isolation guarantee slice 007 proved for the dashboard).

This slice is the **Reporting vertical**, and it is the first build inside
`app/Domain/Reporting/` (currently only `.gitkeep` scaffolds).

## 2. Scope

### 2.1 In scope — backend (READ-ONLY)

**Four read-model query services** in `app/Domain/Reporting/Services/` (`final`,
stateless, read-only; constructor-less or DI-only; each exposes a typed method
returning a normalised array / collection of plain row arrays — NEVER a mutation,
NEVER an event, NEVER `DB::transaction`). Each runs **one efficient aggregate** (no
N+1) over the **DemoScope-scoped** `Student` / `JuryAssignment` model:

1. **`GraduatesReportService`** — `graduates(?int $year, ?int $programId): Collection`
   returns the list of `Graduated` students with their full record, joined to
   `program` + `graduationType` for display names. **One query** with eager-loaded
   `program:id,name` + `graduationType:id,name` (no N+1), filtered by
   `where('status', Graduated)` plus optional `whereYear('graduation_date', $year)`
   and `where('program_id', $programId)`, ordered by `graduation_date` then
   `control_number`. Returns **plain snake_case row arrays** (§2.4 graduates shape).
2. **`TerminalEfficiencyService`** — `byCohort(): Collection` and
   `byProgram(): Collection`. Each computes, per group, `{graduates, total, rate}`
   where `total` = all students in that group (any status) and `graduates` = those
   in `Graduated`. **`rate` is an integer percentage** (0-100), computed as
   `(int) round($graduates / $total * 100)` with a `total === 0 → rate = 0` guard
   (NO float in the prop; the rate is a deliberate **rounded-int percent** — see
   §2.6 int/float). The cohort grouping is by **enrollment year**
   (`EXTRACT(YEAR FROM enrollment_date)`); the program grouping is by `program_id`
   (joined to the program name). Computed with a **single grouped aggregate per
   axis** — `select(year/program, count(*) total, count(*) filter (where status =
   'graduated') graduates) group by …` (Postgres `COUNT(*) FILTER (WHERE …)` keeps
   it ONE query per axis, no per-group loop) — then normalised in PHP to the int
   rate. (`mixed`-from-Postgres rule: build int totals with `(int)`/`is_numeric`
   guards, NEVER raw `(int)` on a mixed sum — memory rule.)
3. **`CohortsReportService`** — `overview(): Collection` returns one row per
   enrollment-year cohort with **per-status counts** (a fixed 9-key map, missing
   states → 0) plus `total`, `graduated`, `in_progress` (= total − graduated).
   Computed with **ONE grouped aggregate**: `select(year, status, count(*)) group
   by year, status`, then pivoted in PHP into per-cohort rows (the slice-007
   "normalise in PHP to a complete fixed shape" technique, extended to two
   dimensions). No per-cohort or per-status loop query.
4. **`JudgeCertificatesService`** — `byProfessor(?int $professorId): Collection`
   returns, per professor who has served on at least one jury, the list of jury
   assignments they sat: each entry `{student, role, ceremony/graduation context}`.
   Reads **`JuryAssignment`** (DemoScope-scoped) eager-loaded with
   `student:id,control_number,first_name,last_name,status,ceremony_date,graduation_date,program_id`
   + `student.program:id,name` + the four professor relations, then **inverts** the
   four role columns into per-professor → per-assignment rows in PHP, deriving the
   `JuryRole` for each (president/secretary/vocal/substitute). Optional
   `$professorId` filter narrows to one professor. **One query** for the
   assignments (plus the eager loads); the inversion is pure PHP. (See §2.4 judge
   shape; §2.8 D-INVERT for why the inversion is PHP, not four UNION queries.)

> **Why services, not Actions:** SPEC §5.6 — "if it **reads without writing** →
> Service". These never mutate; an Action (which implies `DB::transaction` + a state
> change + an event) would be wrong here. The slice-007 dashboard inlined its single
> aggregate in the controller; this slice has **four** distinct multi-line
> aggregates, so they are extracted into named, independently testable services to
> keep each controller anemic (≤15 lines) — the same shaping the queue controllers
> use via private helpers, promoted to a service because the query logic is
> substantial and reused by the (future) export. (Open question A.)

**Four anemic controllers** (one per report) under
`App\Http\Controllers\Reporting\` (a new HTTP namespace — see §2.8 D-NS), each
`final extends Controller`, each method ≤15 lines, render-only, snake_case props,
NO Eloquent write, NO Action, NO DTO:

5. **`Reporting\GraduatesReportController@index`** — reads the optional `year` +
   `program_id` query filters from the request (plain `$request->integer('year')`
   etc. — these are **display filters on a read-only GET, not validated input**, so
   no DTO; an invalid value simply yields an empty/unfiltered result — see §2.8
   D-FILTER), calls `GraduatesReportService::graduates(...)`, and renders
   `Reporting/Graduates` with the rows + the available filter options (distinct
   graduation years + the program catalog) + the currently-applied filters.
6. **`Reporting\TerminalEfficiencyReportController@index`** — calls the
   `TerminalEfficiencyService` and renders `Reporting/TerminalEfficiency` with
   `by_cohort` + `by_program` rows + the overall rate.
7. **`Reporting\CohortsReportController@index`** — calls `CohortsReportService`
   and renders `Reporting/Cohorts` with the per-cohort rows.
8. **`Reporting\JudgeCertificatesReportController@index`** — reads the optional
   `professor_id` filter, calls `JudgeCertificatesService`, and renders
   `Reporting/JudgeCertificates` with the per-professor assignment rows + the
   professor filter options.
9. **`Reporting\ReportingHubController@index`** — the **reporting index/hub**:
   renders `Reporting/Index` with four cards linking (resolved `route()` URLs) to
   the four reports. No data query (or a tiny "headline" count reusing the same
   scoped totals — keep it query-free or one trivial scoped count; recommend
   query-free links only — see Open question E).

**Routes** (`->name()`, no closures, all in **one** `['auth','demo',
'role:admin,super_admin,secretary']` group mirroring the existing staff group):

| method | path | name | controller |
|--------|------|------|------------|
| GET | `/admin/reports` | `admin.reports.index` | `ReportingHubController@index` |
| GET | `/admin/reports/graduates` | `admin.reports.graduates` | `GraduatesReportController@index` |
| GET | `/admin/reports/terminal-efficiency` | `admin.reports.terminal-efficiency` | `TerminalEfficiencyReportController@index` |
| GET | `/admin/reports/cohorts` | `admin.reports.cohorts` | `CohortsReportController@index` |
| GET | `/admin/reports/judge-certificates` | `admin.reports.judge-certificates` | `JudgeCertificatesReportController@index` |

> **Role gate:** `admin,super_admin,secretary` — identical to the slice-007 admin
> dashboard group and SPEC RBAC (reports are staff analytics; `student` and the two
> non-reviewing staff roles `assistant_secretary` / `school_services` get **403**,
> exactly as on `admin.dashboard`). The reports do **not** add a landing re-point —
> staff continue to land on `admin.dashboard`; the hub is reached via a nav link
> (Open question F: a "Reports" link on the admin dashboard is RECOMMENDED but
> minimal — see §2.4).

### 2.2 The four reports — exact aggregate strategy (SSOT for the counts)

| report | source model (scoped) | grouping | the ONE aggregate | rate/total type |
|--------|----------------------|----------|-------------------|-----------------|
| Graduates | `Student` | none (filtered list) | `where(status=graduated)` + optional year/program, eager `program`+`graduationType`, ordered | counts are ints; no rate |
| Terminal efficiency (cohort) | `Student` | enrollment **year** | `select(year, count(*) total, count(*) filter(status=graduated) grads) group by year` | `rate` = **int percent** 0-100 (`round($g/$t*100)`), `t==0→0` |
| Terminal efficiency (program) | `Student` | `program_id` (+ name join) | same FILTER aggregate `group by program_id` | same int percent |
| Cohorts | `Student` | enrollment **year** × `status` | `select(year, status, count(*)) group by year, status`, pivot in PHP to 9-key map per cohort | counts ints; `in_progress = total − graduated` |
| Judge certificates | `JuryAssignment` | per professor (PHP inversion of the 4 role cols) | one `JuryAssignment` query, eager `student`(+`program`) + 4 professors; invert in PHP | counts ints; no rate |

> **No N+1 anywhere.** Each report is **one** aggregate/list query (plus eager
> loads). The cohorts pivot and the judge inversion are pure-PHP transforms of a
> single result set — the slice-007 "normalise in PHP" technique. Falsifiable by a
> query-count test per report (§2.6).
>
> **DemoScope:** `Student` and `JuryAssignment` carry the global scope, so every
> aggregate is auto-confined to the acting session's mode. `Program` /
> `GraduationType` / `Professor` are unscoped catalogs (shared reference data) and
> are safe to join for display names. **NEVER `withoutGlobalScopes()`.**

### 2.3 Reuse — what already exists (do NOT recreate)

- **`GraduationStatus`** — 9 cases, `step()`, `labelKey()` (`status.<value>`),
  `color()`, `isTerminal()`, `cases()`. Reuse for the cohort 9-key map, the
  status labels/colors, and the `Graduated` filter.
- **`JuryRole`** — `President`/`Secretary`/`Vocal`/`Substitute`, `labelKey()`
  (`jury_role.<value>`), `color()`. Reuse to label the four roles in the judge
  report.
- **`Student`** + **`JuryAssignment`** models (DemoScope-scoped, all needed columns
  + relations present — `program()`, `graduationType()`, `juryAssignment()`,
  `student()`, `president()`/`secretary()`/`vocal()`/`substitute()`).
- **`Program`** (`code`, `name`), **`GraduationType`** (`name`), **`Professor`**
  (`first_name`, `last_name`, `mother_last_name`).
- **`AdminDashboardController`** — the canonical "one grouped-count → normalise in
  PHP → snake_case fixed-shape array" pattern + its private-helper shaping. The four
  services mirror its query discipline.
- **`Graduation/AdminDashboard.tsx`** — page chrome (`Head`, header,
  `LanguageSwitcher`, `DemoBanner`, `#main`, `<h1>`, `TILE_ACCENT` color-token map,
  count tiles, quick-link cards). The report pages reuse this chrome + tile pattern.
- **`StudentFactory`** states (`formBReview`/`documentsStage`/`paymentVerified`/
  `juryAssigned`/`ceremonyScheduled`/`ceremonyPassed`) + the graduated fixture
  (`ceremonyPassed()->state(['status'=>Graduated,'graduation_date'=>…])`),
  **`JuryAssignmentFactory`**, **`ProfessorFactory`**, **`ProgramFactory`**,
  **`UserFactory`** role states + the demo-session helper shape from
  `DemoAdminDashboardIsolationTest` (`demoAdmin()` / `demoStudents()`).
- **Locale files** `resources/locales/{es,en}.json` (flat dotted; the
  `status.<value>`, `jury_role.<value>`, `app.name` keys already exist).
- **Tests:** the slice-007 `tests/Feature/Dashboard/*` patterns
  (`AdminDashboardPagePropsTest`, `AdminDashboardQueryCountTest`,
  `DashboardRoleGatingTest`, `DemoAdminDashboardIsolationTest`) are the templates
  for this slice's reporting tests. `tests/Arch/ArchitectureTest.php` for the new
  `App\Domain\Reporting` namespace rules.

### 2.4 Inertia prop shapes (snake_case — the page prop contract)

These shapes are the **load-bearing contract**: each controller payload and its
`.tsx` page interface must match EXACTLY, locked by a Pest Inertia prop-contract
test per page (memory rule: a prop-contract test for EVERY page). `status` always
serialises as the **enum value string** (e.g. `"graduated"`); `role` as the
`JuryRole` value string (e.g. `"president"`); dates as ISO-8601 strings (or `null`).

#### `Reporting/Index` (the hub) props

```
{
  reports: Array<{
    key: string,            // "graduates" | "terminal_efficiency" | "cohorts" | "judge_certificates"
    title_key: string,      // i18n key, e.g. "reports.graduates.title"
    description_key: string, // i18n key, e.g. "reports.graduates.description"
    route: string,          // resolved URL of the report (no dead-end card)
  }>,                       // EXACTLY 4 rows, fixed order
}
```

#### `Reporting/Graduates` props

```
{
  graduates: Array<{
    id: number,
    control_number: string,
    full_name: string,            // first_name + ' ' + last_name
    program_name: string,         // from the eager-loaded program (NOT NULL FK)
    graduation_type_name: string, // from the eager-loaded graduationType (NOT NULL FK)
    diploma_folio: string | null, // VARCHAR(50); null only for malformed data (graduated always has one)
    record_book: string | null,
    record_sheet: string | null,
    graduation_date: string | null, // ISO-8601 date (Y-m-d) or null
  }>,
  filters: {
    year: number | null,          // the applied enrollment/graduation-year filter (graduation year — see §2.8 D-YEAR), or null
    program_id: number | null,    // the applied program filter, or null
  },
  filter_options: {
    years: Array<number>,         // distinct graduation years present (desc), for the year <select>
    programs: Array<{ id: number, name: string }>, // the program catalog (unscoped), for the program <select>
  },
  total: number,                  // count of rows in `graduates` (the result size)
}
```

> **Filters are GET query params** (`?year=2025&program_id=3`) reflected back in
> `filters`; the page renders two `<select>`s posting via Inertia `router.get`
> (or `<Link>` with query) — server-side filtering, snapshot reload.

#### `Reporting/TerminalEfficiency` props

```
{
  by_cohort: Array<{
    cohort_year: number,    // enrollment year
    total: number,
    graduates: number,
    rate: number,           // INTEGER percent 0..100 (round(graduates/total*100); total==0 → 0)
  }>,                       // ordered by cohort_year desc
  by_program: Array<{
    program_id: number,
    program_name: string,
    total: number,
    graduates: number,
    rate: number,           // INTEGER percent 0..100
  }>,                       // ordered by program_name
  overall: {
    total: number,          // all scoped students
    graduates: number,      // all scoped graduated
    rate: number,           // INTEGER percent 0..100 across everyone
  },
}
```

#### `Reporting/Cohorts` props

```
{
  cohorts: Array<{
    cohort_year: number,    // enrollment year
    total: number,
    graduated: number,
    in_progress: number,    // total - graduated
    by_status: Array<{      // EXACTLY 9 rows, in step() order, every state present (0 if empty)
      status: string,       //   GraduationStatus value
      step: number,         //   1..9
      label_key: string,    //   "status.<value>"
      color: string,        //   color() token
      count: number,
    }>,
  }>,                       // ordered by cohort_year desc
}
```

#### `Reporting/JudgeCertificates` props

```
{
  professors: Array<{
    professor_id: number,
    professor_name: string,         // first_name + ' ' + last_name (+ mother_last_name if present)
    assignment_count: number,       // how many juries this professor sat (the rows below)
    assignments: Array<{
      jury_assignment_id: number,
      role: string,                 // JuryRole value: "president" | "secretary" | "vocal" | "substitute"
      role_label_key: string,       // "jury_role.<value>"
      student_id: number,
      student_control_number: string,
      student_name: string,         // student first_name + ' ' + last_name
      program_name: string,         // from student.program (NOT NULL FK)
      student_status: string,       // GraduationStatus value (ceremony/graduation context)
      ceremony_date: string | null, // ISO-8601 datetime or null
      graduation_date: string | null, // ISO-8601 date or null
    }>,
  }>,
  filters: {
    professor_id: number | null,    // the applied professor filter, or null
  },
  filter_options: {
    professors: Array<{ id: number, name: string }>, // professors who have served on ≥1 jury
  },
}
```

### 2.5 In scope — frontend (5 Inertia pages)

All pages: `final` React function components, theme + locale aware (`useLocale`),
WCAG 2.2 AA (one `<h1>`, `#main` landmark, semantic tables/lists — counts/rates
have text labels, never color-only; rates rendered as `N%`; real `<Link>`s never
dead-ends), mount the page chrome (`Head` + header + `LanguageSwitcher` +
`<DemoBanner />`), reuse `Graduation/AdminDashboard.tsx`'s tile/`TILE_ACCENT`
pattern where counts/colors appear. **Type-only** `import type { GraduationStatus,
JuryRole } from '@/types/generated'` where a value is compared (compare raw value
strings cast to the type — NEVER value-import the generated enum).

1. **`Reporting/Index.tsx`** — the hub: 4 report cards (`t(title_key)` +
   `t(description_key)` + an Inertia `<Link href={report.route}>`), responsive grid.
2. **`Reporting/Graduates.tsx`** — a filter bar (year `<select>` + program
   `<select>` + a "clear" link, all driving `router.get` with the query params) and
   a semantic `<table>` of graduates (control number, name, program, type, folio,
   book/sheet, graduation date) + a result-count line + an empty state.
3. **`Reporting/TerminalEfficiency.tsx`** — the `overall` rate as a headline stat
   card; a per-cohort `<table>` (`cohort_year`, `graduates`/`total`, `rate%`); a
   per-program `<table>` (program, `graduates`/`total`, `rate%`). Rates shown as
   `N%` text (a progress-bar visual is optional, color is a redundant cue).
4. **`Reporting/Cohorts.tsx`** — per cohort: the `cohort_year` heading, the
   `total`/`graduated`/`in_progress` summary, and the 9-state breakdown as the
   familiar count-tile grid (reusing the dashboard tile pattern, `step` order,
   `t(label_key)`, `TILE_ACCENT[color]`).
5. **`Reporting/JudgeCertificates.tsx`** — a professor `<select>` filter; per
   professor: name + `assignment_count`, then a `<table>` of their assignments
   (role via `t(role_label_key)`, student control number + name, program, student
   status via `t('status.'+status)`, ceremony/graduation dates). A small **"export
   coming soon" / disabled "Generate certificate" affordance is OUT** — the print
   button belongs to the deferred export slice (§2.7); the screen shows the data only.

6. **(Optional, recommended) a "Reports" nav link** added to
   `Graduation/AdminDashboard.tsx`'s header (an Inertia `<Link
   href={route('admin.reports.index')}>`), so the hub is reachable — minimal, one
   link, behind the same role gate. (Open question F.)

### 2.6 int/float handling for rates (DELIBERATE)

- **`rate` is an INTEGER percentage 0..100**, never a float. Computed as
  `(int) round($graduates / max($total, 1) * 100)` with an explicit
  `total === 0 → rate = 0` guard. Rationale: the money/float prohibition is about
  *currency*, but the spirit (be deliberate about numeric types; no surprising
  float drift in the contract) applies — an integer percent is the simplest exact
  contract, renders as `N%`, and the prop-contract test asserts `is_int($rate)`.
  (Open question C confirms int-percent over a `{graduates,total}`-only shape; we
  ship **both** the raw `graduates`/`total` ints **and** the derived int `rate`, so
  the page can show "37 / 100 (37%)" without re-deriving and a future export has the
  exact numbers.)
- **All counts/totals are ints.** Postgres returns aggregate counts as `mixed` via
  `pluck`/`selectRaw`; build totals with `(int)` per-bucket and `array_sum(
  array_map('intval', …))` or `is_numeric` guards — **never** `(int)` on a raw mixed
  sum (memory rule / PHPStan L9). `COUNT(*) FILTER (WHERE …)` returns a count too;
  cast each to int.

### 2.7 i18n

- **Frontend UI copy** in `resources/locales/{es,en}.json` (flat dotted, consumed
  via `useLocale().t`), new `reports.*` namespace:
  - hub: `reports.hub.title`, `reports.hub.subtitle`,
    `reports.graduates.title`/`.description`,
    `reports.terminal_efficiency.title`/`.description`,
    `reports.cohorts.title`/`.description`,
    `reports.judge_certificates.title`/`.description`.
  - graduates: `reports.graduates.subtitle`, `reports.graduates.col.control_number`,
    `…col.name`, `…col.program`, `…col.type`, `…col.folio`, `…col.book`,
    `…col.sheet`, `…col.graduation_date`, `reports.graduates.filter.year`,
    `…filter.program`, `…filter.all`, `…filter.clear`, `reports.graduates.count`,
    `reports.graduates.empty`.
  - terminal efficiency: `reports.terminal_efficiency.subtitle`,
    `…overall_heading`, `…by_cohort_heading`, `…by_program_heading`,
    `…col.cohort`, `…col.program`, `…col.graduates`, `…col.total`, `…col.rate`,
    `reports.terminal_efficiency.empty`.
  - cohorts: `reports.cohorts.subtitle`, `reports.cohorts.cohort_heading`
    (e.g. "Generación {year}"), `…total`, `…graduated`, `…in_progress`,
    `reports.cohorts.empty`.
  - judge certificates: `reports.judge_certificates.subtitle`,
    `…filter.professor`, `…filter.all`, `…assignment_count`,
    `…col.role`, `…col.student`, `…col.program`, `…col.student_status`,
    `…col.ceremony_date`, `…col.graduation_date`, `…export_deferred`
    (the "export coming soon" note), `reports.judge_certificates.empty`.
  - reused (already present): every `status.<value>` (9), every `jury_role.<value>`
    (4), `app.name`. Optional dashboard nav link copy: `reports.nav.link`.
- **PHP `__()` keys:** every controller in this slice **flashes/throws NOTHING**
  (GET-only renders) — so **NO new `lang/{es,en}.json` keys** and **NO PHP lang-key
  resolution test** are required. Falsifiable: if any controller is found calling
  `__()`, that key MUST be added to BOTH `lang/es.json` + `lang/en.json` with a
  resolution test — but the design forbids it. (Memory rule: read-only controllers
  that flash nothing need no `lang/` keys.)

### 2.8 Deviations / decisions flagged for the gate

- **D-READONLY.** No migration, no Action, no input DTO, no state mutation, no
  event — four read-model **services** + four (+ hub) anemic controllers + five
  Inertia pages + `reports.*` locale keys + tests. Minimal change satisfying SPEC
  §3.7.
- **D-SCOPE.** Every aggregate runs on the **DemoScope-scoped** `Student` /
  `JuryAssignment` — **never** `withoutGlobalScopes()`. A demo coordinator's
  reports count ONLY demo data; a real coordinator's ONLY real data. Proven by
  `DemoReportIsolationTest`. (Catalogs — `Program`/`GraduationType`/`Professor` —
  are unscoped reference data, joined for display only; a demo session sees the
  same catalog as a real session, which is correct.)
- **D-ONEQUERY.** Each report is **one** efficient aggregate/list query (+ eager
  loads); cohorts pivot + judge inversion are pure-PHP transforms of one result
  set. No N+1. Proven by `ReportQueryCountTest`.
- **D-RATE-INT.** `rate` is a rounded **integer percent** (0-100), not a float;
  `graduates`/`total` ship alongside as ints. (Open question C.)
- **D-NS.** Controllers live in a **new** `App\Http\Controllers\Reporting\`
  namespace (the SPEC §7.3 sketch says `Admin\ReportController`, but slices put
  domain-aligned controllers under a domain-named HTTP namespace —
  `Graduation\AdminDashboardController` etc.; a `Reporting\` namespace matches the
  `App\Domain\Reporting` domain and the established convention). Pages live under
  `resources/js/Pages/Reporting/`. (Open question D.)
- **D-FILTER.** The graduates/judge filters are **GET query params on a read-only
  endpoint, not validated input** — an out-of-range/non-numeric value simply yields
  no/unfiltered results (`$request->integer('year')` coerces; a non-existent
  program id matches nothing). **No `Spatie\LaravelData` DTO** is introduced (the
  DTO rule governs *mutating* input; a read-only display filter has no validation
  surface). Falsifiable: a garbage `?year=abc` returns 200 with a sensible
  (empty/unfiltered) result, never a 422/500.
- **D-YEAR.** The graduates report's year filter is the **graduation year**
  (`whereYear('graduation_date', …)`) — "graduates of 2025". The terminal-efficiency
  / cohorts grouping uses the **enrollment year** (`enrollment_date`) — "the 2019
  cohort". These are intentionally different axes (a 2019-cohort student graduates
  in 2024); flagged so implementer + tests agree. (Open question G.)
- **D-INVERT.** The judge report inverts `JuryAssignment`'s four professor columns
  into per-professor rows **in PHP** (one query + eager loads), not four SQL
  `UNION`s — simpler, scope-safe, and the data volume (juries per session) is small.
  Flagged as a deliberate read-model shape.
- **D-EXPORT-DEFERRED.** Excel (`maatwebsite/excel`), PDF (`barryvdh/laravel-dompdf`)
  and Word judge `.docx` (`phpoffice/phpword`) **export/download** are OUT of this
  slice (§2.9). The services return exactly the rows a future exporter will
  serialise, so the export slice adds only a generator + a `?export=xlsx`/download
  route — no re-query.

### 2.9 DEFERRED (explicitly OUT)

- **Excel / PDF / Word EXPORT + download** — REPORT-01/02/03 "Excel export",
  CERT-01 "Word document (.docx) per professor". This slice ships the **on-screen**
  reports + the exact data; the file generators + download routes are a dedicated
  later slice (D-EXPORT-DEFERRED). The judge "Generate certificate" button + the
  reports' "Download Excel/PDF" buttons are OUT.
- **REPORT-04 custom `ReportBuilder`** (SPEC §3.7 — flexible filters / grouping /
  COUNT-SUM-AVG-MIN-MAX) — a separate, larger slice; this slice ships the four
  fixed reports only.
- **The Analytics domain (SPEC §3.8 ANALYTICS-01..05)** — terminal-efficiency
  *dashboard with trend charts*, status pie, bottleneck analysis, monthly-graduates
  line chart, the alert system. Those are charts/time-series visualisations and a
  separate chapter; this slice ships accessible **tables/tiles only** (the same
  "no charts this slice" carve as slice 007's D-NOCHARTS). The terminal-efficiency
  *report* (REPORT-01, a table) is in scope; the terminal-efficiency *dashboard*
  (ANALYTICS-01, a chart) is not.
- **Date-range (from/to) filters** — SPEC REPORT-02 mentions a "date range filter".
  This slice ships a **year + program** filter (simpler, matches the cohort/year
  axis); an arbitrary from/to range is deferred to the export/ReportBuilder slice.
  (Open question H — recommend year filter now.)
- **Pagination of the graduates table** — the result set per session is bounded
  (a registrar's graduated cohort); this slice renders the full filtered list. If
  scale demands it later, add `->paginate()` (the queue controllers' pattern) — not
  needed now. (Open question I.)
- **Demo seeding of report-rich data** — the demo presets already seed pipeline
  students (slice 006); no new demo seeder. A demo coordinator's reports reflect
  whatever their sandbox contains (often sparse — that is correct + honest).

## 3. Acceptance scenarios (When… Then)

1. **Reporting hub lists the four reports.** *When* a staff user (admin /
   super_admin / secretary) GETs `admin.reports.index`, *then* `Reporting/Index`
   renders exactly 4 report cards, each a real `<Link>` to its report route (no
   dead-end).
2. **Graduates report lists graduated students with full records.** *When* staff
   GET `admin.reports.graduates`, *then* `Reporting/Graduates` lists every
   `Graduated` student (scoped) with `control_number`, `full_name`, `program_name`,
   `graduation_type_name`, `diploma_folio`, `record_book`, `record_sheet`,
   `graduation_date`, ordered by graduation date — and **no** non-graduated student
   appears.
3. **Graduates report filters by year + program (server-side).** *When* staff GET
   `admin.reports.graduates?year=2025&program_id=3`, *then* only graduates whose
   `graduation_date` year is 2025 and `program_id` is 3 appear; `filters` reflects
   `{year:2025, program_id:3}`; `filter_options.years` / `.programs` carry the
   selectable values. A garbage `?year=abc` returns 200 with an unfiltered/empty
   result (never 422/500).
4. **Terminal efficiency computes the int-percent rate per cohort and program.**
   *When* staff GET `admin.reports.terminal-efficiency` with a seeded cohort
   (e.g. 10 students enrolled 2019, 4 of them `Graduated`), *then* `by_cohort`
   contains a `{cohort_year:2019, total:10, graduates:4, rate:40}` row (`rate` is
   the **integer** 40, not 40.0 or "40%"); `by_program` rows carry the same shape
   per program; `overall.rate` is the int percent across all scoped students; a
   cohort with 0 students never appears and any `total===0` guard yields `rate:0`.
5. **Cohorts overview groups by enrollment year with a 9-state breakdown.** *When*
   staff GET `admin.reports.cohorts` with students spread across years and statuses,
   *then* each `cohorts[]` row has its `cohort_year`, `total`, `graduated`,
   `in_progress` (= total − graduated), and a `by_status` of **exactly 9** rows in
   `step()` order (empty states 0) with the right per-status `count`, `label_key`,
   `color`.
6. **Judge certificates list each professor's juries.** *When* staff GET
   `admin.reports.judge-certificates` with seeded juries, *then* each professor who
   sat on ≥1 jury appears with `assignment_count` and one `assignments[]` entry per
   jury they sat — each entry carrying the correct `role` (`president`/`secretary`/
   `vocal`/`substitute`) + `role_label_key`, the student's control number + name +
   program + status, and ceremony/graduation dates. Filtering `?professor_id=X`
   narrows to that professor.
7. **Single aggregate per report (no N+1).** *When* any report request runs, *then*
   its data is produced by **one** aggregate/list query (+ eager loads) — no
   per-group, per-status or per-professor loop (falsifiable via query logging).
8. **Demo coordinator's reports count ONLY demo data (CRITICAL).** *When* a demo
   staff session views any report while real students/juries and another demo
   session's rows exist, *then* every count/rate/list reflects **only that demo
   session's** data — never real, never another session's — because the aggregates
   run through the DemoScope and the services **never** `withoutGlobalScopes()`.
9. **Real coordinator's reports count ONLY real data (symmetric).** *When* a real
   staff user views any report while demo rows exist, *then* every count/list
   excludes every demo-tagged student/jury.
10. **Role gating.** *When* a guest hits any report route, *then* redirect to
    `login`; *when* a `student`, `assistant_secretary` or `school_services` user
    hits any report route, *then* **403**; admin / super_admin / secretary → 200.
11. **Read-only.** *When* any report renders, *then* **no** DB write, status
    transition, event, or folio mint occurs (GET-only; no Action; no DTO).
12. **Rates are integers.** *When* any terminal-efficiency prop is serialised,
    *then* every `rate` is a PHP `int` in 0..100 (asserted `is_int`), never a float
    or a pre-formatted string.
13. **Theme + locale + a11y.** *When* any report renders, *then* it honours
    dark/light + ES/EN via `useLocale`, exposes one `<h1>`, a `#main` landmark,
    semantic tables/lists, text labels on every count/rate (never color-only), and
    real `<Link>`s (WCAG 2.2 AA).
14. **Prop contract locked.** *When* the prop-contract tests run, *then* each
    controller payload matches its page interface exactly (snake_case; `status`/
    `role` as value strings; the fixed-shape 9-row cohort breakdown; int rates), so
    a future controller/page drift fails CI.
15. **Export deferral is honest.** *When* the judge report (or any report) renders,
    *then* there is **no** working export/download button — the data is on-screen
    only; the export slice will add the generators without re-querying.

## 4. Success criteria (definition of done for this slice)

- `composer format` (Pint) clean, `composer analyse` (Larastan **level 9/10**)
  green, `composer test` (Pest: arch + the new Feature/contract/aggregate/isolation
  tests on PostgreSQL 18) green.
- `php artisan typescript:transform` emits cleanly (the optional `#[TypeScript]`
  view-DTOs, if adopted per Open question B, appear in `generated.d.ts`; if not, no
  new type is added and existing types are unchanged).
- The five routes appear in `php artisan route:list` with the names/middleware of
  §2.1; all under `['auth','demo','role:admin,super_admin,secretary']`.
- A recruiter (demo coordinator) and a real coordinator both: open the reporting
  hub → open each report → see correct, mode-isolated data → filter where
  applicable — with no dead-end and no real PII leaking into a demo report.
- **Security gate (falsifiable):** `DemoReportIsolationTest` passes — every report's
  counts/lists reflect only the acting session's mode.
- **Performance gate (falsifiable):** `ReportQueryCountTest` passes — each report is
  one aggregate/list query (no N+1).
- **Correctness gate (falsifiable):** `TerminalEfficiencyReportTest` /
  `CohortsReportTest` / `GraduatesReportTest` / `JudgeCertificatesReportTest` pass
  against a deterministically seeded cohort (exact counts + int rates).
- No real PII in factories (fictional students/professors/programs); `.gitignore`
  still blocks db/secrets.

## 5. Constitution (§No-negociables) this slice must satisfy

`declare(strict_types=1)` everywhere · explicit return types · `final` controllers
+ `final` Reporting services (+ `final readonly` view-DTOs if adopted) · anemic
controllers (≤15 lines, render-only, snake_case props shaped via service +
`->only()`/`->through()`/explicit array) · **read-only: no Action, no migration, no
input DTO, no domain state mutation, no event** (the Spatie-Data-DTO mutation rule
is N/A — there is no validated input; read-only display filters are plain GET query
params, D-FILTER) · named routes / no closures · backed-enum reuse
(`GraduationStatus`, `JuryRole`) — `status`/`role` serialised as the enum **value**
string in props · every aggregate runs on the **DemoScope-scoped** model — **never**
`withoutGlobalScopes()` (would leak real data into a demo report) · each report is
**one** aggregate/list query, no N+1 (Ley 9) · **`rate` is an int percent**, counts
are ints built with `intval` guards (no float, no raw `(int)` on a mixed Postgres
sum) · the Reporting domain **may read** `Graduation` / `Jury` / `Academic` models
but **must NOT import `App\Domain\Identity`**, and Reporting ↛ `Illuminate\Http`
(services are domain-private; the controllers are in `App\Http`) · authorization
stays in HTTP middleware (`EnsureRole` + `demo`), never the domain · Inertia props
snake_case + a Pest prop-contract test per page · the generated TS enum is
**type-only** imported (compare raw value strings cast to the type — never
value-imported) · dark/light + ES/EN on all five pages, WCAG 2.2 AA · frontend copy
in `resources/locales/{es,en}.json`; **no** new PHP `__()` key (controllers flash
nothing) · Pest with arch tests on PostgreSQL (never SQLite) · bigint ids (ADR-001).

## 6. Open questions for the gate

- **A. Services vs inline controller shaping.** Slice 007 inlined its single
  aggregate in the controller. This slice has four substantial aggregates →
  **Recommend: extract to `app/Domain/Reporting/Services/*` read-model services**
  (SPEC §5.6 "reads without writing → Service"; keeps controllers ≤15 lines; each
  service is independently unit-testable; the future export reuses them). (If the
  gate prefers controller-private helpers like the queue controllers, the four
  could stay inline — but four multi-line aggregates would bloat the controllers
  past the anemic budget.)
- **B. `#[TypeScript]` view-DTOs?** Wrap each report's output rows in a
  `final readonly` `Spatie\LaravelData` `#[TypeScript]` view-DTO (auto-generated TS
  types, snake_case mapped), or ship plain snake_case arrays like the slice-007
  dashboard? **Recommend: plain arrays** (no input to validate; the dashboard
  precedent ships plain arrays; a view-DTO adds ceremony without a validation
  payoff). If the gate wants the typed contract surfaced to TS, adopt the DTOs —
  but keep the exact snake_case shapes of §2.4 and the prop-contract tests.
- **C. Rate type.** Int percent (0-100) vs a `{graduates,total}`-only shape (rate
  derived on the client) vs a float. **Recommend: ship `graduates`+`total`+ a
  derived int `rate`** (exact ints for a future export + a ready-to-render int
  percent; `is_int` asserted). (D-RATE-INT.)
- **D. HTTP namespace + page folder.** `App\Http\Controllers\Reporting\*` +
  `resources/js/Pages/Reporting/*` (domain-aligned, established convention) vs the
  SPEC literal `Admin\ReportController`. **Recommend: `Reporting\` namespace.**
  (D-NS.)
- **E. Hub query.** The hub renders query-free links, or also a tiny headline (e.g.
  `total_graduates`) reusing a scoped count? **Recommend: query-free links only**
  (each report owns its data; the hub is pure navigation).
- **F. "Reports" nav link on the admin dashboard.** Add one `<Link>` to
  `admin.reports.index` in the `AdminDashboard.tsx` header so the hub is reachable?
  **Recommend: yes** (one link, same role gate, avoids an orphaned hub — the
  ux-flow "no unreachable screen" rule). Minimal blast radius.
- **G. Year axis.** Graduates filter = **graduation** year; efficiency/cohorts
  grouping = **enrollment** year. **Recommend: confirm the split** (D-YEAR).
- **H. Date-range vs year filter.** SPEC REPORT-02 says "date range filter"; this
  slice ships a year (+ program) filter. **Recommend: year filter now, arbitrary
  from/to deferred** to the export/ReportBuilder slice.
- **I. Pagination of the graduates table.** Full filtered list now, `->paginate()`
  later if scale demands. **Recommend: full list now** (bounded per session).
- **J. Arch rule for Reporting.** Mirror the Jury/Ceremony arch shape — `Reporting
  ↛ Identity`, Reporting classes/services final — and **NOT** a literal "only
  Shared" rule (Reporting legitimately reads Graduation/Jury/Academic models).
  **Recommend: `Reporting ↛ Identity` + final-services rules.** (Matches the
  cross-domain reality slices 003/004 already established.)
