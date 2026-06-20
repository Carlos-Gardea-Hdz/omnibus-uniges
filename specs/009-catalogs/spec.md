# Spec 009 — Academic catalog CRUD (staff management of the reference data the pipeline depends on)

> **Phase:** Specify (the WHAT and WHY, not the HOW).
> **SSOT:** `SPEC.md` §3.2 Academic Domain (Departments, Programs, StudyPlans,
> GraduationTypes, Professors, RequiredDocuments — the reference catalogs the
> 9-state graduation pipeline reads); §3.1 RBAC; §7.3 admin routes. This is the
> **LAST UNIGES slice** — it closes the loop by letting staff *manage* the
> reference data every prior slice only *consumed*.
> **Builds on (reuse VERBATIM — do NOT duplicate or invent):**
> - **The six existing catalog tables + models + factories** (slices 001/002
>   foundation): `Department`, `Program`, `Professor`, `GraduationType`,
>   `StudyPlan`, `RequiredDocument` in `app/Domain/Academic/Models/`, their
>   migrations (`create_{departments,programs,professors,graduation_types,
>   study_plans,required_documents,graduation_type_required_document}_table`),
>   their **`$fillable`**, **`SoftDeletes`** (all but `RequiredDocument` — see
>   §2.8 D-SOFTDELETE), **`unique`** code/email columns, **restrict** FKs, and the
>   `graduation_type_required_document` pivot wired both ways
>   (`GraduationType::requiredDocuments()` / `RequiredDocument::graduationTypes()`).
>   **No migration is expected** — the schema exists (see §2.8 D-NOMIGRATION).
> - **The established mutating vertical** (slices 001-004): the
>   **Action + Spatie-Data-DTO + anemic-controller** triad —
>   `SubmitFormBAction` / `SubmitFormBData` / `Student\FormBController`,
>   `FormBReviewController@approve/reject` (the redirect-back-with-flash pattern),
>   `DB::transaction` on multi-write Actions, `__()` flash keys in
>   `lang/{es,en}.json`.
> - **The auth + demo guards** (slices 005/006): **`EnsureRole`** (the `role:`
>   alias, value-matched against `UserRole`), the **`['auth','demo','role:…']`**
>   route groups in `routes/web.php`, **`DemoSessionMiddleware`** with its
>   **`DESTRUCTIVE_ROUTE_NAMES`** write-guard list (the documented extension point),
>   the **`DemoPreset`** enum (presets = 4 students + `personal`→
>   `AssistantSecretary` + `admin`→`Admin`; **NO preset is `super_admin`** — the
>   load-bearing fact behind the security gate, §2.6), `RoleLandingRoute`.
> - **The staff table/page chrome** (slices 007/008): `Graduation/Review.tsx` (the
>   semantic `<table>` + accessible reject **modal** + `useForm` + `FormError`
>   pattern), `Graduation/AdminDashboard.tsx` (header + `LanguageSwitcher` +
>   `<DemoBanner />` + `#main` + one `<h1>` + quick-link cards — the hub model),
>   the form primitives `Components/form/{TextField,SelectField,FormError}`,
>   `useLocale().t` (frontend copy in `resources/locales/{es,en}.json`).
>
> **This slice mirrors the slice-001/004 mutating conventions VERBATIM:** Action +
> Spatie Data DTO + anemic controller (≤15 lines), `DB::transaction` on the writes,
> named routes / no closures, web validation = **302 + session errors** (never 422),
> a Pest Inertia prop-contract test per page, role-gating + demo-block tests, arch
> tests, Pest on PostgreSQL 18.
>
> **Status:** DRAFT — awaiting human review gate before `/sdd-plan`.

## 1. Problem & why

Every prior slice (Form B, documents, jury, ceremony, reports) *reads* the six
Academic catalogs but **nobody can create, edit or remove a catalog row** through
the app. Today the only way to add a `Program`, retire a `GraduationType`, fix a
`Professor`'s misspelled name, or change which documents a graduation type requires
is a seeder or a raw SQL edit. SPEC §3.2 calls for staff-managed reference data;
this slice delivers the **CRUD surface** that closes the UNIGES loop.

The catalogs are **shared baseline reference data**, NOT pipeline data:

