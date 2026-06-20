# Spec 007 — Dashboards (student pipeline-overview home + admin system-overview home)

> **Phase:** Specify (the WHAT and WHY, not the HOW).
> **SSOT:** `SPEC.md` §7.2 student routes — row 1614 `GET /student/dashboard` →
> `Student\DashboardController@index` "Pipeline status overview"; §7.3 admin
> routes — row 1626 `GET /admin/dashboard` → `Admin\DashboardController@index`
> "Admin dashboard with stats + charts"; §RBAC row 1866-1867 ("View student
> dashboard" = student only; "View admin dashboard" = all staff roles); §15 Phase
> 3 "Student dashboard (static, no real-time yet)". The `GraduationStatus` enum (9
> states, `step()` 1-9, `labelKey()`, `color()`), the `Student` aggregate +
> pipeline columns, and the staff queues already built in slices 001-005
> (FormBReview, AnnexIiiPending docs, payment-to-verify, jury-to-assign,
> ceremony-to-schedule, ceremony-passed-to-graduate).
> **Builds on (reuse VERBATIM — do NOT duplicate):** the **demo slice (006)** —
> the `DemoScope` symmetric global scope on `Student`/`StudentDocument`/
> `JuryAssignment` (so dashboard COUNT/aggregate queries are automatically correct
> for real vs demo admins), `DemoContext`, the `demo` middleware, the shared
> `demo` Inertia prop + `DemoBanner.tsx`; the **auth slice (005)** —
> `RoleLandingRoute::for()` (the post-login redirect map), `EnsureRole`, the
> `auth`/`role` route groups, `UserFactory` role states; the **graduation slices
> (001-004)** — `Student` model + `GraduationStatus`, `StudentFactory`
> pipeline-stage states, `GraduationProgress.tsx`, `Status.tsx`, `Review.tsx` /
> `JuryAssign.tsx` / `DocumentReview.tsx` / `CeremonySchedule.tsx`, the
> `Student/Status` + `Graduation/*` page patterns, `LocaleContext` / `useLocale`,
> `LanguageSwitcher`. **This slice mirrors slice 006 conventions VERBATIM**
> (controller ≤15 lines, anemic, snake_case props shaped with `->only()` /
> explicit array; named routes / no closures; a Pest Inertia prop-contract test
> per page; arch tests; Pest on PostgreSQL 18). **READ-ONLY slice: no migration,
> no Action, no state mutation, no DTO** (a tiny internal view-array is fine; no
> `Spatie\LaravelData` DTO is required because nothing is validated/submitted).
> **Status:** DRAFT — awaiting human review gate before `/sdd-plan`.

## 1. Problem & why

After slices 001-006 the pipeline is complete (9 states, auth, demo, real-time
status) but **post-login lands users on a *task* screen, not a *home*.** A
student logs in and lands directly on `student.status` (a single live-status
board); a staff member lands directly on `admin.graduation.review` (the Form B
queue). SPEC §7.2/§7.3 specify a **dashboard** as each role's home:
`GET /student/dashboard` ("Pipeline status overview") and `GET /admin/dashboard`
("Admin dashboard with stats"). **Neither route, controller, nor page exists.**
`RoleLandingRoute::for()` currently redirects to the task screens.

This slice adds the two **read-only overview homes** and re-points the
post-login landing at them, so:

- A **student** lands on a personal **pipeline overview**: their current
  `GraduationStatus` (value + step 1-9 + label + color), the 9-step progress
  summary (done vs pending), and a **single primary call-to-action** that deep-
  links to the one screen where their next action lives (Form B / documents /
  payment / status).
- A **staff member** (admin / super_admin / secretary) lands on a **system
  overview**: how many students sit in each of the 9 `GraduationStatus` states
  (ONE grouped-count query — no N+1), the size of each pending staff queue, the
  total graduates, and quick links into each queue.

Because the COUNT/aggregate queries run through the **DemoScope-scoped** `Student`
model, a **demo admin's dashboard automatically counts only their own demo
students** and a **real admin's dashboard automatically counts only real
students** — the isolation guarantee from slice 006 extends to the dashboard with
**zero extra code** (and we must NOT bypass the scope, which would leak real data
into a demo dashboard).

## 2. Scope

### 2.1 In scope — backend (READ-ONLY)

1. **Two routes** (`->name()`, no closures), each gated by `auth` + `demo` + the
   role gate, mirroring the existing groups in `routes/web.php`:
   - `GET /student/dashboard` → `Student\DashboardController@index`, name
     **`student.dashboard`**, in the existing `['auth', 'demo', 'role:student']`
     group.
   - `GET /admin/dashboard` → `Graduation\AdminDashboardController@index` (HTTP
     namespace mirrors the existing `App\Http\Controllers\Graduation\*` staff
     controllers — see plan §recon / Open question A), name **`admin.dashboard`**,
     in the existing `['auth', 'demo', 'role:admin,super_admin,secretary']` group.
2. **`Student\DashboardController`** — anemic (≤15 lines), `final extends
   Controller`. `index(Request $request): Response`. Resolves the authenticated
   student via `$request->user()?->student` (mirrors `StatusController`), derives
   a small overview payload from the student's `status` (a `GraduationStatus`
   enum instance via the cast), and renders `Student/Dashboard` with snake_case
   props (§2.3). No Eloquent writes; no Action. The DemoScope on the `student`
   relation already isolates the demo student.
