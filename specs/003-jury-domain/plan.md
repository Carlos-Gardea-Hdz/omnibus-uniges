# Plan 003 — Jury Domain (payment → jury assignment, 6→7) — the HOW

> Companion to `spec.md`. Grounded in a read-only recon of the complete slice-001
> and slice-002 implementations (the canonical pattern) + SPEC.md
> §3.5/§6.3.15/§3.3/§6.1. Gated on `CLAUDE.md §No-negociables`. Mirrors plan 002's
> structure.

## 0. Decisions & flagged conflicts (resolve at implement)

| # | Topic | Decision | Why / flag |
|---|---|---|---|
| D1 | **ID strategy** | `jury_assignments.id` = bigint `$table->id()`; all FKs `foreignId`. | Continues ADR-001 (001/002). SPEC §6.3.15 says CHAR(26) ULID — same documented deviation; do not introduce a 3rd scheme. |
| D2 | **CHECK for nullable substitute** | bigint scheme → no ULID sentinel. Per-pair `(substitute_professor_id IS NULL OR a != substitute_professor_id)` rather than the SPEC's `COALESCE(..., '0000…0')`. | Functionally identical to §6.3.15; idiomatic for bigint + NULL. |
| D3 | **No payment migration** | Reuse `students.payment_reference` / `payment_verified` / `paid_at` (slice-001 students migration, already present). | SPEC §3.4/§6.3.11; the prompt mandates reuse. Adding a payment table/columns is forbidden here. |
| D4 | **Four-distinct rule placement** | Pairwise `#[Different(...)]` on `AssignJuryData` (field-keyed session errors + TS type) **AND** the DB CHECK (defense in depth). | Laravel `different` passes when the other field is null → optional substitute handled. DTO is the validation SSOT; CHECK is the invariant of last resort (JURY-01). |
| D5 | **verify ≠ assign** | `VerifyPaymentAction` flips `payment_verified` with NO transition. The 6→7 advance lives only in `AssignJuryAction`. | SPEC §3.5 makes verification a *precondition*, not the transition. |
| D6 | **Precondition guard placement** | `AssignJuryAction` asserts `payment_verified === true` (throws `ValidationException` with an i18n message) BEFORE calling the state machine; the state machine guards `status === PaymentPending`. | SPEC §3.5 JURY-02. Domain-level, inside the transaction, fail-loud. Authorization stays in HTTP. |
| D7 | **`JuryAssigned` broadcast** | Ship it (spec Open Q C, recommended). `ShouldBroadcast`, private `student.{id}`, name `jury.assigned`. `StudentStatusChanged(6→7)` still fires for the progress bar. | Symmetry with slice-002 `DocumentStatusChanged`. If the gate declines, drop the event + its test + the React listener; `StudentStatusChanged` alone suffices. |
| D8 | **New domain namespace** | Jury code lives in `App\Domain\Jury\{Enums,Models,Actions,Data,Events}` (the scaffolded `app/Domain/Jury/*` dirs). `Student` model gains a `juryAssignment(): HasOne` relation (in `App\Domain\Graduation`). | SPEC §5 domain layout (`Jury/` exists). Cross-domain: Jury MAY import Graduation (Student, GraduationStatus, state machine, StudentStatusChanged) and Academic (Professor); Graduation ↛ Jury (one-way), and nothing imports Identity. |

## 1. Architecture (unchanged from slice-001/002 conventions)

Request lifecycle: **Controller (≤15 lines) → Spatie Data DTO (validation SSOT) →
one Action `handle(...)` (`DB::transaction`, dispatches event[s]) →
redirect/`Inertia::render` with `->only()`/`->map()` props.** Domain never touches
HTTP or Identity. Status mutations **only** through `GraduationStateMachine` (exists;
edge 6→7 already legal). Payment-column mutations only inside `SubmitPaymentAction`
/`VerifyPaymentAction`; jury creation only inside `AssignJuryAction`. Broadcast on
the existing private `student.{id}` channel: `StudentStatusChanged` (reused) for the
6→7 advance, `JuryAssigned` (new) for the jury detail.