- A `Student`/`StudentDocument`/`JuryAssignment` is **DemoScope-scoped** — a demo
  sandbox owns its own rows. **Catalogs are NOT scoped** (spec 008 §2.2 stated this
  explicitly: "`Program`/`GraduationType`/`Professor` are catalogs and are NOT
  demo-scoped — shared reference data"). Both a real coordinator and a demo
  coordinator see, and the whole pipeline joins against, the **same** catalog rows.

That sharing is exactly why catalog mutation is the **#1 risk of this slice**: a
demo visitor who could create/rename/delete a catalog row would **corrupt the
shared baseline for every other user and every other demo session** — there is no
sandbox to contain the damage (the rows are global). So the central, falsifiable
requirement is: **a demo session must NOT be able to mutate any catalog**, enforced
two ways (a `super_admin`-only gate the demo presets can never satisfy, **and** the
mutation routes added to the demo write-guard list).

The second risk is **referential integrity**: catalog rows are referenced by
**restrict** FKs (a `Program` by `students.program_id` and `study_plans.program_id`;
a `GraduationType` by `students.graduation_type_id`; a `StudyPlan` by
`students.study_plan_id`; a `Department` by `programs.department_id`; a `Professor`
by `jury_assignments.{president,secretary,vocal}_professor_id`). Deleting a row a
student/plan/jury still points at must fail **gracefully** (302 + a friendly "in
use" error), **never a 500** (the established graceful-guard convention — illegal
state transitions already render a 302, bootstrap/app.php).

This slice is the **Academic CRUD vertical**, and the first mutating code inside
`app/Domain/Academic/` (currently only `Models/` exist; `Actions/`/`Data/`/`Enums/`
are `.gitkeep` scaffolds).

## 2. Scope

### 2.1 In scope — the six catalogs + the pivot

CRUD (`index`, `create`-via-modal-or-route, `store`, `update`, `destroy`) for all
six catalogs, **plus** managing the `graduation_type ↔ required_document` pivot
(which required documents a graduation type demands). Per catalog:

| catalog | unique col(s) | other fields | FK (exists) | referenced-by restrict FKs (delete-in-use risk) |
|---------|---------------|--------------|-------------|--------------------------------------------------|
| `departments` | `code` | `name` | — | `programs.department_id` |
| `programs` | `code` | `name` | `department_id` | `students.program_id`, `study_plans.program_id` |
| `professors` | `email` | `first_name`, `last_name`, `mother_last_name?` | — | `jury_assignments.{president,secretary,vocal}_professor_id` (restrict); `students.advisor_id`/`jury…substitute` are **nullOnDelete** → NOT a block |
| `graduation_types` | `code` | `name`, `requires_advisor` (bool) | — | `students.graduation_type_id`; pivot rows **cascade** (auto-removed, NOT a block) |
| `study_plans` | `code` | `name` | `program_id` | `students.study_plan_id` |
| `required_documents` | — (no unique col) | `name`, `description?`, `allowed_mimes`, `max_size_kb` | — | `student_documents.required_document_id` (restrict); pivot rows **cascade** (NOT a block) |
| **pivot** `graduation_type_required_document` | composite PK | — | — | managed via `sync()` on the GraduationType edit form; not independently deletable |

**Sync-the-required-documents** of a graduation type is part of the
`graduation_types` edit surface: a multi-select of `required_documents` whose
submission `sync()`s the pivot (cascade pivot, so no restrict concern).

### 2.2 In scope — backend (mutating)

**Per catalog (6×), three `final` transactional Actions** in
`app/Domain/Academic/Actions/` (`Create{Catalog}Action`, `Update{Catalog}Action`,
`Delete{Catalog}Action`), each `handle(...)` one business operation, wrapped in
`DB::transaction` when it touches more than one table (the `graduation_types`
create/update touches the pivot → transactional; the single-table catalogs still
use `DB::transaction` for symmetry and future-proofing, matching the established
Action convention). Signatures (§Contract is authoritative):

- `Create{Catalog}Action::handle({Catalog}Data $data): {Model}` — `Model::create($data->toArray())`-equivalent (explicit `fill`/`create` over `$fillable`); the GraduationType variant also `sync()`s the pivot from `$data->required_document_ids`.
- `Update{Catalog}Action::handle({Model} $model, {Catalog}Data $data): {Model}` — `$model->fill(...)->save()`; GraduationType also re-`sync()`s the pivot.
- `Delete{Catalog}Action::handle({Model} $model): void` — `$model->delete()` (a SoftDelete for the five soft-deletable catalogs; a hard delete for `RequiredDocument`, §2.8 D-SOFTDELETE) **inside a `try`**, catching the **restrict-FK `Illuminate\Database\QueryException`** and re-throwing a domain **`CatalogInUseException`** (new, `app/Domain/Academic/Exceptions/`) — surfaced as a graceful **302 + friendly error**, NEVER a 500 (§2.5, §2.8 D-DELETE-GUARD).

> **Why not soft-delete sidesteps the restrict FK?** A `SoftDeletes` `->delete()`
> issues an `UPDATE deleted_at`, which does **not** trip the DB restrict FK — the
> row physically stays, so the pipeline's `belongsTo` still resolves it and the
> child rows are never orphaned. **But** silently soft-deleting a `Program` that
> 40 students still reference would leave those students pointing at a "deleted"
> program (an invisible-but-referenced ghost) — a data-integrity foot-gun. So the
> Delete Action **pre-checks for live references** and refuses with
> `CatalogInUseException` **before** soft-deleting (the same friendly 302), and
> additionally catches the hard-delete `QueryException` for `RequiredDocument` and
> as a belt-and-braces guard. The pre-check + the catch together guarantee "delete
> a referenced catalog row → graceful 302, never a 500, never an orphan". (§2.8
> D-DELETE-GUARD specifies the exact reference set per catalog.)