3. **`Graduation\AdminDashboardController`** — anemic (≤15 lines), `final extends
   Controller`. `index(): Response`. Computes:
   - **the 9-state status histogram** with **ONE grouped-count query**:
     `Student::query()->select('status', DB::raw('count(*) as total'))
     ->groupBy('status')->pluck('total', 'status')` (or `->get()->pluck(...)`),
     then **normalised in PHP** to a complete map of **all 9** `GraduationStatus`
     cases (missing states → 0) so the page always receives a fixed-shape array
     of 9 rows in `step()` order. This is the only aggregate query that hits the
     status column; **no per-state COUNT loop** (would be an N-query smell).
   - **`total_graduates`** = the histogram's `graduated` bucket (no extra query —
     derived from the same grouped result).
   - **`total_students`** = `array_sum` of the histogram buckets (no extra query).
   - **the pending-queue sizes** — each is the count of students in the
     `GraduationStatus` that the corresponding staff queue lists (see §2.2 the
     queue→status table). These are **derived from the same histogram** wherever
     the queue is exactly "students in state X" (Form B review, Annex III docs,
     jury assignment, ceremony scheduling, graduation-eligible) — **no additional
     queries** for those. (`ceremony_passed` "ready to graduate" is a refinement —
     see §2.2 note + Open question C.)
   - **the per-status quick-link route names** for the page's deep links.
   All counts run on the **DemoScope-scoped** `Student` model. **NEVER**
   `withoutGlobalScope(DemoScope::class)` here (that would leak real counts into a
   demo admin dashboard and vice-versa). Anemic; no Action; read-only.
4. **`RoleLandingRoute::for()` change** — re-point the post-login landing:
   `UserRole::Student` → **`student.dashboard`** (was `student.status`);
   `Admin | SuperAdmin | Secretary` → **`admin.dashboard`** (was
   `admin.graduation.review`). `AssistantSecretary | SchoolServices` →
   **`landing`** (unchanged — they have no dashboard route gated to them in this
   slice; the admin dashboard is `role:admin,super_admin,secretary` only, matching
   the auth group and SPEC RBAC). The previous targets (`student.status`,
   `admin.graduation.review`) stay fully reachable as named routes. The match
   stays **exhaustive** over the 6-role enum (compile-time obligation preserved).

### 2.2 Pending staff queues — the queue → status mapping (SSOT for the admin counts)

