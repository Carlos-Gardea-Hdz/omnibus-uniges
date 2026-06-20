# Plan 009 — Academic catalog CRUD (the HOW)

> **Phase:** Plan. Turns `specs/009-catalogs/spec.md` into the concrete
> architecture / files-to-touch. Gated on `CLAUDE.md` §No-negociables.
> **Read first:** `spec.md` (this folder); `specs/002-documents-domain/{spec,plan}.md`
> (the catalog tables/pivot + the Action+DTO+controller mutating pattern);
> `specs/008-reporting/spec.md` (the staff hub + the `route:super_admin`/demo
> reasoning + the never-`withoutGlobalScopes()` rule).
> **Stack:** Laravel 12 + Inertia 2 + React 19 + TS + PostgreSQL 18 (Sail).
> **Slice nature:** MUTATING CRUD — 6 catalogs × (1 DTO + 3 Actions) + 1
> `CatalogInUseException` + 6 controllers + 1 hub controller + 7 Inertia pages + 25
> routes (super_admin-gated) + 18 demo-blocked names + `catalogs.*` locale + `lang/`
> flash keys + tests. **NO migration** (schema exists). Excel import / restore UI
> DEFERRED.

## 1. Recon — what already exists (verified, do NOT recreate)

- **The six catalog tables + the pivot** exist (migrations verified): `departments`
  (`code` unique, `name`, softDeletes), `programs` (`code` unique, `name`,
  `department_id`→restrict, softDeletes), `professors` (`first_name`, `last_name`,
  `mother_last_name?`, `email` unique, softDeletes), `graduation_types` (`code`
  unique, `name`, `requires_advisor` bool default false, softDeletes), `study_plans`
  (`code` unique, `name`, `program_id`→restrict, softDeletes), `required_documents`
  (`name`, `description?`, `allowed_mimes`, `max_size_kb` default 10240 — **NO
  softDeletes, NO unique column**), `graduation_type_required_document` (composite
  PK, both FKs **cascade**).
- **The six models** in `app/Domain/Academic/Models/` — `final`, `$fillable`,
  `SoftDeletes` (all but `RequiredDocument`), `newFactory()`, `@property` PHPDoc,
  the relations: `Department::programs()`, `Program::{department(),studyPlans()}`,
  `StudyPlan::program()`, `GraduationType::requiredDocuments()` (BelongsToMany on
  `graduation_type_required_document`), `RequiredDocument::graduationTypes()`. The
  pivot is wired both ways. **`requires_advisor` cast `boolean`; `max_size_kb` cast
  `integer`.**
- **The six factories** in `database/factories/` — reuse for tests (factory-hygiene
  already correct: `unique()` only on `code`/`email`, `randomElement` for non-unique
  names). `GraduationTypeFactory::withRequiredDocuments(int $count=2)` attaches pivot
  rows — reuse for the pivot tests.
- **The mutating triad** (slice 001): `SubmitFormBAction` (`__construct` DI +
  `handle(DTO, Model): Model` + `DB::transaction`), `SubmitFormBData`
  (`#[TypeScript] final extends Data`, `#[Required,Max,Exists,Regex]` attributes),
  `Student\FormBController` (anemic; `store(DTO, …Action)` → `redirect()->route()
  ->with('success', __())`), `FormBReviewController@approve/reject` (the
  `redirect()->back()->with('success', __())` shape, route-model-bound `{student}`).
- **`EnsureRole`** (`role:` alias, value-matches `UserRole`; miss → 403),
  **`DemoSessionMiddleware`** (the `DESTRUCTIVE_ROUTE_NAMES` list — currently only
  `admin.graduation.ceremony.graduate`; the documented extension point; a matching
  route → `back()->with('error', __('demo.blocked'))`), **`bootstrap/app.php`** (the
  `role`/`demo` aliases; the `$exceptions->render(InvalidStatusTransitionException…)`
  → 302/422 — the template for `CatalogInUseException`).
- **`DemoPreset`** — presets map to roles `Student`×4, `AssistantSecretary`
  (personal), `Admin` (admin). **NO `super_admin`** → the structural demo gate.
- **`UserFactory`** states: `admin()/superAdmin()/secretary()/student()/
  assistantSecretary()/schoolServices()/demo(sessionId)`. Reuse for role-gating +
  demo tests.
- **Page chrome:** `Graduation/AdminDashboard.tsx` (header + `LanguageSwitcher` +
  `<DemoBanner />` + `#main` + `<h1>` + card grid — the hub model);
  `Graduation/Review.tsx` (semantic `<table>` + accessible reject **modal**:
  focus-on-open, Escape-close, `role="dialog"`/`aria-modal`/`aria-labelledby`,
  `useForm` + `FormError`, approve via `router.post`). Reuse both VERBATIM as the
  catalog list + create/edit-modal template.