**Per catalog, one Spatie Data DTO** in `app/Domain/Academic/Data/`
(`#[TypeScript] final extends Data`), validation = SSOT (no FormRequest, no
`$request->validate()`): `Required`, `Max`, **`Unique` on `code`/`email` ignoring
self on update**, **`Exists` on FK ids** (`department_id`, `program_id`,
`required_document_ids[]`). Exact rules in §Contract.

**Six anemic controllers** (`final extends Controller`, ≤15 lines/method) under
`App\Http\Controllers\Admin\Catalog\` (new HTTP namespace, §2.8 D-NS):
`DepartmentController`, `ProgramController`, `ProfessorController`,
`GraduationTypeController`, `StudyPlanController`, `RequiredDocumentController`.
Each: `index()` (Inertia render — the list + any FK options + the catalog's own
rows), `store({Catalog}Data, {Create}Action)` → redirect-back + flash,
`update({Model}, {Catalog}Data, {Update}Action)` → redirect-back + flash,
`destroy({Model}, {Delete}Action)` → redirect-back + flash. **No `create`/`edit`
GET** — create/edit happen inline (modal/expanding row) on the index page, like the
`Graduation/Review` reject modal (§2.8 D-INLINE). Receive DTO → call Action →
redirect; props via `->only()`/explicit map.

**One catalog hub** controller `Admin\Catalog\CatalogHubController@index` →
`Admin/Catalogs/Index` with six cards linking the six catalog index routes (each a
resolved `route()` URL, no dead-end), mirroring the reporting hub (spec 008 §2.4
`Reporting/Index`).

### 2.3 Routes (named, no closures) — the security gate (CRITICAL)

All catalog routes live in **one new group gated to `role:super_admin` only**:

```php
Route::middleware(['auth', 'demo', 'role:super_admin'])
    ->prefix('admin/catalogs')->name('admin.catalogs.')->group(...);