The staff queues already built (slices 001-004) each list **students in exactly
one `GraduationStatus`** (verified against the queue controllers' `where('status',
…)` predicates):

| queue (route name)                         | lists students in status        | `step()` | quick-link target              |
|--------------------------------------------|---------------------------------|----------|--------------------------------|
| Form B review (`admin.graduation.review`)  | `FormBReview` (2)               | 2        | `admin.graduation.review`      |
| Annex III docs (`admin.graduation.documents.index`) | `AnnexIiiPending` (5)  | 5        | `admin.graduation.documents.index` |
| Jury / payment (`admin.graduation.jury.index`) | `PaymentPending` (6)        | 6        | `admin.graduation.jury.index`  |
| Ceremony scheduling (`admin.graduation.ceremony.index`, top table) | `JuryAssigned` (7) | 7 | `admin.graduation.ceremony.index` |
| Graduation (`admin.graduation.ceremony.index`, bottom table) | `CeremonyScheduled` (8) | 8 | `admin.graduation.ceremony.index` |

> Each queue size is therefore the matching bucket of the **single histogram** —
> no extra query. The histogram is the SSOT for both the 9-state breakdown AND the
> queue sizes.
>
> **Refinement (Open question C):** the ceremony "graduating" bottom table lists
> `CeremonyScheduled` students but the **graduate action is only enabled once the
> ceremony date has passed** (`ceremonyPassed`). The dashboard's "ready to
> graduate" count is, in this slice, simply the `CeremonyScheduled` bucket
> (scheduled-or-passed), which matches the queue's row count exactly. A precise
> "date has passed" count would need a second `where('ceremony_date','<',now())`
> query; **deferred** to keep the single-query guarantee. Decided in plan §4.

### 2.3 Inertia prop shapes (snake_case — the page prop contract)

These shapes are the **load-bearing contract**: the controller payload and the
`.tsx` page interface must match EXACTLY, and a Pest Inertia prop-contract test
locks each (memory rule: a prop-contract test for EVERY page). `status` /
`GraduationStatus` always serialise as the **enum value string** (e.g.
`"jury_assigned"`), never the enum object.

#### `Student/Dashboard` props

```
{
  student_id: number | null,            // null if the user has no student row (defensive, mirrors StatusController)
  control_number: string | null,
  full_name: string | null,             // first_name + ' ' + last_name, or null
  status: string | null,                // GraduationStatus value, e.g. "annex_iii_pending"
  step: number | null,                  // status.step() 1..9, or null
  total_steps: number,                  // constant 9
  is_graduated: boolean,                // status === Graduated
  cta: {                                // the single primary next-action deep link, or null at terminal
    route: string,                      //   a named route resolved to a URL (e.g. "/student/documents")
    label_key: string,                  //   i18n key in resources/locales (e.g. "dashboard.student.cta.documents")
  } | null,
  form_b_observations: string | null,   // surfaced when status is form_b_rejected (re-submit hint)
}
```

> **CTA resolution (decided here):** the next-action target is a pure function of
> the current `GraduationStatus`, computed in the controller (no domain logic —
> just a `match`):
>
> | status                         | cta.route (URL via `route()`)        | cta.label_key                          |
> |--------------------------------|--------------------------------------|----------------------------------------|
> | `FormBPending`                 | `student.form-b.create`              | `dashboard.student.cta.form_b`         |
> | `FormBReview`                  | `student.status`                     | `dashboard.student.cta.status_review`  |
> | `FormBRejected`                | `student.form-b.create`              | `dashboard.student.cta.form_b_fix`     |
> | `AnnexesPending`               | `student.documents.index`            | `dashboard.student.cta.documents_begin`|
> | `AnnexIiiPending`              | `student.documents.index`            | `dashboard.student.cta.documents`      |
> | `PaymentPending`               | `student.payment.index`              | `dashboard.student.cta.payment`        |
> | `JuryAssigned`                 | `student.status`                     | `dashboard.student.cta.status_track`   |
> | `CeremonyScheduled`            | `student.status`                     | `dashboard.student.cta.status_ceremony`|
> | `Graduated`                    | `null` (no CTA — show celebration)   | —                                      |
>
> The CTA's `route` is a **resolved URL string** (the controller calls
> `route('student.documents.index')`) so the page links it directly without
> knowing route names. This keeps "no dead-end button" (every CTA points at a real
> reachable screen).

#### `Graduation/AdminDashboard` (or `Admin/Dashboard`) props

```
{
  status_breakdown: Array<{            // EXACTLY 9 rows, in step() order 1..9, every state present (0 if empty)
    status: string,                    //   GraduationStatus value
    step: number,                      //   1..9
    label_key: string,                 //   status.labelKey() → "status.<value>"
    color: string,                     //   status.color() token: "primary" | "warning" | "danger" | "success"
    count: number,
  }>,
  queues: Array<{                      // the pending staff queues, fixed order
    key: string,                       //   "form_b" | "documents" | "jury" | "ceremony" | "graduation"
    label_key: string,                 //   i18n key, e.g. "dashboard.admin.queue.form_b"
    count: number,                     //   the matching histogram bucket
    route: string,                     //   resolved URL of the queue's index (quick link)
  }>,
  totals: {
    students: number,                  //   sum of all 9 buckets (scoped)
    graduates: number,                 //   the graduated bucket
    in_progress: number,               //   students - graduates
  },
}
```

> All three sub-shapes derive from the **one** grouped-count query (§2.1.3) +
> pure PHP normalisation + `route()` URL resolution. No N+1, no second status
> query (modulo the deferred ceremony-passed refinement, Open question C).

### 2.4 In scope — frontend

1. **`Student/Dashboard.tsx`** — a personal pipeline overview. Reuses
   **`GraduationProgress`** (the 9-step bar component, fed the `status` value)
   for the progress summary; a status badge styled by `color` (reuse the badge
   pattern from `Status.tsx` / `DocumentStatusBadge`); a **done-vs-pending step
   list** (the 9 steps with the current one highlighted, prior ones marked done,
   later ones pending — driven by `step`/`total_steps`); and a single prominent
   **primary CTA button** linking to `cta.route` with `t(cta.label_key)` (hidden
   when `cta` is null, i.e. graduated → show a celebration block like
   `Status.tsx`'s graduated section). When `status === 'form_b_rejected'`, surface
   `form_b_observations`. Mounts `<DemoBanner />` (slice 006) and
   `<LanguageSwitcher />` like every authenticated page. Theme + locale aware,
   WCAG 2.2 AA (the CTA is a real `<a>`/Inertia `<Link>`; the step list is a
   semantic `<ol>` with non-color state cues; one `<h1>`; `#main` landmark).
   Type-only `import type { GraduationStatus } from '@/types/generated'`; compare
   raw string values cast to the type (never value-import the enum).