## 2. Data model

### 2.1 `jury_assignments` — migration `2026_06_19_000040_create_jury_assignments_table`
- `id` bigint `$table->id()`.
- `student_id` `foreignId` → students **restrictOnDelete**, **`->unique()`**
  (1:1, historical; SPEC §6.1 row 1581 + §6.3.15 unique index).
- `president_professor_id` `foreignId` → professors **restrictOnDelete**.
- `secretary_professor_id` `foreignId` → professors **restrictOnDelete**.
- `vocal_professor_id` `foreignId` → professors **restrictOnDelete**.
- `substitute_professor_id` `foreignId` **nullable** → professors **nullOnDelete**
  (SPEC §6.1 row 1585).
- `timestamps()`; `softDeletes()` (FK-restrict / historical rule, matches slice-002).

  > **`foreignId('x_professor_id')->constrained('professors')`** infers the table
  > from the column name `professors`? No — `x_professor_id` does not resolve to
  > `professors` automatically. Use the explicit form:
  > `$table->foreignId('president_professor_id')->constrained('professors')->restrictOnDelete();`
  > (and likewise for each), so the FK targets `professors(id)`.

- **CHECK** (Postgres, via `DB::statement` AFTER `Schema::create`, reversible by the
  table drop in `down()`), name `jury_assignments_distinct_professors_check`:
  ```sql
  ALTER TABLE jury_assignments ADD CONSTRAINT jury_assignments_distinct_professors_check CHECK (
      president_professor_id <> secretary_professor_id
      AND president_professor_id <> vocal_professor_id
      AND secretary_professor_id <> vocal_professor_id
      AND (substitute_professor_id IS NULL OR substitute_professor_id <> president_professor_id)
      AND (substitute_professor_id IS NULL OR substitute_professor_id <> secretary_professor_id)
      AND (substitute_professor_id IS NULL OR substitute_professor_id <> vocal_professor_id)
  )
  ```
- `down()`: `Schema::dropIfExists('jury_assignments')` (drops the CHECK with it).

### 2.2 Migration order
Extends slice-002 order. After `2026_06_19_000030_create_student_documents_table`,
add `2026_06_19_000040_create_jury_assignments_table` (needs `students` +
`professors`, both already migrated earlier). No `students` change.

## 3. Domain layer (`app/Domain/Jury` + a relation on `app/Domain/Graduation/Models/Student`)

**Exists (reuse):** `Graduation\Enums\GraduationStatus` (edge 6→7 legal),
`Graduation\StateMachine\GraduationStateMachine`,
`Graduation\Exceptions\InvalidStatusTransitionException`,
`Graduation\Models\Student` (payment columns + casts already present:
`payment_verified`→bool, `paid_at`→datetime), `Graduation\Events\StudentStatusChanged`,
`Academic\Models\Professor`.

**Create:**

- `app/Domain/Jury/Enums/JuryRole.php` — `#[TypeScript] enum JuryRole: string`
  cases `President='president'`, `Secretary='secretary'`, `Vocal='vocal'`,
  `Substitute='substitute'`; `labelKey(): 'jury_role.'.$this->value`; `color()`
  (president→primary, secretary→primary, vocal→primary, substitute→warning — pick
  semantic tokens consistent with the other enums).
- `app/Domain/Jury/Models/JuryAssignment.php` — `final`, `HasFactory`,
  `SoftDeletes`, `$fillable` = `['student_id','president_professor_id',
  'secretary_professor_id','vocal_professor_id','substitute_professor_id']`. No enum
  casts (the roles are columns, not a status). `@property int $id`,
  `@property int $student_id`, `@property int $president_professor_id`,
  `@property int $secretary_professor_id`, `@property int $vocal_professor_id`,
  `@property int|null $substitute_professor_id`, plus `@property-read Student $student`,
  `@property-read Professor $president`, `@property-read Professor $secretary`,
  `@property-read Professor $vocal`, `@property-read Professor|null $substitute`
  (NON-nullable belongsTo MUST be `@property-read Professor $x`, per the memory rule
  — otherwise Larastan types them `Professor|null`). Relations:
  `student(): BelongsTo<Student>`, `president(): BelongsTo<Professor,'president_professor_id'>`,
  `secretary(): BelongsTo<Professor,'secretary_professor_id'>`,
  `vocal(): BelongsTo<Professor,'vocal_professor_id'>`,
  `substitute(): BelongsTo<Professor,'substitute_professor_id'>`. `newFactory()`.