```

| method | path | name | controller@method |
|--------|------|------|-------------------|
| GET | `/admin/catalogs` | `admin.catalogs.index` | `CatalogHubController@index` |
| GET | `/admin/catalogs/departments` | `admin.catalogs.departments.index` | `DepartmentController@index` |
| POST | `/admin/catalogs/departments` | `admin.catalogs.departments.store` | `DepartmentController@store` |
| PUT | `/admin/catalogs/departments/{department}` | `admin.catalogs.departments.update` | `DepartmentController@update` |
| DELETE | `/admin/catalogs/departments/{department}` | `admin.catalogs.departments.destroy` | `DepartmentController@destroy` |
| … | (identical 4-route shape for `programs`, `professors`, `graduation-types`, `study-plans`, `required-documents`) | | |

Total = 1 hub GET + 6 × (1 index GET + store POST + update PUT + destroy DELETE) =
**25 routes**, all under `['auth','demo','role:super_admin']`.

> **Gate decision (the #1 security control, §2.6, D-GATE):** the catalogs are
> gated to **`role:super_admin` ONLY** — NOT the broader
> `admin,super_admin,secretary` staff group the dashboard/reports use. Rationale:
> (a) catalog mutation is the most privileged operation in the system (it rewrites
> the shared baseline), so it belongs to the top role; (b) **decisively, no
> `DemoPreset` mints a `super_admin`** (the `admin` preset → `UserRole::Admin`, the
> `personal` preset → `AssistantSecretary`, the four student presets → `Student`),
> so **a demo session can never even reach a catalog route** — `EnsureRole` returns
> 403 before any controller runs. This is the primary, structural demo block.
> (`admin`/`secretary` real staff get 403 too — acceptable: only the super_admin
> manages reference data; if the gate wants `admin` included later, it can be
> widened, but then the demo `admin` preset WOULD reach the routes and the
> §2.3-DESTRUCTIVE block below becomes load-bearing rather than redundant.)

> **Defense-in-depth demo block (D-DEMOBLOCK):** every catalog **mutation** route
> (the 6×3 = **18** `store`/`update`/`destroy` names) is ALSO added to
> `DemoSessionMiddleware::DESTRUCTIVE_ROUTE_NAMES`, so that even if the gate is ever
> widened to include the demo-reachable `admin` role, a demo session is still
> blocked with a graceful **302 + `__('demo.blocked')`** and **no mutation occurs**.
> The index/hub GETs are read-only and are NOT added (a demo super_admin doesn't
> exist today, but a future demo `admin` viewing the catalog read-only is harmless).
> Both controls are independently tested (§3 scenarios 9-10).

### 2.4 Inertia prop shapes (snake_case — the page prop contract)

Each controller payload and its `.tsx` page interface must match EXACTLY, locked by
a Pest Inertia prop-contract test per page (memory rule). `requires_advisor`
serialises as a bool; ids as numbers; `code`/`email` as strings.

#### `Admin/Catalogs/Index` (the hub)
```
{
  catalogs: Array<{
    key: string,             // "departments" | "programs" | "professors" | "graduation_types" | "study_plans" | "required_documents"
    title_key: string,       // "catalogs.departments.title"
    description_key: string,  // "catalogs.departments.description"
    count: number,           // row count for that catalog (one trivial count() each — see §2.8 D-HUBCOUNT)
    route: string,           // resolved index URL (no dead-end card)
  }>,                        // EXACTLY 6 rows, fixed order
}
```

#### `Admin/Catalogs/Departments`
```
{
  departments: Array<{ id: number, code: string, name: string }>,  // ordered by code
}
```

#### `Admin/Catalogs/Programs`
```
{
  programs: Array<{ id: number, code: string, name: string, department_id: number, department_name: string }>, // department eager-loaded for display
  department_options: Array<{ id: number, name: string }>,  // the FK <select>, ordered by name
}
```

#### `Admin/Catalogs/Professors`
```
{
  professors: Array<{ id: number, first_name: string, last_name: string, mother_last_name: string | null, email: string, full_name: string }>, // ordered by last_name
}
```

#### `Admin/Catalogs/GraduationTypes`
```
{
  graduation_types: Array<{
    id: number, code: string, name: string, requires_advisor: boolean,
    required_document_ids: Array<number>,   // the synced pivot ids (for the multi-select)
  }>,                                        // ordered by code
  required_document_options: Array<{ id: number, name: string }>, // the multi-select source, ordered by name
}
```

#### `Admin/Catalogs/StudyPlans`
```
{
  study_plans: Array<{ id: number, code: string, name: string, program_id: number, program_name: string }>, // program eager-loaded
  program_options: Array<{ id: number, name: string }>,  // the FK <select>, ordered by name
}
```

#### `Admin/Catalogs/RequiredDocuments`
```
{
  required_documents: Array<{ id: number, name: string, description: string | null, allowed_mimes: string, max_size_kb: number }>, // ordered by name
}
```

### 2.5 Graceful delete-in-use (the #2 risk — falsifiable)

Deleting a catalog row that a restrict FK (or, for the soft-deletable catalogs, any
live reference) still points at:

1. The `Delete{Catalog}Action` **pre-checks** the reference set (§2.8 D-DELETE-GUARD)
   and, if any live child exists, throws **`CatalogInUseException`** (a domain
   exception, `app/Domain/Academic/Exceptions/`) **before** any delete.
2. As a belt-and-braces guard, the `->delete()` is wrapped in a `try/catch
   (QueryException $e)` that, on a foreign-key-violation SQLSTATE (23503),
   re-throws the same `CatalogInUseException`.
3. `bootstrap/app.php` renders `CatalogInUseException` like the existing
   `InvalidStatusTransitionException`: **302 redirect-back with a session error**
   (`->withErrors(['catalog' => $e->getMessage()])`) for web, 422 JSON for API
   clients — **never an unhandled 500**.

The friendly message resolves `__('catalogs.error.in_use')` (both lang files).
Falsifiable: deleting a `Program` that a `Student` references → 302 + the in-use
error, the row still present, **no 500** (§3 scenario 8).

### 2.6 Security (the #1 risk — falsifiable)

Two independent controls, both tested:

- **Structural gate (primary):** `role:super_admin` on every catalog route. No
  `DemoPreset` mints a `super_admin`, so a demo session is 403'd before any
  controller runs. Test: a demo session (provisioned via the `admin`/`personal`
  presets) GETting/POSTing any catalog route → **403**, no mutation.
- **Write-guard (defense-in-depth):** the 18 mutation route names in
  `DemoSessionMiddleware::DESTRUCTIVE_ROUTE_NAMES`. Test (forces the guard to fire
  even if the gate were widened): a demo session **carrying the `admin` role** (or
  the test simulating a demo super_admin) hitting a catalog mutation route →
  graceful **302 + `demo.blocked`**, `assertDatabaseCount` unchanged. (See §3
  scenario 10 for the exact construction.)

### 2.7 In scope — frontend (7 Inertia pages)

All pages: `final` React function components, theme + locale aware (`useLocale`),
WCAG 2.2 AA (one `<h1>`, `#main` landmark, semantic `<table>`, accessible
create/edit **modal** reusing the `Graduation/Review` dialog pattern — focus on
open, Escape-to-close, `role="dialog"`/`aria-modal`, `FormError`), mount the chrome
(`Head` + header + `LanguageSwitcher` + `<DemoBanner />`), reuse
`Components/form/{TextField,SelectField,FormError}`. `useForm` posts via Inertia
(`router.put`/`router.delete` for update/destroy; **web validation surfaces as
session errors**, mapped to `errors` on the form). `requires_advisor` is a
checkbox; `required_document_ids` a multi-select; `allowed_mimes` a text input
(CSV). A delete button opens a confirm dialog; on the in-use 302 the page shows the
flashed error (never a crash).