- **Form primitives:** `Components/form/TextField` (label/value/onChange/error/
  required/hint, native `type` forwarded), `SelectField` (options
  `{value,label}[]`, placeholder), `FormError`. Reuse.
- **Locale:** `resources/locales/{es,en}.json` (flat dotted; `app.name`,
  `admin.review.*`, `dashboard.admin.*` present — mirror for `catalogs.*`).
  `lang/{es,en}.json` (PHP `__()` flash keys — `form.*`, `admin.review.*`,
  `demo.blocked` present; ADD the `catalogs.*` flash + error keys here).
- **Tests:** `tests/Feature/Reporting/ReportRoleGatingTest.php` (the guest→login
  302, wrong-role→403, right-role→200 matrix with a `->with()` dataset — the exact
  template for `CatalogRoleGatingTest`); `tests/Feature/Graduation/SubmitFormBTest`
  / `ReviewDocumentTest` (the 302 + `assertSessionHasErrors` web-validation
  convention, `actingAs`, `RefreshDatabase` on pgsql). `tests/Arch/
  ArchitectureTest.php` (the `App\Domain` final/strict + cross-domain-isolation
  rules; extend for `App\Domain\Academic\Actions`).
- **`generated.d.ts`** — currently emits `GraduationStatus`, `JuryRole`,
  `DocumentStatus`, `UserRole`, `DemoPreset`, and the existing DTOs. The six catalog
  DTOs will be added.

## 2. Files to touch

### Create — domain DTOs (`app/Domain/Academic/Data/`)
- `DepartmentData.php`, `ProgramData.php`, `ProfessorData.php`,
  `GraduationTypeData.php`, `StudyPlanData.php`, `RequiredDocumentData.php`
  (`#[TypeScript] final extends Data`).

### Create — domain Actions (`app/Domain/Academic/Actions/`) — 18 files
- `Create{Department,Program,Professor,GraduationType,StudyPlan,RequiredDocument}Action.php`
- `Update{…six…}Action.php`
- `Delete{…six…}Action.php`
(each `final`, `handle(...)`, `DB::transaction`; Delete catches/pre-checks → throws
`CatalogInUseException`.)

### Create — domain exception (`app/Domain/Academic/Exceptions/`)
- `CatalogInUseException.php` — `final extends \RuntimeException`; a static
  `forCatalog(string $messageKey): self` or a plain message carrying
  `__('catalogs.error.in_use')`. (Domain ↛ Http: it carries a message string, not an
  HTTP response — the render lives in `bootstrap/app.php`.)

### Create — controllers (`app/Http/Controllers/Admin/Catalog/`) — 7 files
- `CatalogHubController.php` (index)
- `DepartmentController.php`, `ProgramController.php`, `ProfessorController.php`,
  `GraduationTypeController.php`, `StudyPlanController.php`,
  `RequiredDocumentController.php` (each: index/store/update/destroy).

### Create — Inertia pages (`resources/js/Pages/Admin/Catalogs/`) — 7 files
- `Index.tsx` (hub), `Departments.tsx`, `Programs.tsx`, `Professors.tsx`,
  `GraduationTypes.tsx`, `StudyPlans.tsx`, `RequiredDocuments.tsx`.

### Create — tests (`tests/Feature/Academic/`)
- `CatalogHubPagePropsTest.php` — hub: 6 cards, counts, resolved routes.
- `DepartmentCrudTest.php`, `ProgramCrudTest.php`, `ProfessorCrudTest.php`,
  `GraduationTypeCrudTest.php`, `StudyPlanCrudTest.php`,
  `RequiredDocumentCrudTest.php` — per catalog: create happy; update happy +
  unique-ignore-self; validation 302s (required/max/unique/exists); delete-not-in-use
  happy; **delete-in-use → 302 + `catalogs.error.in_use` + row preserved + not 500**;
  (GraduationType) pivot `sync`; (Professor) advisor/substitute nullOnDelete delete
  succeeds.
- `CatalogPagePropsTest.php` — the 6 index page prop contracts (component + exact
  snake_case shape; `requires_advisor` bool; `required_document_ids` number[]; FK
  option lists). (Or split per page.)