2. **`Graduation/AdminDashboard.tsx`** — a system overview. A **counts grid**:
   the totals (students / graduates / in-progress) as headline stat cards; the
   **9-state breakdown** as a grid of labelled count tiles, each coloured by its
   `color` token and labelled via `t(label_key)`, in `step` order; the **pending
   queues** as a row of quick-link cards (each `t(label_key)` + `count` badge +
   an Inertia `<Link href={queue.route}>` "open queue"). Reuses the page chrome
   (`header` + `LanguageSwitcher` + `DemoBanner`). No charts in this slice (SPEC
   "charts" are deferred to the analytics slice — Open question D); plain
   accessible count tiles only. Theme + locale aware, WCAG 2.2 AA (stat tiles are
   a `<dl>` or labelled list, not bare divs; counts have text labels, never
   color-only; quick links are real links).
3. **Wire the post-login landing** — no frontend change needed beyond the two new
   pages; the redirect is server-side via `RoleLandingRoute`. (`Welcome.tsx`'s
   `landing.cta.demo` / `landing.cta.login` are unchanged.)
4. **Optional nav affordance (decided in plan, Open question E):** a "back to
   dashboard" link in the task-screen headers is **OUT of this slice** to keep the
   blast radius minimal (the task screens already render `DemoBanner`); flagged.

### 2.5 i18n

- **Frontend UI copy** in `resources/locales/{es,en}.json` (consumed via
  `useLocale().t`):
  - student: `dashboard.student.title`, `dashboard.student.subtitle`,
    `dashboard.student.progress_heading`, `dashboard.student.next_step_heading`,
    `dashboard.student.cta.form_b`, `…cta.form_b_fix`, `…cta.status_review`,
    `…cta.documents_begin`, `…cta.documents`, `…cta.payment`, `…cta.status_track`,
    `…cta.status_ceremony`, `dashboard.student.graduated.title`,
    `dashboard.student.graduated.body`, `dashboard.student.observations_heading`,
    `dashboard.student.step.done`, `dashboard.student.step.current`,
    `dashboard.student.step.pending` (the non-color step cues).
  - admin: `dashboard.admin.title`, `dashboard.admin.subtitle`,
    `dashboard.admin.totals.students`, `…totals.graduates`, `…totals.in_progress`,
    `dashboard.admin.breakdown_heading`, `dashboard.admin.queues_heading`,
    `dashboard.admin.queue.form_b`, `…queue.documents`, `…queue.jury`,
    `…queue.ceremony`, `…queue.graduation`, `dashboard.admin.queue.open`,
    `dashboard.admin.empty` (no students yet).
  - reused (already present): every `status.<value>` label key (the 9 status
    labels), `app.name`, `progress.step`, `status.graduated.*`.
- **PHP `__()` keys:** this slice's controllers **flash/throw NOTHING** (they only
  render) — so **no new `lang/{es,en}.json` keys** are required, and **no PHP
  lang-key resolution test** is needed (there is nothing to resolve). Frontend
  copy lives in `resources/locales` only. (Falsifiable: if a controller is found
  calling `__()`, that key must be added to BOTH `lang/es.json` + `lang/en.json`
  with a resolution test — but the design forbids it.)