- `app/Domain/Graduation/Models/Student.php` — **modify**: add
  `juryAssignment(): HasOne<JuryAssignment>` + `@property-read JuryAssignment|null
  $juryAssignment` PHPDoc. No business logic. (Importing `App\Domain\Jury\Models\
  JuryAssignment` into Graduation is fine — the arch rule only forbids Identity.)
- `app/Domain/Jury/Events/JuryAssigned.php` — `final implements ShouldBroadcast`,
  uses `Dispatchable`/`InteractsWithSockets`/`SerializesModels`, `readonly` promoted
  ctor `(JuryAssignment $jury)`; `broadcastOn(): [new PrivateChannel('student.'.
  $this->jury->student_id)]`; `broadcastAs(): 'jury.assigned'`; `broadcastWith():
  {student_id, president_professor_id, secretary_professor_id, vocal_professor_id,
  substitute_professor_id}` (substitute nullable). (Role labels live in i18n; the
  event carries ids only, matching the `DocumentStatusChanged` id-only style.)
- `app/Domain/Jury/Actions/SubmitPaymentAction.php` — `handle(SubmitPaymentData
  $data, Student $student): Student`. tx: `$student->payment_reference =
  $data->payment_reference; $student->paid_at = now(); $student->save();` return
  `$student`. **No** transition, **no** event. (Status must be `PaymentPending`; the
  controller resolves the student and the route is student-gated — assert
  `status === PaymentPending` defensively → `ValidationException` with
  `__('validation.payment.wrong_state')` if not.)
- `app/Domain/Jury/Actions/VerifyPaymentAction.php` — `handle(Student $student):
  Student`. tx: `$student->payment_verified = true; $student->save();` return
  `$student`. **No** transition, **no** event.
- `app/Domain/Jury/Actions/AssignJuryAction.php` — `handle(AssignJuryData $data,
  Student $student): JuryAssignment`. Constructor-inject `GraduationStateMachine`.
  tx:
  1. **Precondition guard:** if `! $student->payment_verified` throw
     `ValidationException::withMessages(['payment_verified' =>
     __('validation.jury.payment_unverified')])`.
  2. `$from = $student->status; $to = GraduationStatus::JuryAssigned;
     $this->stateMachine->assertCanTransition($from, $to);` (rejects any non-
     `PaymentPending` source — JURY-02 state guard).
  3. `$jury = JuryAssignment::create([... the five fields from $data ...]);`
     (the unique `student_id` makes a duplicate throw a QueryException; the DB CHECK
     is the last-line distinctness guard).
  4. `$student->status = $to; $student->save();`
  5. `StudentStatusChanged::dispatch($student, $from, $to);` and (D7)
     `JuryAssigned::dispatch($jury);`
  6. `return $jury;`
- `app/Domain/Jury/Data/SubmitPaymentData.php` — `#[TypeScript] final extends Data`.
  `payment_reference` `#[Required, StringType, Max(50)]` (col is `VARCHAR(50)`).
- `app/Domain/Jury/Data/AssignJuryData.php` — `#[TypeScript] final extends Data`:
  ```php
  #[Required, IntegerType, Exists('professors', 'id'),
    Different('secretary_professor_id'), Different('vocal_professor_id'),
    Different('substitute_professor_id')]
  public int $president_professor_id,
  #[Required, IntegerType, Exists('professors', 'id'),
    Different('vocal_professor_id'), Different('substitute_professor_id')]
  public int $secretary_professor_id,
  #[Required, IntegerType, Exists('professors', 'id'),
    Different('substitute_professor_id')]
  public int $vocal_professor_id,
  #[Nullable, IntegerType, Exists('professors', 'id')]
  public ?int $substitute_professor_id = null,
  ```
  > Pairwise `Different` across the four covers all six pairs without duplication
  > (president↔{sec,voc,sub}, secretary↔{voc,sub}, vocal↔sub). Laravel's `different`
  > passes when the compared field is null, so a null substitute never trips.
  > The `Exists` rule on a nullable field is skipped when null (Laravel default).