- `CatalogRoleGatingTest.php` — every route (25): guest→login 302; student /
  assistant_secretary / school_services / **secretary / admin** → 403; super_admin →
  200/302. (Mirror `ReportRoleGatingTest`'s `->with()` dataset.)
- `CatalogDemoSecurityTest.php` (CRITICAL) — (a) a demo session (any preset) →
  catalog routes → 403, no mutation; (b) the 18 mutation route names ARE in
  `DemoSessionMiddleware::DESTRUCTIVE_ROUTE_NAMES` (a structural assertion) AND, with
  the guard forced to fire (demo session + `admin` role reaching a mutation route),
  → 302 + `demo.blocked` + `assertDatabaseCount` unchanged.
- `CatalogLangKeyResolutionTest.php` — every `catalogs.*` PHP `__()` key resolves in
  BOTH `lang/es.json` + `lang/en.json` (memory rule).
- `resources/js/Pages/Admin/Catalogs/__tests__/{Index,Departments,Programs,Professors,GraduationTypes,StudyPlans,RequiredDocuments}.test.tsx`
  (Vitest) — each page renders its rows/table, opens the create modal, shows a
  validation error, opens the edit modal pre-filled, confirms delete; the hub renders
  6 cards. Mock `@inertiajs/react` (`Link`, `useForm`, `router`, `usePage`→
  `{props:{demo:null, auth:{user:{role:'super_admin'}}}}`); wrap in `ThemeProvider`+
  `LocaleProvider`; `window.matchMedia` mocked by the vitest setup (memory rule);
  `vi.hoisted()` for `vi.mock` refs; query by `getByRole(button/combobox,{name})`.

### Edit
- `routes/web.php` — import the 7 `Admin\Catalog\*` controllers; add the
  `Route::middleware(['auth','demo','role:super_admin'])->prefix('admin/catalogs')
  ->name('admin.catalogs.')->group(...)` block with the 25 routes (the hub + 6 ×
  `Route::resource`-style index/store/update/destroy — written explicitly, no
  closures, route-model-bound `{department}`/`{program}`/… params).
- `app/Http/Middleware/DemoSessionMiddleware.php` — extend
  `DESTRUCTIVE_ROUTE_NAMES` with the **18** mutation route names
  (`admin.catalogs.{departments,programs,professors,graduation-types,study-plans,required-documents}.{store,update,destroy}`).
- `bootstrap/app.php` — add `$exceptions->render(CatalogInUseException…)` →
  `back()->withErrors(['catalog'=>$e->getMessage()])` (web) / 422 JSON (api), beside
  the existing `InvalidStatusTransitionException` render.
- `lang/es.json` + `lang/en.json` — add `catalogs.created`, `catalogs.updated`,
  `catalogs.deleted`, `catalogs.error.in_use`.
- `resources/locales/es.json` + `resources/locales/en.json` — add the `catalogs.*`
  frontend keys (§6) + optional `catalogs.nav.link`.
- `tests/Arch/ArchitectureTest.php` — add the `App\Domain\Academic` rules (§8).
- **(Optional, Open Q E)** `resources/js/Pages/Graduation/AdminDashboard.tsx` — a
  `super_admin`-only `<Link href={route('admin.catalogs.index')}>` in the header
  (conditional on the shared `auth.user.role` Inertia prop — confirm it ships in
  `HandleInertiaRequests`; if not, render the link unconditionally but rely on the
  403 gate, OR read the role from `usePage().props.auth`).

### Do NOT touch
- **No migration.** No model schema change (no `SoftDeletes` added to
  `RequiredDocument`, no new unique column — D-NOMIGRATION/D-SOFTDELETE). No
  `Student`/`StudentDocument`/`JuryAssignment` change. No `DemoScope`/`DemoContext`/
  `EnsureRole` change. No `RoleLandingRoute` change (super_admin still lands on
  `admin.dashboard`; the hub is reached via the optional nav link). Prior pages stay
  as-is (except the optional dashboard nav link).

## 3. DTO designs (validation SSOT)

> All: `#[TypeScript] final extends \Spatie\LaravelData\Data`, constructor property
> promotion, snake_case public props matching the page contract. Web validation →
> 302 + session errors (controller method-signature injection). The **unique-
> ignore-self** on update: Spatie `Unique('table','column')->ignore($id, 'id')` —
> but the ignored id is only known on update. Two acceptable techniques (decide in
> implement, recommend **A**):
> - **A (recommended): `Unique` rule WITHOUT ignore in the attribute, plus an
>   `Update` Action pre-check** that excludes self — mirrors `SubmitFormBAction::
>   assertControlNumberAvailable` (a `whereKeyNot($model)` `exists()` → throw
>   `ValidationException::withMessages(['code'=>__()])` → 302 field error). Keeps the
>   DTO route-agnostic (same DTO for store + update). The store path's plain
>   `Unique` catches duplicates on create.
> - **B: a `rules(ValidationContext $context)` method** on the DTO reading the route
>   param to build `Rule::unique(...)->ignore($routeId)`. More magic; couples the DTO
>   to the route. Avoid unless the gate prefers it.
> Technique **A** is the established pattern (slice 001 did exactly this for
> `control_number`), so the DTO carries a plain `Unique` (create-correct) and the
> Update Action does the ignore-self exclusion. The prop-contract + CRUD tests pin
> both create-duplicate (DTO rule) and update-keep-own (Action exclusion).

- `DepartmentData` — `code` `#[Required, Max(20), Unique('departments','code')]`;
  `name` `#[Required, Max(150)]`.
- `ProgramData` — `code` `#[Required, Max(20), Unique('programs','code')]`; `name`
  `#[Required, Max(150)]`; `department_id` `#[Required, IntegerType,
  Exists('departments','id')]`.
- `ProfessorData` — `first_name` `#[Required, Max(100)]`; `last_name` `#[Required,
  Max(100)]`; `mother_last_name` `#[Max(100)] ?string = null`; `email` `#[Required,
  Email, Max(255), Unique('professors','email')]`.
- `GraduationTypeData` — `code` `#[Required, Max(20),
  Unique('graduation_types','code')]`; `name` `#[Required, Max(150)]`;
  `requires_advisor` `#[BooleanType] bool = false`; `required_document_ids`
  `#[ArrayType] array` with `#[Exists('required_documents','id')]` on the items via a
  `#[DataCollectionOf]`-free `int[]` + per-item exists (Spatie `['*' =>
  Rule::exists]` — express as `#[Exists('required_documents','id')]` applied to the
  array element rule; confirm the exact attribute spelling in implement — fallback:
  a `rules()` entry `'required_document_ids.*' => ['integer',
  Rule::exists('required_documents','id')]`). Default `[]`.
- `StudyPlanData` — `code` `#[Required, Max(20), Unique('study_plans','code')]`;
  `name` `#[Required, Max(150)]`; `program_id` `#[Required, IntegerType,
  Exists('programs','id')]`.
- `RequiredDocumentData` — `name` `#[Required, Max(150)]`; `description` `#[Max(1000)]
  ?string = null`; `allowed_mimes` `#[Required, Max(255)]` (CSV string); `max_size_kb`
  `#[Required, IntegerType, Min(1), Max(102400)]` (default the model's 10240 in the
  page, but required in the DTO so the value is explicit).

## 4. Action designs (one operation; transactional)

> All: `final` in `App\Domain\Academic\Actions`. Create/Update return the model;
> Delete returns `void`. `DB::transaction` wraps every write (single-table catalogs
> too, for symmetry + the future-proofing the convention expects; the GraduationType
> create/update genuinely spans the pivot). **Never `withoutGlobalScopes()`** in the
> delete pre-check (runs only as super_admin → no demo context → scope is a no-op,
> sees all real children — D-DELETE-GUARD).

### 4.1 Create (×6)
```php
public function handle(DepartmentData $data): Department
{
    return DB::transaction(fn (): Department => Department::create([
        'code' => $data->code, 'name' => $data->name,
    ]));
}
```
- GraduationType create additionally, inside the same transaction:
  `$type = GraduationType::create([...]); $type->requiredDocuments()->sync($data->required_document_ids); return $type;`

### 4.2 Update (×6) — with unique-ignore-self pre-check (technique A)
```php
public function handle(Department $department, DepartmentData $data): Department
{
    $this->assertCodeAvailable($data->code, $department); // whereKeyNot()->exists() → ValidationException on clash
    return DB::transaction(function () use ($department, $data): Department {
        $department->fill(['code' => $data->code, 'name' => $data->name])->save();
        return $department;
    });
}
```
- The `assert{Code,Email}Available` private helper mirrors `SubmitFormBAction::
  assertControlNumberAvailable` (a `Model::where(col,$value)->whereKeyNot($model)
  ->exists()` → `throw ValidationException::withMessages([col => __('…')])` → the
  web path renders 302 + the field error). Catalogs with no unique col
  (`RequiredDocument`) skip the helper.
- GraduationType update also re-`sync()`s the pivot.

### 4.3 Delete (×6) — graceful restrict-FK handling (CRITICAL)
```php
public function handle(Department $department): void
{
    if ($this->isReferenced($department)) {        // pre-check: programs.department_id exists()
        throw new CatalogInUseException(__('catalogs.error.in_use'));
    }
    DB::transaction(function () use ($department): void {
        try {
            $department->delete();                 // soft delete (SoftDeletes); hard for RequiredDocument
        } catch (QueryException $e) {
            // SQLSTATE 23503 = FK violation backstop (belt-and-braces).
            if ($e->getCode() === '23503') {
                throw new CatalogInUseException(__('catalogs.error.in_use'));
            }
            throw $e;
        }
    });
}
```
- `isReferenced()` per catalog uses the §D-DELETE-GUARD set:
  - Department: `Program::where('department_id', $id)->exists()`.
  - Program: `Student::where('program_id',$id)->exists() || StudyPlan::where('program_id',$id)->exists()`.
  - Professor: `JuryAssignment::where('president_professor_id',$id)->orWhere('secretary_professor_id',$id)->orWhere('vocal_professor_id',$id)->exists()` (advisor/substitute are nullOnDelete → excluded).
  - GraduationType: `Student::where('graduation_type_id',$id)->exists()` (pivot cascades → not checked).
  - StudyPlan: `Student::where('study_plan_id',$id)->exists()`.
  - RequiredDocument: `StudentDocument::where('required_document_id',$id)->exists()` (pivot cascades → not checked).
- **Arch note:** these `isReferenced` queries make the Academic Action read
  `Student`/`StudentDocument`/`JuryAssignment` (Graduation/Jury models). That is a
  legitimate cross-domain READ (mirrors Reporting reading Graduation, spec 008 Open
  Q J) — the arch rule to keep is **Academic ↛ Identity** (NOT "only Shared"). Add
  the explicit `Academic ↛ Identity` arch rule (§8). The soft-delete `->delete()`
  on a referenced row is **never reached** (the pre-check throws first); the
  `QueryException` catch backstops the hard-delete (`RequiredDocument`) + any FK we
  failed to pre-check — guaranteeing no 500 under any catalog (scenario 11).

## 5. Controller designs (anemic, ≤15 lines/method)

> All: `final extends Controller`, `App\Http\Controllers\Admin\Catalog`, inject the
> Action via the method signature, DTO via the method signature (Spatie auto-
> resolves + validates → 302 on failure). `store`/`update`/`destroy` →
> `redirect()->back()->with('success', __('catalogs.{created,updated,deleted}'))`.
> `index()` → `Inertia::render` with the list (+ FK options) shaped via `->only()`/
> explicit map. NO direct Eloquent write in the controller (the Action owns it); the
> `index` reads are the catalog's own rows + the unscoped FK option lists (the same
> read-only catalog reads `FormBController::catalogs()` already does — acceptable in
> an anemic render-only method, and the `controllers never touch Eloquent\Model` arch
> rule is satisfied because these are catalog **subclasses**, exactly as the existing
> `FormBController`/`FormBReviewController` query their models and pass).