### 2.6 Tests (Pest, PostgreSQL 18 — never SQLite; app-needing tests in `tests/Feature/`)

**Backend (Feature):**

- `tests/Feature/Dashboard/StudentDashboardPagePropsTest.php` — prop-contract:
  acting as a student with a `Student` at a known status (e.g.
  `documentsStage()` → `AnnexIiiPending`), `GET route('student.dashboard')`
  → `assertOk()` + `assertInertia` component `Student/Dashboard` with the EXACT
  snake_case shape (§2.3): `status` is the **value string** `"annex_iii_pending"`,
  `step === 5`, `total_steps === 9`, `is_graduated === false`, `cta.route` ===
  `route('student.documents.index', absolute:false)` (or the resolved URL),
  `cta.label_key === 'dashboard.student.cta.documents'`, `full_name`/
  `control_number` correct, `form_b_observations` present. A second case: a
  `formBRejected()` student exposes the rejection observations + the `form_b_fix`
  CTA. A third: a `Graduated` student (via `ceremonyPassed()` + graduate, or
  forceFill status) has `is_graduated === true` and `cta === null`.
- `tests/Feature/Dashboard/AdminDashboardPagePropsTest.php` — prop-contract +
  **counts correctness**: seed students across several statuses (e.g. 3
  `formBReview`, 2 `documentsStage`, 1 `paymentPending`, 1 `juryAssigned`, 2
  `ceremonyScheduled`, 4 graduated). `GET route('admin.dashboard')` as an admin
  → component `Graduation/AdminDashboard`, `status_breakdown` has **exactly 9**
  rows in `step` order with the exact per-state counts (the empty states are 0),
  each row's `status` is the value string + `label_key === "status.<value>"` +
  `color` matches `GraduationStatus::from($value)->color()`; `queues` has the 5
  rows with counts equal to the matching buckets (form_b=3, documents=2, jury=1,
  ceremony=2, graduation=2) and each `route` a resolved queue URL; `totals.students`
  === sum, `totals.graduates` === 4, `totals.in_progress` === students-4.
- `tests/Feature/Dashboard/AdminDashboardQueryCountTest.php` — **single-query
  guarantee (falsifiable):** wrap the request in `DB::listen` (or
  `assertQueryCount` via a counter) and assert the status-aggregate is computed
  with **exactly one** `group by status` query — no per-state COUNT loop, no N+1.
  (Concretely: count queries whose SQL contains `group by` / `from "students"` and
  assert the status histogram added exactly one such aggregate.)
- `tests/Feature/Dashboard/DashboardRoleGatingTest.php` — role gating:
  - `student.dashboard`: a guest → redirect to `login`; a non-student
    authenticated user (admin) → **403**; a student → 200.
  - `admin.dashboard`: a guest → redirect to `login`; a `student` → **403**; an
    `assistant_secretary` / `school_services` user → **403** (not in the
    `role:admin,super_admin,secretary` group); `admin` / `super_admin` /
    `secretary` → 200.
- `tests/Feature/Dashboard/DemoAdminDashboardIsolationTest.php` — **the
  DemoScope-extends-to-dashboard security core (falsifiable):**
  1. Provision a demo admin session A (with N demo students at assorted statuses,
     tagged to A) alongside several **real** students at the same statuses. Acting
     as A's user `->withSession(A demo keys)`, `GET admin.dashboard`: the
     `status_breakdown` counts + `totals` reflect **only A's demo students** —
     **never** the real students, **never** another demo session B's students.
     (Mirrors `DemoIsolationTest::demoStaffWithStudent()` fixture helper.)
  2. A **real** admin's `admin.dashboard` counts **only real** students and
     **excludes** every demo-tagged student (symmetric scope).
  3. Falsifiable guard: `Student::withoutGlobalScopes()->count()` exceeds the
     demo dashboard's `totals.students` (the rows physically exist; the scope —
     not deletion — hides them), and the controller does **not** call
     `withoutGlobalScope` (assert the real-admin count != all-rows count when both
     real and demo rows exist).

**Backend (Arch):**