## 4. HTTP layer

- `app/Http/Controllers/Student/PaymentController.php` — `final`.
  - `index(Request $request): Response` → resolve the student (fail 404 if none,
    mirror `DocumentController::student()`), `Inertia::render('Student/Payment',
    [...])` (the §contract shape).
  - `submit(SubmitPaymentData $data, Request $request, SubmitPaymentAction $action):
    RedirectResponse` → `$action->handle($data, $this->student($request))` →
    `redirect()->route('student.payment.index')->with('success',
    __('payment.submitted'))`.
- `app/Http/Controllers/Graduation/JuryController.php` — `final`.
  - `index(): Response` → list students in `PaymentPending` (with payment columns +
    eager `program:id,name`/`graduationType:id,name`) **and** the professor catalog
    for the selects (`Professor::query()->orderBy('last_name')->get(['id',
    'first_name','last_name','mother_last_name'])->map(...)`), paginated students
    via `->through(fn (Student $s) => $this->mapStudent($s))`. `Inertia::render(
    'Graduation/JuryAssign', ['students' => ..., 'professors' => ...,
    'roles' => ...])` (see §contract).
  - `verifyPayment(Student $student, VerifyPaymentAction $action): RedirectResponse`
    → `$action->handle($student)` → `redirect()->back()->with('success',
    __('payment.verified'))`.
  - `assign(Student $student, AssignJuryData $data, AssignJuryAction $action):
    RedirectResponse` → `$action->handle($data, $student)` → `redirect()->back()
    ->with('success', __('jury.assigned'))`.
- **Routes** (`routes/web.php`, named, no closures), appended to the existing groups:
  - `role:student`: `GET /student/payment` `student.payment.index`; `POST
    /student/payment` `student.payment.submit`.
  - `role:admin,super_admin,secretary`: `GET /admin/graduation/jury`
    `admin.graduation.jury.index`; `POST /admin/graduation/jury/{student}/verify-payment`
    `admin.graduation.jury.verify-payment`; `POST /admin/graduation/jury/{student}/assign`
    `admin.graduation.jury.assign`.
- **Channel auth:** unchanged — `student.{studentId}` (slice 001) authorises the
  student and staff; both `StudentStatusChanged` and `JuryAssigned` broadcast there.
  Asserted, not modified.

## 5. Frontend (Inertia 2 + React 19 + TS)

- `resources/js/Pages/Student/Payment.tsx` — props snake_case (see §contract).
  Renders the payment-reference form (`useForm` posting to `student.payment.submit`),
  shows current `payment_reference`/`paid_at`/`payment_verified` (a badge:
  verified=success, awaiting=warning). Subscribes via `createEcho()` to
  `student.{student_id}` `.graduation.step.completed` and `.jury.assigned` to flip the
  page live once a jury is assigned (status → `jury_assigned`); cleanup on unmount
  (mirror `Student/Documents`). The `JuryRole`/`GraduationStatus` types are
  **type-only** imports; compare against raw string values cast to the type
  (`status === ('jury_assigned' as GraduationStatus)`), never value-import the enum.
- `resources/js/Pages/Graduation/JuryAssign.tsx` — admin table of `PaymentPending`
  students. Per student: a "verify payment" button (POST to verify-payment, only when
  not yet verified) and, once verified, a four-`<select>` jury form (President,
  Secretary, Vocal required + Substitute optional), each populated from the
  `professors` prop. **Client-side all-different guard:** disable the submit button
  (and surface an inline message) whenever any two non-empty selections collide;
  this mirrors the server `AssignJuryData` `Different` rules (server remains the
  authority). `useForm` posting to `admin.graduation.jury.assign`; `FormError` shows
  server session errors keyed to each professor field. Reuse `Components/form/
  {SelectField,FormError}` and the dialog/echo patterns from `Graduation/DocumentReview`
  / `Student/Documents`.