### 5.1 `CatalogHubController@index`
```php
public function index(): Response
{
    return Inertia::render('Admin/Catalogs/Index', [
        'catalogs' => [
            ['key'=>'departments','title_key'=>'catalogs.departments.title','description_key'=>'catalogs.departments.description','count'=>Department::count(),'route'=>route('admin.catalogs.departments.index')],
            // … programs, professors, graduation_types, study_plans, required_documents …
        ],
    ]);
}
```

### 5.2 `ProgramController` (the richest single-FK shape; the others mirror it)
```php
public function index(): Response
{
    return Inertia::render('Admin/Catalogs/Programs', [
        'programs' => Program::query()->with('department:id,name')->orderBy('code')->get()
            ->map(fn (Program $p): array => [
                'id'=>$p->id,'code'=>$p->code,'name'=>$p->name,
                'department_id'=>$p->department_id,'department_name'=>$p->department->name,
            ])->all(),
        'department_options' => Department::query()->orderBy('name')->get(['id','name'])->all(),
    ]);
}
public function store(ProgramData $data, CreateProgramAction $action): RedirectResponse
{ $action->handle($data); return back()->with('success', __('catalogs.created')); }

public function update(Program $program, ProgramData $data, UpdateProgramAction $action): RedirectResponse
{ $action->handle($program, $data); return back()->with('success', __('catalogs.updated')); }

public function destroy(Program $program, DeleteProgramAction $action): RedirectResponse
{ $action->handle($program); return back()->with('success', __('catalogs.deleted')); }
```
- `DepartmentController` / `ProfessorController` / `RequiredDocumentController`:
  same shape, no FK options. `StudyPlanController`: `program_options`.
  `GraduationTypeController`: `required_document_options` + each row carries
  `required_document_ids` (`$type->requiredDocuments->pluck('id')`).