- The existing `tests/Arch/ArchitectureTest.php` broad rules already cover the new
  controllers (`controllers never touch Eloquent directly` is **N/A** — these
  read-only dashboard controllers DO query Eloquent for counts, exactly as the
  existing `FormBReviewController`/`JuryController`/`CeremonyController` index
  methods do; those are already permitted). Assert: the new controllers are
  `final`, `declare(strict_types=1)`, no `Illuminate\Http` import inside any
  `App\Domain` (the controllers live in `App\Http`, not the domain — no domain
  file is added). **Add no new forbidding rule** (no Action/DTO/domain class is
  created). If the existing arch suite asserts "controllers are anemic / ≤ N
  lines", these must satisfy it.

**Frontend (Vitest):**

- `resources/js/Pages/Student/__tests__/Dashboard.test.tsx` — renders the
  progress summary + the primary CTA with the right label and `href`; when
  `is_graduated` (cta null) it shows the celebration block and **no** CTA; when
  `status === 'form_b_rejected'` it surfaces the observations. (Vitest setup
  already mocks `window.matchMedia`; mock `@inertiajs/react` `Link`/`usePage` —
  provide a `demo: null` page prop so `DemoBanner` renders nothing, mirroring the
  `DemoChooser.test.tsx` mock pattern.)
- `resources/js/Pages/Graduation/__tests__/AdminDashboard.test.tsx` — renders the
  9 breakdown tiles with their counts + labels, the 3 totals, and the 5 queue
  quick-link cards each linking to its `route`; an `empty` state when
  `totals.students === 0`.

### 2.7 DEFERRED (explicitly OUT)

- **Charts / analytics visualisations** (SPEC §7.3 "stats + charts", §ANALYTICS-01
  terminal-efficiency dashboard, the "7 chart types" of Phase 4) — OUT; this slice
  ships accessible count tiles only. Charts belong to the dedicated analytics
  slice. (Open question D.)
- **Real-time dashboard updates** — SPEC §15 explicitly says "Student dashboard
  (static, no real-time yet)". The live status board (`Student/Status`) keeps its
  Reverb subscription; the **dashboard is a static snapshot** (no Echo). OUT.
- **`/admin/students` advanced filters / detail / the catalog CRUD / reports**
  (SPEC §7.3 other rows) — separate slices; the dashboard only **links** to the
  existing queues.
- **Precise "ready to graduate" (ceremony date passed) count** — the dashboard
  uses the `CeremonyScheduled` bucket (matches the queue row count) to preserve
  the single-query guarantee; a `ceremony_date < now()` refinement is deferred.
  (Open question C.)
- **Assistant-secretary / school-services dashboards** — those roles land on
  `landing` (no dashboard gated to them this slice); their dashboards (if any) are
  a later slice. The admin dashboard is `role:admin,super_admin,secretary` only,
  matching the existing staff auth group + SPEC RBAC row 1867.
- **A view DTO (`Spatie\LaravelData`)** — not required: nothing is validated or
  type-shared to a form; the controllers shape a plain snake_case array (mirroring
  the existing `FormBReviewController::index` `->through(... => [...])`). (Open
  question B — recommend: no DTO.)

### 2.8 Deviations / decisions flagged for the gate

- **D-READONLY.** No migration, no Action, no DTO, no domain class — just two
  anemic read-only controllers + two Inertia pages + the `RoleLandingRoute` edit.
  This is the minimal change that satisfies SPEC §7.2/§7.3.
- **D-ONEQUERY.** The admin status histogram is **one** `group by status`
  aggregate, normalised in PHP to all 9 states; queue sizes + totals derive from
  it (no extra queries). Proven by `AdminDashboardQueryCountTest`.
- **D-SCOPE.** Dashboard counts run on the **DemoScope-scoped** `Student` — never
  `withoutGlobalScope`. A demo admin's dashboard counts only demo students; a real
  admin's only real. Proven by `DemoAdminDashboardIsolationTest`.
- **D-LANDING.** `RoleLandingRoute` re-points students→`student.dashboard`,
  staff→`admin.dashboard`; the old targets stay reachable. Exhaustive match
  preserved.