- i18n:
  - **UI copy** in `resources/locales/{es,en}.json`:
    `jury_role.president|secretary|vocal|substitute`; `payment.page.title|subtitle`,
    `payment.field.reference`, `payment.submit`, `payment.status.verified|awaiting`,
    `payment.paid_at`; `admin.jury.title|subtitle|empty`, `admin.jury.col.*`,
    `admin.jury.verify`, `admin.jury.role.*`, `admin.jury.assign`,
    `admin.jury.guard.distinct` (client all-different message), `admin.jury.substitute_none`.
  - **Flash + validation messages** in `lang/{es,en}.json`:
    `payment.submitted`, `payment.verified`, `jury.assigned`,
    `validation.payment.wrong_state`, `validation.jury.payment_unverified`.

## 6. Tests

- `tests/Unit/Jury/JuryRoleTest.php` — every case `labelKey()`/`color()`; string-backed.
- `tests/Unit/Graduation/GraduationStateMachineTest.php` — **extend**: 6→7 legal; a
  representative illegal jury edge (e.g. `AnnexIiiPending`→`JuryAssigned`, and
  `JuryAssigned`→`JuryAssigned`) throws `InvalidStatusTransitionException`.
- `tests/Feature/Jury/SubmitPaymentTest.php` — student in `PaymentPending` submits →
  `payment_reference` + `paid_at` set, `payment_verified` still false, status
  unchanged, NO `StudentStatusChanged` (`Event::fake`); missing/too-long reference →
  302 + `assertSessionHasErrors('payment_reference')`; non-student → 403; submitting
  from a non-`PaymentPending` state → guarded (302 session error or fail-loud per D6).
- `tests/Feature/Jury/VerifyPaymentTest.php` — admin/secretary verifies →
  `payment_verified = true`, status unchanged, NO `StudentStatusChanged`, no jury row;
  non-staff → 403.
- `tests/Feature/Jury/AssignJuryTest.php` — the core:
  - happy path (verified, `PaymentPending`, 4 distinct incl. substitute) → exactly one
    `jury_assignments` row, status `JuryAssigned`, `StudentStatusChanged(6→7)` +
    `JuryAssigned` dispatched (`Event::fake`), atomic.
  - happy path with **null substitute** → row created, substitute null, advance fires.
  - `payment_verified === false` → blocked (no row, status stays `PaymentPending`,
    no event); assert via response (302 session error on `payment_verified`) or that
    the Action throws.
  - wrong source state (e.g. `AnnexIiiPending`) → no row, no transition.
  - duplicate jury (student already assigned) → rejected, status unchanged.
  - two equal professor ids (president==vocal; vocal==substitute) → 302 +
    `assertSessionHasErrors` on the offending field; no row.
  - missing professor id / non-existent professor id → 302 session errors.
  - non-staff → 403.
- `tests/Feature/Jury/PaymentPagePropsTest.php` — Inertia contract for
  `Student/Payment`: `->component('Student/Payment')->where('student_id', …)
  ->where('status', 'payment_pending')->hasAll(['student_id','status',
  'payment_reference','paid_at','payment_verified'])`; status serialised as the enum
  **value** (string), never the object; 404 when the user has no Student.
- `tests/Feature/Jury/JuryAssignPagePropsTest.php` — Inertia contract for
  `Graduation/JuryAssign`: `->component('Graduation/JuryAssign')
  ->has('students')->has('professors')->has('roles')`; each student row shape
  (`id`, `control_number`, `full_name`, `program_name`, `graduation_type_name`,
  `payment_reference`, `paid_at`, `payment_verified`); each professor row
  (`id`, `full_name`); `roles` = the four `JuryRole` values.
- `tests/Feature/Jury/JuryAssignedBroadcastTest.php` (if D7 shipped) —
  `covers(JuryAssigned::class)`: build the event from an in-memory `JuryAssignment`
  with forced ids (no DB, mirror `DocumentStatusBroadcastTest`); assert channel
  `private-student.{id}`, name `jury.assigned`, `broadcastWith` keys/values
  (substitute id present and null variants).