### Routes (`routes/web.php`)
```php
Route::middleware(['auth', 'demo', 'role:super_admin'])
    ->prefix('admin/catalogs')->name('admin.catalogs.')->group(function (): void {
        Route::get('/', [CatalogHubController::class, 'index'])->name('index');

        Route::get('/departments', [DepartmentController::class, 'index'])->name('departments.index');
        Route::post('/departments', [DepartmentController::class, 'store'])->name('departments.store');
        Route::put('/departments/{department}', [DepartmentController::class, 'update'])->name('departments.update');
        Route::delete('/departments/{department}', [DepartmentController::class, 'destroy'])->name('departments.destroy');
        // … identical 4-route block for programs, professors, graduation-types, study-plans, required-documents …
    });
```
- Route-model binding: `{department}`, `{program}`, `{professor}`,
  `{graduationType}` (kebab path `graduation-types`, param `{graduationType}`),
  `{studyPlan}` (path `study-plans`), `{requiredDocument}` (path
  `required-documents`). Confirm the implicit-binding param names match the model
  variable in the controller signature.

### Demo block (`DemoSessionMiddleware`)
```php
private const DESTRUCTIVE_ROUTE_NAMES = [
    'admin.graduation.ceremony.graduate',
    // Slice 009 — catalog mutations corrupt the SHARED baseline (no demo sandbox).
    'admin.catalogs.departments.store', 'admin.catalogs.departments.update', 'admin.catalogs.departments.destroy',
    'admin.catalogs.programs.store', 'admin.catalogs.programs.update', 'admin.catalogs.programs.destroy',
    'admin.catalogs.professors.store', 'admin.catalogs.professors.update', 'admin.catalogs.professors.destroy',
    'admin.catalogs.graduation-types.store', 'admin.catalogs.graduation-types.update', 'admin.catalogs.graduation-types.destroy',
    'admin.catalogs.study-plans.store', 'admin.catalogs.study-plans.update', 'admin.catalogs.study-plans.destroy',
    'admin.catalogs.required-documents.store', 'admin.catalogs.required-documents.update', 'admin.catalogs.required-documents.destroy',
];
```

