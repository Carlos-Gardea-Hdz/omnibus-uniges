# Plan 001 — Graduation Domain (Form B vertical slice) — the HOW

> Companion to `spec.md`. Grounded in two read-only recons (SPEC.md domain map +
> scaffold inventory). Gated on `CLAUDE.md §No-negociables`.

## 0. Decisions & flagged conflicts (resolve at implement)

| # | Topic | Decision | Why / flag |
|---|---|---|---|
| D1 | **ID strategy** | `students.id` + catalog PKs = bigint `$table->id()`; `students.user_id` = `foreignId` (matches `users.id` bigint). | Foundation `users` uses bigint; no ULID/UUID trait exists. SPEC §6.3.11 says CHAR(26) ULID; global law says UUIDv7. **3-way conflict.** Do NOT introduce a 3rd ID scheme in a feature slice. **→ ADR `docs/decisions/ADR-001-id-strategy.md`** to settle project-wide (recommend UUIDv7 across the board in a dedicated foundation PR). |
| D2 | **Test DB** | Add Sail `docker-compose.yml` (php8.5 app + pgsql18 + valkey8). `phpunit.xml`→`pgsql` test connection (DB `uniges_testing`). Feature tests `RefreshDatabase` on Postgres. | Law: never SQLite for DB tests. `students` uses JSONB + CHECK → SQLite would misbehave. No local Sail compose exists yet. Infra-as-code (Law 3); reusable for the whole project. |
| D3 | **PHPStan** | level 9 (as configured). | Matches `phpstan.neon` + spec. |
| D4 | **Cross-domain isolation** | Authorization (role checks) lives in HTTP middleware/controllers (Identity's `UserRole`), never in `App\Domain\Graduation`. `Student` model relates to `App\Models\User` (NOT `App\Domain\Identity`), so the arch rule `Graduation ↛ Identity` holds. | Existing arch test forbids `App\Domain\Graduation` → `App\Domain\Identity`. |
| D5 | **Slice end** | Build the **full `students` table** (all SPEC §6.3.11 columns) in one migration, but only Form B Actions (states 1↔2↔3, 2→4). | Avoid 8 later ALTERs; schema is known & stable. YAGNI applies to behavior, not to a known-complete table. |
| D6 | **Catalog depth** | 5 catalog tables: schema-first migrations + minimal Eloquent models + factories. No catalog business logic. | Enough to satisfy `students` FKs and build factories for feature tests. |

## 1. Architecture (unchanged from foundation conventions)

Request lifecycle: **Controller (≤15 lines) → Spatie Data DTO (validation SSOT) →
one Action `handle(DTO): Student` (`DB::transaction`, dispatches event) →
`Inertia::render`/redirect with `->only()` props.** Domain never touches HTTP. State
mutations only through `GraduationStateMachine` (exists). Every transition dispatches
`StudentStatusChanged` (ShouldBroadcast) on private `student.{id}`.

## 2. Data model

### 2.1 `users` — ADD `role` (migration + model)
- Migration `..._add_role_to_users_table`: `role` string, default `'student'`, indexed; cast to `UserRole` (exists). `down()` drops the column (reversible).
- `User` model: add `'role'` to `$fillable`, `'role' => UserRole::class` cast, `student(): HasOne` → `App\Domain\Graduation\Models\Student`.
- `HandleInertiaRequests::share` → add `auth.user.role` (`->only(['id','name','email'])` + `'role' => $request->user()?->role` ). `types/index.ts` already expects `role`.

### 2.2 `students` (full schema per SPEC §6.3.11) — migration `..._create_students_table`
Columns (bigint id, timestamps, softDeletes): `user_id` FK→users **restrict** unique;
`control_number` string(12) unique (CHECK `~ '^\d{8,12}$'`); `program_id` FK→programs
restrict; `graduation_type_id` FK→graduation_types restrict; `study_plan_id` FK→study_plans
restrict; `advisor_id` FK→professors **nullOnDelete** nullable; `first_name`/`last_name`
string(100), `mother_last_name` string(100) null; `gender` string + CHECK in (male,female,other);
`age` int null; `phone`/`mobile` string(15) null; `status` string default `'form_b_pending'`
(cast `GraduationStatus`); `workflow_metadata` jsonb null; `gpa` decimal(5,2) CHECK 70–100;
`enrollment_date` date; `graduation_date` date null; `thesis_title` string(300) null;
`thesis_abstract` text null; `form_b_submitted_at` ts null; `form_b_approved` bool default
false; `form_b_observations` text null; `annex_iii_completed` bool default false;
`documents_completed_at` ts null; `payment_reference` string(50) null; `payment_verified`
bool default false; `paid_at` ts null; `ceremony_date` ts null; `ceremony_location`
string(200) null; `diploma_folio` string(50) null; `record_book`/`record_sheet` string(20)
null; `address_street` string(125) null, `address_neighborhood` string(100) null,
`address_ext_number`/`address_int_number` string(11) null, `address_postal_code` int null
CHECK (null or 10000–99999); `is_team_project` bool default false. CHECK constraints via
`DB::statement` after `Schema::create` (Postgres) — reversible (table drop).

### 2.3 Catalog tables (schema-first) — 5 migrations in dependency order
`departments` (id,name,code,timestamps,softDeletes) → `programs` (+`department_id`
FK→departments restrict) → `professors` (id,first/last name,email,timestamps,softDeletes)
→ `graduation_types` (id,code,name,requires_advisor bool,timestamps,softDeletes) →
`study_plans` (id,code,name,program_id FK→programs restrict,timestamps,softDeletes).
Minimal models (`final`, `$fillable`, SoftDeletes, `HasFactory`) + factories.

### 2.4 Migration order (matches SPEC §12.3 subset)
`departments → programs → professors → graduation_types → study_plans → add_role_to_users
→ students`.

## 3. Domain layer (`app/Domain/Graduation`)

**Exists (reuse):** `Enums/GraduationStatus`, `ValueObjects/ControlNumber`,
`ValueObjects/Gpa` (integer hundredths), `StateMachine/GraduationStateMachine`,
`Exceptions/InvalidStatusTransitionException`. **Shared:** `ValueObjects/Email`.

**Create:**
- `app/Domain/Shared/ValueObjects/Address.php` — `final readonly`, fields street/neighborhood/
  extNumber/intNumber/postalCode; validate postal 10000–99999 in ctor; `toArray()`.
- `app/Domain/Graduation/Models/Student.php` — `final`, `HasFactory`, `SoftDeletes`,
  `$fillable`, `$casts` (`status`→GraduationStatus, `workflow_metadata`→array, dates,
  `form_b_approved`/etc → bool, `gpa`→decimal:2). Relationships: `user` BelongsTo
  `App\Models\User`; `program`/`graduationType`/`studyPlan`/`advisor` BelongsTo catalog
  models. Accessors: `controlNumber(): ControlNumber`, `gpaVo(): Gpa`, `address(): ?Address`.
  No business logic (transitions live in Actions+state machine).
- `app/Domain/Graduation/Events/StudentStatusChanged.php` — `final`, `implements
  ShouldBroadcast`, `readonly` promoted ctor `(Student $student, GraduationStatus $from,
  GraduationStatus $to)`. `broadcastOn(): PrivateChannel('student.'.$student->id)`;
  `broadcastAs(): 'graduation.step.completed'`; `broadcastWith(): {completed_step: from.value,
  next_step: to.value, progress: round(to.step()/9*100), message: to->labelKey()}`.
- `app/Domain/Graduation/Actions/SubmitFormBAction.php` — `handle(SubmitFormBData, Student):
  Student`. Guard `status->step() <= 3`. `DB::transaction`: assertCanTransition(current→FormBReview),
  fill data, if resubmit clear `form_b_observations`, set `form_b_submitted_at`, set status,
  save, `StudentStatusChanged::dispatch`. Returns Student.
- `app/Domain/Graduation/Actions/ApproveFormBAction.php` — `handle(Student): Student`. tx:
  assert FormBReview→AnnexesPending, set `form_b_approved=true`, status, save, dispatch.
- `app/Domain/Graduation/Actions/RejectFormBAction.php` — `handle(Student, ReviewFormBData):
  Student`. tx: assert FormBReview→FormBRejected, set `form_b_observations`, status, save, dispatch.
- `app/Domain/Graduation/Data/SubmitFormBData.php` — `#[TypeScript] final extends Data`.
  Fields with attributes: control_number `#[Required,Regex('/^\d{8,12}$/')]`; first/last/
  mother name; gender `#[In(...)]`; gpa `#[Required,Numeric,Min(70),Max(100)]`;
  enrollment_date `#[Date]`; thesis fields nullable; address fields; program_id/
  graduation_type_id/study_plan_id `#[Required]` + DB existence rule. Uniqueness of
  control_number via `#[Unique]` (ignore self on update).
- `app/Domain/Graduation/Data/ReviewFormBData.php` — `observations` `#[Required,Min(5)]`
  (used by reject; approve takes no body).

## 4. HTTP layer

- `app/Http/Middleware/EnsureRole.php` — alias `role`; `role:student` / `role:admin,super_admin`.
  Registered in `bootstrap/app.php` `->withMiddleware(fn => $m->alias([...]))`. 403 on mismatch.
- `app/Http/Controllers/Student/FormBController.php` — `final`, ≤15-line methods:
  `create()` Inertia `Student/FormB` (student's own record + status, `->only()`); `store`/
  `update(SubmitFormBData)` → `SubmitFormBAction` → redirect back with flash.
- `app/Http/Controllers/Graduation/FormBReviewController.php` — `index()` Inertia
  `Graduation/Review` (paginated FormBReview students, `->only()`); `approve(Student)` →
  ApproveFormBAction; `reject(Student, ReviewFormBData)` → RejectFormBAction.
- `app/Http/Controllers/Student/StatusController.php` — `index()` Inertia `Student/Status`.
- **Routes** (`routes/web.php`, named, no closures, role middleware):
  `GET /student/form-b` create · `POST /student/form-b` store · `PUT /student/form-b` update
  · `GET /student/status` · (admin) `GET /admin/graduation/review` · `POST
  /admin/graduation/{student}/approve` · `POST /admin/graduation/{student}/reject`.
- **Channel auth** (`routes/channels.php`): `Broadcast::channel('student.{studentId}',
  fn(User $u, string $studentId) => (string)$u->student?->getKey() === $studentId ||
  (bool)$u->role?->isStaff())`.
- `.env`/`.env.example`: `BROADCAST_CONNECTION=reverb` + REVERB_* + VITE_REVERB_* (generate
  dev creds). Keep secret server-side; only `VITE_REVERB_APP_KEY` to client.

## 5. Frontend (Inertia 2 + React 19 + TS)

- `resources/js/Pages/Student/FormB.tsx` — form via `useForm<SubmitFormBData>()` typed from
  `generated.d.ts`; field components; i18n via `useLocale().t`; theme-aware tokens; submit to
  `store`/`update`; show `form_b_observations` if rejected. WCAG 2.2 AA (labels, focus rings,
  error `aria-describedby`).
- `resources/js/Pages/Student/Status.tsx` — renders `GraduationProgress`; subscribes via
  `createEcho()` to `student.{id}` `graduation.step.completed`, updates step live; cleanup on
  unmount.
- `resources/js/Pages/Graduation/Review.tsx` — admin table of FormBReview students; approve/
  reject (reject opens a dialog for observations).
- `resources/js/Components/GraduationProgress.tsx` — 9-step bar from `GraduationStatus` enum
  (uses `step()`/`color()` mirrored in TS), `aria` progress semantics.
- `resources/js/Components/form/{TextField,SelectField,FormError}.tsx` — minimal a11y
  primitives (Tailwind v4 tokens).
- `resources/js/echo.ts` already exports `createEcho()`; wire it lazily in Status page (not
  globally) to avoid a socket on every page.
- i18n: add to `resources/locales/{es,en}.json` → `form.*` (labels, submit, success),
  `validation.*`, `admin.review.*`. `status.*` already present.

## 6. Tests

- `tests/Unit/Graduation/GraduationStateMachineTest.php` — every legal edge passes; a
  representative illegal edge throws `InvalidStatusTransitionException`. (enum test exists.)
- `tests/Unit/Shared/AddressTest.php`, `tests/Unit/Graduation/Form*` VO edge cases.
- `tests/Feature/Graduation/SubmitFormBTest.php` — student submits valid → status FormBReview,
  event dispatched (`Event::fake`), DB persisted; invalid GPA/control rejected by DTO (422).
- `tests/Feature/Graduation/ReviewFormBTest.php` — admin approve (→AnnexesPending,
  form_b_approved) / reject (→FormBRejected, observations) / resubmit (→FormBReview, cleared);
  non-staff gets 403; illegal transition path asserts no DB change.
- `tests/Feature/Graduation/StatusBroadcastTest.php` — assert event `broadcastOn`/`broadcastAs`/
  `broadcastWith` contract.
- Extend `tests/Arch/ArchitectureTest.php` — Actions are `final` + `invokable`-ish; Events
  final; new VO readonly (Shared rule already covers); `App\Domain` ↛ `Illuminate\Http` holds.
- `resources/js/Pages/Student/__tests__/FormB.test.tsx` (Vitest) — renders, validation display.
- All DB tests on **pgsql** (`RefreshDatabase`); factories: `StudentFactory` + 5 catalog
  factories + `UserFactory` role states.

## 7. Infra (D2)
- `docker-compose.yml` (Sail style): `laravel.test` (build `./vendor/laravel/sail/runtimes/8.5`
  or `php:8.5`), `pgsql` (postgres:18, db `uniges`, + create `uniges_testing`), `valkey`
  (valkey/valkey:8). Volumes, healthchecks, `sail` network.
- `config/database.php`: ensure `pgsql` connection reads env; add nothing if present.
- `phpunit.xml`: `DB_CONNECTION=pgsql`, `DB_DATABASE=uniges_testing`; keep cache/queue array,
  `BROADCAST_CONNECTION=null` in tests (assert via `Event::fake`, no live socket).

## 8. File manifest

**Create (~34):** 7 migrations · 6 models (Student + 5 catalog) · 6 factories · Address VO ·
StudentStatusChanged event · 3 Actions · 2 DTOs · EnsureRole middleware · 3 controllers ·
3 Inertia pages · GraduationProgress + 3 form components · 7 test files · docker-compose.yml.
**Modify (~7):** `User.php` · `HandleInertiaRequests.php` · `bootstrap/app.php` · `routes/web.php`
· `routes/channels.php` · `phpunit.xml` · `resources/locales/{es,en}.json` · `.env.example`.
**Generated:** `resources/js/types/generated.d.ts` via `typescript:transform`.
**New doc:** `docs/decisions/ADR-001-id-strategy.md` (records D1; recommends project-wide UUIDv7).

## 9. Verify order (phase 5)
`composer format` → `composer analyse` (L9) → `typescript:transform` → `sail up -d` →
`sail artisan migrate:fresh` → `sail pest` (Unit+Arch+Feature on pgsql) → `pnpm tsc --noEmit`
→ `pnpm lint` → `pnpm vitest run` → `pnpm build`. Evidence captured for the gate.

## 10. Constitution check
strict_types ✓ · explicit returns ✓ · final domain ✓ · readonly VO/DTO/event ✓ · backed enum ✓
· Spatie Data only (no FormRequest) ✓ · Action+`DB::transaction` ✓ · anemic controllers ✓ ·
named routes/no closures ✓ · Pest+arch on Postgres ✓ · no PII in factories (fictional) ✓ ·
reversible migrations ✓. ID law (UUIDv7) **deferred via ADR-001** (flagged, not silently broken).