1. `Admin/Catalogs/Index.tsx` — 6 catalog cards (`t(title_key)` + count +
   `<Link href={route}>`), responsive grid (mirrors `Reporting/Index`).
2-7. `Admin/Catalogs/{Departments,Programs,Professors,GraduationTypes,StudyPlans,RequiredDocuments}.tsx`
   — a "New {catalog}" button opening the create modal; a semantic `<table>` of
   rows; per row an Edit button (opens the modal pre-filled) + a Delete button
   (confirm → `router.delete`). FK selects (`program_options`/`department_options`)
   and the GraduationType multi-select are bound from the page props.

**(Recommended) one "Catalogs" nav link** added — but only for `super_admin`. The
`AdminDashboard.tsx` header is shared by `admin`/`secretary`/`super_admin`, so the
link is rendered **conditionally** on the shared `auth.user.role === 'super_admin'`
Inertia prop (it already ships in `HandleInertiaRequests`; confirm in plan) so
non-super-admins never see a link they'd be 403'd on (the ux-flow "no link to a
screen you can't reach" rule). (Open question E.)

### 2.8 Deviations / decisions flagged for the gate

- **D-NOMIGRATION.** The six catalog tables + the pivot **already exist** (slice
  001/002 foundation, verified). This slice adds **NO migration**. The only schema
  question is `required_documents` lacking a unique column and lacking
  `SoftDeletes` (§D-SOFTDELETE) — both are pre-existing and **left as-is** (no
  genuinely-missing column is needed for CRUD). If the gate wants a unique
  `required_documents.name` or to add `SoftDeletes` there, that is a **separate,
  justified, reversible migration** — flagged, NOT bundled silently.
- **D-SOFTDELETE.** Five catalogs use `SoftDeletes`; **`RequiredDocument` does
  NOT** (its migration has no `softDeletes()`, no `deleted_at`). So its `Delete`
  Action does a **hard delete**, guarded by the restrict FK on
  `student_documents.required_document_id` (+ the cascade pivot auto-cleans). The
  five soft-deletable catalogs do a **soft delete after the live-reference
  pre-check** (so a referenced row is refused, never soft-deleted into a
  referenced-ghost). Do NOT add `SoftDeletes` to `RequiredDocument` in this slice
  (no migration — D-NOMIGRATION); the asymmetry is documented and tested.
- **D-DELETE-GUARD.** The exact live-reference set the `Delete` Action pre-checks
  (and the FKs the `QueryException` catch backstops):
  - `Department` → `programs` where `department_id` (restrict).
  - `Program` → `students` where `program_id` **OR** `study_plans` where
    `program_id` (both restrict).
  - `Professor` → `jury_assignments` where `president|secretary|vocal_professor_id`
    (restrict). `students.advisor_id` + `jury…substitute_professor_id` are
    **nullOnDelete** → NOT a block (deleting a professor used only as advisor/
    substitute nulls those columns, no error — that is correct and tested).
  - `GraduationType` → `students` where `graduation_type_id` (restrict). The pivot
    rows **cascade** (auto-removed) → NOT a block.
  - `StudyPlan` → `students` where `study_plan_id` (restrict).
  - `RequiredDocument` → `student_documents` where `required_document_id`
    (restrict). The pivot rows **cascade** → NOT a block.
  - The pre-check uses `withoutGlobalScopes()`-free `exists()` on the **un**scoped
    child query? **No** — `Student`/`StudentDocument`/`JuryAssignment` ARE
    DemoScope-scoped, but the catalog Actions run only under a `super_admin` (never
    a demo session, D-GATE), so `DemoContext` is unset and the scope is a no-op:
    the pre-check sees ALL real children. **The Action MUST NOT call
    `withoutGlobalScopes()`** (memory/spec-008 rule) — it relies on the
    no-demo-context fact. (Falsifiable: the delete-in-use test runs as a real
    super_admin with real children present.)
- **D-GATE.** `role:super_admin` only (not the staff trio). The decisive reason is
  demo-safety (no preset is super_admin). (§2.6, §2.3.)
- **D-DEMOBLOCK.** The 18 mutation routes added to `DESTRUCTIVE_ROUTE_NAMES`
  (defense-in-depth). (§2.3, §2.6.)
- **D-NS.** Controllers under `App\Http\Controllers\Admin\Catalog\*`; pages under
  `resources/js/Pages/Admin/Catalogs/*` (domain-aligned, mirrors the
  `Reporting\`/`Reporting/` precedent). (Open question A.)
- **D-INLINE.** No `create`/`edit` GET routes — create/edit are **inline modals**
  on each index page (the `Graduation/Review` reject-modal pattern), keeping the
  route surface to 4 per catalog (index/store/update/destroy) and the controllers
  anemic. (Open question B.)
- **D-DTO-WEB.** Spatie Data DTOs are the validation SSOT; web validation failures
  surface as **302 + session errors** (the controller method-signature injection,
  the slice-001 convention — NEVER 422 for the web path). The unique-ignore-self
  rule uses Spatie's `Unique(...)->ignore($id)` driven by the route-bound model on
  update (§Contract). (Memory rule.)
- **D-HUBCOUNT.** The hub renders a `count()` per catalog (6 trivial scoped-but-
  unscoped `count()` queries — catalogs aren't demo-scoped, so cheap and exact).
  Acceptable in an anemic read-only hub controller (vs. spec-008's query-free
  reporting hub — here a count is genuinely useful and trivial). (Open question C.)
- **D-PIVOT.** The `graduation_type ↔ required_document` pivot is managed **inside**
  the GraduationType create/update (a `required_document_ids[]` field `sync()`'d in
  the Action), NOT a separate pivot CRUD. The pivot is cascade, so no restrict
  concern. (Open question D.)

### 2.9 DEFERRED (explicitly OUT)

- **Excel bulk import** of catalogs (`maatwebsite/excel`, SPEC key packages) — a
  later slice; this slice ships row-by-row CRUD only.
- **Audit log / change history** of catalog edits — not in SPEC scope for UNIGES;
  the `updated_at` timestamp suffices.
- **Restore of soft-deleted catalogs** (an `index?trashed=1` + restore route) — the
  five soft-deletable catalogs keep their `deleted_at`, but a restore UI is OUT
  (recoverable via the DB if ever needed). (Open question F — recommend defer.)
- **Reordering / drag-sort** of catalog rows — ordering is by `code`/`name`; no
  manual sort.
- **A migration adding a unique `required_documents.name` or `SoftDeletes`** — only
  if the gate explicitly wants it (D-NOMIGRATION/D-SOFTDELETE); not bundled.

## 3. Acceptance scenarios (When… Then)

1. **Catalog hub lists the six catalogs.** *When* a `super_admin` GETs
   `admin.catalogs.index`, *then* `Admin/Catalogs/Index` renders exactly 6 cards,
   each a real `<Link>` to its catalog index route (no dead-end), each with a row
   count.
2. **Create a catalog row (happy path).** *When* a `super_admin` POSTs valid data to
   any `…store` route (e.g. a `Department` `{code:'DEP-X1', name:'…'}`), *then* the
   row is created, the response is a 302 redirect-back with the `catalogs.created`
   flash, and `assertDatabaseHas` confirms the row.
3. **Update a catalog row (happy path, unique-ignore-self).** *When* a `super_admin`
   PUTs valid data to a `…update` route keeping the row's **own** `code`/`email`,
   *then* the unique rule passes (ignores self), the row is updated, 302 + the
   `catalogs.updated` flash. Changing `code` to a value **another** row already owns
   → 302 + `assertSessionHasErrors('code')` (no 500, no duplicate).
4. **Validation rejects bad input (302, not 422).** *When* a `super_admin` POSTs a
   missing `name`, an over-`Max` field, a non-existent FK `department_id`, or a
   duplicate `code`, *then* the Spatie DTO rejects it with **302 +
   `assertSessionHasErrors([...])`** (web convention), and **no** row is created.
5. **Delete a catalog row not in use (happy path).** *When* a `super_admin` DELETEs
   a catalog row no student/plan/jury references, *then* it is soft-deleted (or hard-
   deleted for `RequiredDocument`), 302 + `catalogs.deleted` flash;
   `assertSoftDeleted` / `assertDatabaseMissing` confirms.
6. **GraduationType required-documents pivot syncs.** *When* a `super_admin`
   creates/updates a `GraduationType` with `required_document_ids:[a,b]`, *then* the
   `graduation_type_required_document` pivot holds exactly `{a,b}` for that type
   (`sync` semantics — removing one on a later update removes its pivot row); the
   page prop `required_document_ids` reflects the synced set.
7. **Single graceful path; FK `exists` enforced.** *When* a `store`/`update` carries
   a `program_id`/`department_id`/`required_document_ids[]` that does not exist,
   *then* 302 + `assertSessionHasErrors` on that field — never a DB-integrity 500.
8. **Delete-in-use fails gracefully (CRITICAL, #2 risk).** *When* a `super_admin`
   DELETEs a `Program` a `Student` (or a `StudyPlan`) still references — or a
   `Professor` seated on a `JuryAssignment`, a `GraduationType`/`StudyPlan` a
   student references, a `Department` a program references, a `RequiredDocument` a
   `StudentDocument` references — *then* the response is a **302 redirect-back with
   the `catalogs.error.in_use` session error**, the row is **still present** (not
   deleted), and the status is **NOT 500**. Deleting a `Professor` used only as an
   advisor/substitute (nullOnDelete) succeeds and nulls those columns.
9. **Role gating.** *When* a guest hits any catalog route, *then* redirect to
   `login` (302). *When* a `student`, `assistant_secretary`, `school_services`,
   **`secretary` or `admin`** user hits any catalog route, *then* **403** (only
   `super_admin` passes). `super_admin` → 200/302 as appropriate.
10. **A demo session cannot mutate a catalog (CRITICAL, #1 risk — falsifiable, two
    ways).** *(a)* *When* a demo session (provisioned via any preset — none is
    super_admin) GETs/POSTs any catalog route, *then* `EnsureRole` returns **403**,
    no controller runs, no row changes. *(b)* *When* the demo write-guard is forced
    to fire (a demo session reaching a catalog mutation route — constructed in the
    test by giving the demo user the `admin` role and asserting the
    `DESTRUCTIVE_ROUTE_NAMES` membership), *then* `DemoSessionMiddleware` returns a
    graceful **302 + `demo.blocked`** and `assertDatabaseCount` for the catalog is
    unchanged — **no mutation occurs**.
11. **Restrict-FK delete never 500s under any catalog.** *When* any of the six
    `destroy` routes is hit on an in-use row, *then* the rendered exception is the
    domain `CatalogInUseException` (302/422), proven for every catalog with a live
    reference — there is no path to an unhandled `QueryException`/500.
12. **Theme + locale + a11y.** *When* any catalog page renders, *then* it honours
    dark/light + ES/EN via `useLocale`, exposes one `<h1>`, a `#main` landmark,
    semantic `<table>`s, an accessible create/edit modal (focus-trap, Escape,
    `aria-modal`), labelled inputs (`TextField`/`SelectField`), and real `<Link>`s
    (WCAG 2.2 AA).
13. **Prop contract locked.** *When* the prop-contract tests run, *then* each
    controller payload matches its page interface exactly (snake_case;
    `requires_advisor` bool; `required_document_ids` number[]; the FK option lists),
    so a future controller/page drift fails CI.
14. **Anemic + transactional + named.** *When* the arch/structure tests run, *then*
    each controller method is ≤15 lines and touches only DTO + Action + `route()` +
    `Inertia::render` (no direct Eloquent write); each multi-table Action wraps its
    writes in `DB::transaction`; every route is named, no closures; the Academic
    Actions/Data/Exceptions are `final`; the new DTOs are `#[TypeScript]`.

## 4. Success criteria (definition of done for this slice)

- `composer format` (Pint) clean, `composer analyse` (Larastan **level 9/10**)
  green, `composer test` (Pest: arch + the new Feature/contract/CRUD/security tests
  on PostgreSQL 18) green.
- `php artisan typescript:transform` emits the six `#[TypeScript]` catalog DTOs into
  `resources/js/types/generated.d.ts`.
- The **25 routes** appear in `php artisan route:list` with the names/middleware of
  §2.3, all under `['auth','demo','role:super_admin']`; the 18 mutation names appear
  in `DemoSessionMiddleware::DESTRUCTIVE_ROUTE_NAMES`.
- A `super_admin` can: open the hub → open each catalog → create/edit/delete a row →
  sync a graduation type's required documents — with no dead-end, friendly errors on
  duplicate codes and in-use deletes, and zero 500s.
- **Security gate (falsifiable):** the demo-cannot-mutate tests (both the 403 gate
  and the `DESTRUCTIVE_ROUTE_NAMES` write-guard) pass.
- **Integrity gate (falsifiable):** the delete-in-use tests pass for every catalog
  (302 + in-use error, row preserved, never 500).
- **No migration** added (D-NOMIGRATION) — `php artisan migrate:fresh` is unchanged.
- No real PII in factories (the existing fictional Academic factories are reused);
  `.gitignore` still blocks db/secrets.

## 5. Constitution (§No-negociables) this slice must satisfy

`declare(strict_types=1)` everywhere · explicit return types · `final` controllers +
`final` Academic Actions + `final readonly` is N/A for Actions (stateless) but the
DTOs extend `Spatie\LaravelData\Data` (`#[TypeScript] final`) · backed-enum reuse
(`requires_advisor` is a bool, no new enum) · **Spatie Data DTOs only** (no
FormRequest, no `$request->validate()`); **web validation = 302 + session errors,
never 422** · Actions one operation + **`DB::transaction`** on the multi-table
(GraduationType+pivot) writes · anemic controllers (≤15 lines, DTO → Action →
redirect/`render`, snake_case props via `->only()`/explicit map) · named routes / no
closures · **authorization in HTTP middleware** (`EnsureRole role:super_admin` +
`demo`), never the domain · Academic domain ↛ `Illuminate\Http` and ↛
`App\Domain\Identity` · **never `withoutGlobalScopes()`** in the delete-pre-check
(relies on the no-demo-context fact under the super_admin gate) · the restrict-FK
delete surfaces a **graceful 302** via `CatalogInUseException`, **never a 500** ·
every `__()` flash/error key exists in **BOTH** `lang/es.json` + `lang/en.json` (+ a
resolution test) · Inertia props snake_case + a Pest prop-contract test per page ·
the generated TS enum is **type-only** imported · dark/light + ES/EN on all 7 pages,
WCAG 2.2 AA · frontend copy in `resources/locales/{es,en}.json` · Pest with arch
tests on PostgreSQL (never SQLite) · bigint ids (ADR-001) · **no migration** unless a
genuinely-missing column is justified (none is).

## 6. Open questions for the gate

- **A. HTTP namespace + page folder.** `App\Http\Controllers\Admin\Catalog\*` +
  `resources/js/Pages/Admin/Catalogs/*` (domain-aligned, mirrors `Reporting\`).
  **Recommend: yes** (D-NS).
- **B. Inline modal vs create/edit GET routes.** Inline create/edit modals on the
  index page (the `Graduation/Review` pattern), no `create`/`edit` GET. **Recommend:
  inline** (fewer routes, anemic controllers, D-INLINE).
- **C. Hub counts.** Render a `count()` per catalog on the hub, or query-free links?
  **Recommend: counts** (trivial, genuinely useful; D-HUBCOUNT).
- **D. Pivot management.** Manage the GraduationType↔RequiredDocument pivot inside
  the GraduationType form (a `required_document_ids[]` `sync()`), not a separate
  pivot CRUD. **Recommend: inside the form** (D-PIVOT).
- **E. Catalogs nav link.** Add a `super_admin`-only "Catalogs" `<Link>` to the
  admin dashboard header (conditional on `auth.user.role==='super_admin'` so
  non-super-admins see no unreachable link). **Recommend: yes** (avoids an orphaned
  hub; the ux-flow rule).
- **F. Soft-delete restore UI.** Ship a restore screen for the five soft-deletable
  catalogs? **Recommend: defer** (DB-recoverable; out of scope).
- **G. `super_admin` only vs include `admin`.** Gate to `super_admin` only (demo-
  safe, no preset reaches it) vs the staff trio. **Recommend: `super_admin` only**
  (D-GATE) — with the `DESTRUCTIVE_ROUTE_NAMES` block as defense-in-depth if it is
  ever widened.
- **H. `RequiredDocument` soft-delete + unique name.** It lacks both. Leave as-is
  (no migration, hard delete guarded by the restrict FK), or add a justified
  migration? **Recommend: leave as-is** (D-NOMIGRATION/D-SOFTDELETE); the restrict
  FK already guards integrity and `name` need not be unique.