- **D-NOCHARTS.** Count tiles only; charts deferred to analytics (SPEC Phase 4).
- **D-STATIC.** Dashboard is a static snapshot (no Reverb), per SPEC §15.
- **D-ADMINNS.** The admin dashboard controller lives in
  `App\Http\Controllers\Graduation\` (where the other staff queue controllers
  live) rather than a new `Admin\` namespace, to avoid introducing a one-off
  namespace; the SPEC row 1626 names `Admin\DashboardController` but the
  established slice convention put `FormBReviewController` etc. under `Graduation\`
  — we follow the established convention. (Open question A — gate confirms.)

## 3. Acceptance scenarios (When… Then)

1. **Student lands on their dashboard after login.** *When* a student
   authenticates (real or demo `sustentante_*`), *then* `RoleLandingRoute` sends
   them to `student.dashboard`, which renders `Student/Dashboard` showing their
   current `GraduationStatus` (value + step 1-9 + label + color), the 9-step
   progress summary, and a single primary CTA deep-linking to their active step.
2. **Student CTA matches their status.** *When* the dashboard renders for a
   student in `AnnexIiiPending`, *then* `cta.route` resolves to
   `student.documents.index` and `cta.label_key` is
   `dashboard.student.cta.documents`; for `PaymentPending` → `student.payment.index`
   /`…cta.payment`; for `FormBPending`/`FormBRejected` → `student.form-b.create`;
   for `Graduated` → `cta` is `null` and a celebration block shows instead.
3. **Rejected student sees the fix path.** *When* the student's status is
   `FormBRejected`, *then* the dashboard surfaces `form_b_observations` and a CTA
   pointing back to `student.form-b.create` (`…cta.form_b_fix`).
4. **Staff lands on the admin dashboard after login.** *When* an admin /
   super_admin / secretary authenticates (real or `admin`/`personal`-mapped demo),
   *then* `RoleLandingRoute` sends staff to `admin.dashboard` (admin/super/secretary),
   which renders `Graduation/AdminDashboard` with the 9-state breakdown, the 5
   queue sizes, the totals, and the quick links.
5. **Status breakdown is complete and correct.** *When* the admin dashboard
   renders with students spread across statuses, *then* `status_breakdown` has
   **exactly 9** rows in `step()` order, each with the right per-state `count`
   (empty states show 0), `label_key = status.labelKey()`, and `color =
   status.color()`.
6. **Queue sizes equal their buckets.** *When* the admin dashboard renders, *then*
   each `queues[]` count equals the matching `status_breakdown` bucket (form_b ↔
   `FormBReview`, documents ↔ `AnnexIiiPending`, jury ↔ `PaymentPending`, ceremony
   ↔ `JuryAssigned`, graduation ↔ `CeremonyScheduled`) and each `route` opens the
   real queue index.
7. **Totals are derived, not re-queried.** *When* the admin dashboard renders,
   *then* `totals.students` = sum of the 9 buckets, `totals.graduates` = the
   `graduated` bucket, `totals.in_progress` = students − graduates — all from the
   **single** grouped-count query (no extra status query; no N+1).
8. **Single grouped query (no N+1).** *When* the admin dashboard request runs,
   *then* the status histogram is produced by **exactly one** `group by status`
   aggregate query (falsifiable via query logging).
9. **Demo admin dashboard counts ONLY demo students (CRITICAL).** *When* a demo
   admin session views `admin.dashboard` while real students and another demo
   session's students exist, *then* every count/total reflects **only that demo
   session's** students — never real, never another session's — because the counts
   run through the DemoScope and the controller does **not** bypass it.
10. **Real admin dashboard counts ONLY real students.** *When* a real admin views
    `admin.dashboard` while demo students exist, *then* every count excludes every
    demo-tagged student (symmetric scope).
11. **Role gating.** *When* a guest hits either dashboard, *then* they are
    redirected to `login`; *when* a student hits `admin.dashboard`, or a staff
    user hits `student.dashboard`, or an `assistant_secretary`/`school_services`
    user hits `admin.dashboard`, *then* the response is **403**.
12. **Old targets stay reachable.** *When* `student.status` or
    `admin.graduation.review` is requested directly (by name/URL), *then* it still
    resolves 200 for the right role — the landing re-point did not remove them.
13. **Read-only.** *When* either dashboard is rendered, *then* **no** database
    write, status transition, event, or folio mint occurs (GET-only, no Action).
14. **Theme + locale + a11y.** *When* either dashboard renders, *then* it honours
    dark/light + ES/EN via `useLocale`, exposes one `<h1>`, a `#main` landmark,
    semantic count lists, non-color state cues, and real links/CTAs (WCAG 2.2 AA).
15. **Prop contract locked.** *When* the prop-contract tests run, *then* the
    controller payloads match the page interfaces exactly (snake_case; `status`
    serialised as the enum **value** string; the 9-row fixed-shape breakdown), so
    a future controller/page drift fails CI.

## 4. Success criteria (definition of done for this slice)