- `tests/Arch/ArchitectureTest.php` — **extend**: `App\Domain\Jury` classes final;
  `App\Domain\Jury\Enums` string-backed; `App\Domain\Jury\Actions`/`Events` final;
  Domain ↛ Http (Jury included) and Domain ↛ Identity hold (add
  `App\Domain\Jury` not->toUse `App\Domain\Identity`); the existing
  `'domain code is final'`/`'strict types'` rules already cover `App\Domain\Jury`.
- `resources/js/Pages/Graduation/__tests__/JuryAssign.test.tsx` (Vitest) — the
  all-different guard: renders the four selects; picking the same professor twice
  disables submit + shows the distinct message; four distinct picks (with/without
  substitute) enables submit.
- All DB tests on **pgsql** (`RefreshDatabase`). Factories:
  - `JuryAssignmentFactory` — `student_id` => `Student::factory()->paymentVerified()`,
    four DISTINCT `Professor::factory()` ids (create four professors up front, assign
    distinct ids; do NOT `fake()->unique()` a small pool), `substitute_professor_id`
    => a fifth distinct professor (with a `withoutSubstitute()` state → null).
  - `StudentFactory` — **add** a `paymentPending()` state (status `PaymentPending`,
    `form_b_approved`/`annex_iii_completed` true, `documents_completed_at` set) and a
    `paymentVerified()` state (extends `paymentPending()` + `payment_reference`,
    `paid_at`, `payment_verified = true`). Reuse `ProfessorFactory`.

## 7. Infra
- Reuse slice-001/002 `docker-compose.yml` + `phpunit.xml` (`pgsql`,
  `BROADCAST_CONNECTION=null`). No new config, no new disk. `.gitignore` unchanged.

## 8. File manifest

**Create (~17):** 1 migration (`jury_assignments`) · `JuryRole` enum · `JuryAssignment`
model + `JuryAssignmentFactory` · `JuryAssigned` event · 3 Actions (SubmitPayment,
VerifyPayment, AssignJury) · 2 DTOs (SubmitPaymentData, AssignJuryData) · 2
controllers (Student\PaymentController, Graduation\JuryController) · 2 Inertia pages
(Student/Payment, Graduation/JuryAssign) · ~7 test files (incl. Vitest).
**Modify (~6):** `Student.php` (juryAssignment relation + PHPDoc) · `StudentFactory.php`
(paymentPending/paymentVerified states) · `routes/web.php` · `resources/locales/{es,en}.json`
· `lang/{es,en}.json` · `tests/Arch/ArchitectureTest.php` · extend
`tests/Unit/Graduation/GraduationStateMachineTest.php`.
**Generated:** `resources/js/types/generated.d.ts` via `typescript:transform`.
**No new ADR** (continues ADR-001). **No payment migration** (reuses existing columns).

## 9. Verify order (phase 5 — orchestrator runs the full suite; agents only self-check
PHPStan + tsc read-only per the build rules)
`composer format` → `composer analyse` (L9) → `typescript:transform` → `sail up -d` →
`sail artisan migrate:fresh` → `sail pest` (Unit+Arch+Feature on pgsql) → `pnpm tsc
--noEmit` → `pnpm lint` → `pnpm vitest run` → `pnpm build`. Evidence captured for the gate.

## 10. Constitution check
strict_types ✓ · explicit returns ✓ · final domain ✓ · readonly DTO/event ✓ · backed
enum `JuryRole` ✓ · Spatie Data only (no FormRequest) ✓ · Action + `DB::transaction` ✓
· anemic controllers ✓ · named routes / no closures ✓ · Domain ↛ Http/Identity ✓ ·
status mutations only via `GraduationStateMachine` ✓ · authorization in HTTP only ✓ ·
Pest + arch on Postgres ✓ · bigint ids per ADR-001 (flagged, not silently broken) ✓ ·
all-different enforced in DTO + DB CHECK ✓ · no payment migration (reuse columns) ✓ ·
reversible migration ✓ · no PII in factories ✓.