### Exception render (`bootstrap/app.php`)
```php
$exceptions->render(function (CatalogInUseException $e, Request $request) {
    return $request->expectsJson()
        ? response()->json(['message' => $e->getMessage()], 422)
        : back()->withErrors(['catalog' => $e->getMessage()]);
});
```

## 6. Frontend designs

> All pages mirror `Graduation/Review.tsx`/`AdminDashboard.tsx` chrome: `Head`,
> header (`app.name` + `LanguageSwitcher`), `<DemoBanner />` (a no-op for real
> super_admin — and a demo session can't reach these pages anyway), one
> `<main id="main">`, one `<h1>`. Theme via tokens, locale via `useLocale().t`. WCAG
> 2.2 AA: semantic `<table>`, accessible create/edit **modal** (the `Review.tsx`
> dialog: `role="dialog"`, `aria-modal`, `aria-labelledby`, focus-on-open, Escape-
> close, backdrop-close), `TextField`/`SelectField`/`FormError`, real `<Link>`s.

- **`Index.tsx`** — props `{catalogs}`; a responsive grid of 6 cards, each a `<Link
  href={c.route}>` with `t(c.title_key)`, `t(c.description_key)`, and `c.count`.
- **`Departments.tsx`** — a "New" button → create modal (`code`, `name` via
  `TextField`); a `<table>` (code, name, actions); per row Edit (modal pre-filled,
  `router.put` to `admin.catalogs.departments.update`) + Delete (confirm dialog →
  `router.delete`). `useForm` errors map the 302 session errors.
- **`Programs.tsx`** — same + a `department_id` `SelectField` (from
  `department_options`); the table shows `department_name`.
- **`Professors.tsx`** — `first_name`, `last_name`, `mother_last_name?`, `email`;
  table shows `full_name` + email.
- **`GraduationTypes.tsx`** — `code`, `name`, a `requires_advisor` checkbox, and a
  **multi-select** of `required_document_options` bound to `required_document_ids`
  (a checkbox list or a multi `<select>` — accessible labelled group); the table
  shows the type + a "requires advisor" badge + the count of required documents.
- **`StudyPlans.tsx`** — `code`, `name`, `program_id` `SelectField` (from
  `program_options`); table shows `program_name`.
- **`RequiredDocuments.tsx`** — `name`, `description?`, `allowed_mimes` (CSV text),
  `max_size_kb` (number); table shows name + mimes + size.
- **(Optional) `AdminDashboard.tsx`** — a `super_admin`-only `<Link
  href={route('admin.catalogs.index')}>` `t('catalogs.nav.link')` in the header
  (conditional on `usePage().props.auth?.user?.role === 'super_admin'`).

> **Type-only** `import type { UserRole } from '@/types/generated'` only if a value
> is compared (the nav-link role check) — compare the raw string cast to the type,
> never value-import.

## 7. i18n keys to add

### `lang/{es,en}.json` (PHP `__()` — flashes + the error)
- `catalogs.created`, `catalogs.updated`, `catalogs.deleted`,
  `catalogs.error.in_use` (the friendly "this record is in use and can't be
  deleted" message). **BOTH** files; a `CatalogLangKeyResolutionTest` proves each
  resolves (memory rule).
> The unique-clash `ValidationException` messages (`assert{Code,Email}Available`)
> also resolve a `__()` key — reuse a `catalogs.error.code_taken` /
> `catalogs.error.email_taken` (add to both `lang/` files + the resolution test).

### `resources/locales/{es,en}.json` (frontend `useLocale().t`)
- hub: `catalogs.hub.title`, `catalogs.hub.subtitle`, and per catalog
  `catalogs.{departments,programs,professors,graduation_types,study_plans,required_documents}.title`/`.description`.
- per catalog page: `…subtitle`, the column headers (`…col.code`, `…col.name`,
  `…col.department`, `…col.program`, `…col.email`, `…col.requires_advisor`,
  `…col.required_documents`, `…col.mimes`, `…col.max_size`, `…col.actions`), the
  form labels (same keys reused), `catalogs.new`, `catalogs.edit`, `catalogs.save`,
  `catalogs.cancel`, `catalogs.delete`, `catalogs.delete.confirm`,
  `catalogs.empty`, `catalogs.requires_advisor`, `catalogs.required_documents`.
- optional `catalogs.nav.link`.

## 8. Tests (build exactly the spec §3 + §2 list)

- **Feature (PostgreSQL 18, `uses(RefreshDatabase::class)`, `actingAs`):**
  - **`{Catalog}CrudTest`** (×6) — create happy (`assertRedirect` back + flash,
    `assertDatabaseHas`); update happy keeping own code/email (unique-ignore-self
    passes); update to a foreign code → 302 + `assertSessionHasErrors('code')`;
    validation (missing/over-Max/bad-FK-`exists`/duplicate) → 302 +
    `assertSessionHasErrors`; delete-not-in-use → `assertSoftDeleted` /
    `assertDatabaseMissing`; **delete-in-use → 302 +
    `assertSessionHasErrors('catalog')` (or the rendered in-use message) + the row
    still present + NOT 500** (seed a `Student`/`StudyPlan`/`JuryAssignment`/
    `StudentDocument` referencing the row via the existing factories);
    (GraduationType) create/update with `required_document_ids` → pivot `sync` exact;
    (Professor) delete a professor used only as advisor/substitute → succeeds
    (nullOnDelete), the column nulled.
  - **`CatalogHubPagePropsTest`** — `GET admin.catalogs.index` →
    `Admin/Catalogs/Index`, `catalogs` exactly 6 rows, fixed keys + counts +
    resolved routes.
  - **`CatalogPagePropsTest`** — the 6 index page prop contracts (component + exact
    snake_case shape; `requires_advisor` bool via `where`; `required_document_ids`
    array; the FK option lists present). One `it()` per page or split per file.
  - **`CatalogRoleGatingTest`** — the 25 routes via a `->with()` dataset: guest →
    `assertRedirect(route('login'))` 302; `student`/`assistant_secretary`/
    `school_services`/**`secretary`/`admin`** → `assertForbidden`; `super_admin` →
    200 (GET index/hub) / 302 (mutations with valid data, or a controlled 302).
    (Mirror `ReportRoleGatingTest`.)
  - **`CatalogDemoSecurityTest`** (CRITICAL) — (a) provision a demo session (the
    `admin`/`personal` preset via the DemoLogin flow, or `User::factory()->admin()
    ->demo($sid)` + `->withSession(['is_demo'=>true,'demo_session_id'=>$sid,
    'demo_expires_at'=>now()->addMinutes(30)->timestamp])`) and assert every catalog
    route (GET + a mutation POST/PUT/DELETE) → **403** (the super_admin gate; the
    demo `admin` role isn't super_admin), with `assertDatabaseCount` unchanged.
    (b) a structural assertion that the 18 mutation names are all in
    `DemoSessionMiddleware::DESTRUCTIVE_ROUTE_NAMES` (reflection or a shared const);
    AND a forced-guard test — temporarily widen by acting as a demo session and
    hitting a route that IS in the destructive list AND the demo can reach (the
    existing `admin.graduation.ceremony.graduate` already proves the mechanism;
    for catalogs, since the gate 403s first, assert the **membership** is what would
    block a future widening) → document that (a) is the live control and (b) is the
    defense-in-depth invariant. (Keep the test falsifiable: removing a name from the
    const, or widening the gate to `admin`, must fail a test.)
  - **`CatalogLangKeyResolutionTest`** — `catalogs.created/updated/deleted/
    error.in_use/error.code_taken/error.email_taken` each resolve (≠ the key
    string) in BOTH es + en (memory rule).
- **Arch (`tests/Arch/ArchitectureTest.php`)** — add (mirroring the Reporting block):
  - `arch('cross-domain isolation: Academic does not import Identity')
    ->expect('App\Domain\Academic')->not->toUse('App\Domain\Identity');`
  - `arch('academic actions are final')->expect('App\Domain\Academic\Actions')
    ->classes()->toBeFinal();`
  - `arch('academic data DTOs are final')->expect('App\Domain\Academic\Data')
    ->classes()->toBeFinal();`
  - `arch('academic exceptions are final')->expect('App\Domain\Academic\Exceptions')
    ->classes()->toBeFinal();`
  - the broad `controllers never touch Eloquent\Model` rule already passes (the new
    controllers query catalog **subclasses**, like the existing `FormBController`);
    **do NOT** add a literal "Academic only uses Shared" rule (the delete pre-check
    legitimately reads Graduation/Jury models — Open Q J of spec 008 precedent).
- **Vitest** — the 7 page tests; mock `@inertiajs/react` (`Link`, `useForm`
  returning `{data,setData,post,put,processing,errors,reset,clearErrors}`, `router`
  `{post,put,delete}`, `usePage`→`{props:{demo:null, auth:{user:{role:'super_admin'}}}}`);
  `vi.hoisted()` refs; wrap in `ThemeProvider`+`LocaleProvider`; assert each page
  renders its table rows, opens the create modal (`getByRole('button',{name:/new/i})`),
  shows a validation error, opens an edit modal pre-filled, fires a delete confirm;
  the hub renders 6 cards. (`window.matchMedia` mocked globally by the vitest setup.)

## 9. Quality gates (per CLAUDE.md; run by the implement/verify phase, not here)

`composer format` → `composer analyse` (Larastan 9/10) → `php artisan
typescript:transform` (emits the 6 catalog DTOs) → `composer test` (Pest on
PostgreSQL: arch + the new Feature/CRUD/contract/security tests) → `pnpm test`
(Vitest) → `pnpm build` (tsc). **Orchestrator note:** do NOT run pest/migrate/build
on the shared DB during generation; the architect/agents may run phpstan + tsc
(read-only) only (memory rule).

## 10. Risks / watch-list

- **Demo-mutates-catalog leak (CRITICAL, #1):** the gate (`super_admin`, no preset)
  is the live control; the `DESTRUCTIVE_ROUTE_NAMES` list is the defense-in-depth.
  `CatalogDemoSecurityTest` is the guard; removing a name OR widening the gate must
  fail a test. NEVER gate catalogs to the `admin,super_admin,secretary` trio without
  the destructive-block carrying its weight.
- **Restrict-FK delete 500 (CRITICAL, #2):** the pre-check + the `QueryException`
  (23503) catch + the `CatalogInUseException` render together guarantee a 302, never
  a 500. Every catalog has a delete-in-use test. `RequiredDocument` (hard delete) is
  the riskiest (no soft-delete cushion) — its catch path is exercised.
- **Soft-delete-into-a-ghost:** a `Program` soft-deleted while students reference it
  would leave referenced ghosts — the **pre-check refuses it** before the soft
  delete. Do NOT rely on the FK alone (a soft delete is an UPDATE, doesn't trip it).
- **Unique-ignore-self drift:** technique A (DTO plain `Unique` + Update Action
  `whereKeyNot` exclusion) must let a row keep its own code/email on update. Both
  create-duplicate and update-keep-own are tested.
- **`withoutGlobalScopes()` temptation:** the delete pre-check reads scoped models
  (`Student`/`StudentDocument`/`JuryAssignment`) but runs only as super_admin (no
  demo context → scope no-op → sees all real children). Do NOT add
  `withoutGlobalScopes()` (memory/spec-008 rule); the test runs as a real
  super_admin with real children.
- **Pivot `sync` semantics:** GraduationType update with a shrunk
  `required_document_ids` must REMOVE the dropped pivot rows (sync, not attach). The
  cascade pivot means no restrict concern. Tested.
- **Inertia prop drift:** snake_case + `requires_advisor` bool +
  `required_document_ids` number[] + FK option lists — prop-contract tests lock them.
- **Route-model binding param names:** kebab paths (`graduation-types`,
  `study-plans`, `required-documents`) with camelCase params
  (`{graduationType}`/`{studyPlan}`/`{requiredDocument}`) — confirm implicit binding
  resolves (the param name must match the controller arg name).
- **No migration honesty:** the schema exists; `RequiredDocument`'s missing
  soft-delete + unique-name are pre-existing and left as-is (D-NOMIGRATION). Do not
  silently add a migration; if the gate wants one, it's separate + justified +
  reversible.

## 11. Constitution check
strict_types ✓ · explicit returns ✓ · final controllers + final Academic Actions /
Data / Exceptions ✓ · Spatie Data DTOs only (no FormRequest); web validation 302 +
session errors ✓ · Action + `DB::transaction` on the pivot writes ✓ · anemic
controllers (≤15 lines, DTO → Action → redirect) ✓ · named routes / no closures ✓ ·
authorization in HTTP middleware (`role:super_admin` + `demo`) ✓ · Academic ↛ Http /
↛ Identity ✓ (the delete pre-check reads Graduation/Jury models — a legitimate
cross-domain read, NOT Identity) · never `withoutGlobalScopes()` ✓ · restrict-FK
delete → graceful 302 via `CatalogInUseException`, never 500 ✓ · every `__()` key in
BOTH lang files + resolution test ✓ · Inertia props snake_case + prop-contract test
per page ✓ · type-only generated-enum import ✓ · dark/light + ES/EN, WCAG 2.2 AA ✓ ·
Pest + arch on Postgres ✓ · bigint ids (ADR-001) ✓ · **NO migration** (schema
exists) ✓.