- `composer format` (Pint) clean, `composer analyse` (Larastan **level 9/10**)
  green, `composer test` (Pest: arch + the new Feature/contract tests on
  PostgreSQL 18) green.
- `php artisan typescript:transform` still emits cleanly (no new `#[TypeScript]`
  DTO/enum is added; existing types unchanged).
- The two routes appear in `php artisan route:list` with the names/middleware of
  §2.1; the post-login redirect lands students on `student.dashboard` and
  admin/super/secretary on `admin.dashboard`.
- A recruiter (demo) and a real user both: log in → land on a working dashboard →
  see their pipeline/system overview → click a CTA/quick-link into a real screen.
- **Security gate (falsifiable):** `DemoAdminDashboardIsolationTest` passes —
  proving the demo admin dashboard counts only demo students and the real admin
  dashboard counts only real students (DemoScope isolation extends to the
  dashboard).
- **Performance gate (falsifiable):** `AdminDashboardQueryCountTest` passes —
  proving the status histogram is one grouped query (no N+1).
- No real PII; `.gitignore` still blocks db/secrets.

## 5. Constitution (§No-negociables) this slice must satisfy

`declare(strict_types=1)` everywhere · explicit return types · `final` controllers
· anemic controllers (≤15 lines, render-only, snake_case props shaped with
`->only()`/explicit array) · named routes / no closures · **read-only: no Action,
no migration, no DTO, no domain class** (nothing is validated/submitted/mutated;
the Spatie-Data-DTO rule is N/A because there is no input to validate) · backed
enum reuse (`GraduationStatus`, `UserRole`) — `status` serialised as the enum
**value** string in props · the admin counts run on the **DemoScope-scoped**
model — **never** `withoutGlobalScope(DemoScope::class)` (would leak real data
into a demo dashboard) · the status histogram is **one** grouped-count query (no
N+1) · Domain ↛ `Illuminate\Http` (no domain file is added; the controllers are
in `App\Http`) · authorization stays in HTTP middleware (`EnsureRole` + `demo`),
never the domain · `RoleLandingRoute` stays an **exhaustive** match over the
6-role enum · Inertia props snake_case + a Pest prop-contract test per page · the
generated TS enum is **type-only** imported (compare raw value strings cast to the
type — never value-imported) · dark/light + ES/EN on both pages, WCAG 2.2 AA ·
frontend copy in `resources/locales/{es,en}.json`; **no** new PHP `__()` key (the
controllers flash nothing) · Pest with arch tests on PostgreSQL (never SQLite) ·
bigint ids (ADR-001).

## 6. Open questions for the gate

- **A. Admin controller namespace.** SPEC row 1626 names
  `Admin\DashboardController`, but slices 001-004 put the staff queue controllers
  under `App\Http\Controllers\Graduation\`. **Recommend: follow the established
  convention** → `Graduation\AdminDashboardController` + page
  `Graduation/AdminDashboard.tsx`. (If the gate prefers the SPEC literal, use
  `Admin\DashboardController` + `Admin/Dashboard.tsx` — purely a path choice; the
  routes/props/tests are identical.)
- **B. View DTO?** A `Spatie\LaravelData` view-DTO for the dashboard payloads, or
  a plain snake_case array (as the existing queue controllers do)? **Recommend:
  plain array** — nothing is validated or type-shared to a form; a DTO adds
  ceremony without payoff. (If the gate wants the typed contract, a tiny
  `#[TypeScript]` `StudentDashboardData` / `AdminDashboardData` `Data` object is
  acceptable but must keep snake_case mapping + the same prop shapes.)
- **C. Ceremony "ready to graduate" precision.** The "graduation" queue count uses
  the `CeremonyScheduled` bucket (= the queue's row count). A precise "ceremony
  date passed" count needs a 2nd query. **Recommend: bucket now, defer the
  refinement** (preserves the single-query guarantee).
- **D. Charts.** Ship count tiles only this slice; charts in the analytics slice?
  **Recommend: tiles only, defer charts** (SPEC Phase 4 / ANALYTICS-01).
- **E. "Back to dashboard" nav on task screens.** Add a dashboard link to the
  existing task-screen headers? **Recommend: OUT this slice** (minimal blast
  radius; can follow later).
- **F. Assistant-secretary / school-services landing.** They land on `landing`
  (no dashboard gated to them). Confirm leaving them as-is (no dashboard route
  for those two roles in this slice). **Recommend: leave as-is.**
