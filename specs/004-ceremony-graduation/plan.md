# Plan 004 — Ceremony + Graduation completion (7→8→9) — the HOW

> Companion to `spec.md`. Grounded in a read-only recon of the COMPLETE slices
> 001/002/003 (the canonical pattern) + SPEC.md §3.3/§3.5/§3.6/§6.3.11/§9. Gated on
> `CLAUDE.md §No-negociables`. Mirrors plan 003's structure VERBATIM. **This slice
> COMPLETES the 9-state pipeline — `Graduated` is terminal.**

## 0. Decisions & flagged conflicts (resolve at implement)

| # | Topic | Decision | Why / flag |
|---|---|---|---|
| D1 | **ID strategy** | `diploma_folio_sequences.id` = bigint `$table->id()`; `program_id` = `foreignId`. | Continues ADR-001 (001/002/003). |
| D2 | **Sequence table** | Add `diploma_folio_sequences` (`program_id`, `year`, `last_value`, UNIQUE `(program_id, year)`). The ONLY migration. | Naive `count()` is not race-safe; a locked counter row is the minimal correct mechanism. SPEC §9 row 12 mandates testing folio sequence generation. |
| D3 | **Race-safe folio** | `firstOrCreate((program_id,year))` then re-fetch with `lockForUpdate()`, `last_value++`, save — all INSIDE `MarkAsGraduatedAction`'s `DB::transaction`. | Two concurrent same-program-same-year graduations serialize on the row lock → distinct sequential folios. |
| D4 | **`CeremonyDate` rule** | A custom `App\Rules\CeremonyDate` (`ValidationRule`) wired via `#[Rule(new CeremonyDate)]` on `ScheduleCeremonyData::$ceremony_date`, plus `#[Date]`. Asserts future + weekday (N 1..5) + business hours (08:00 ≤ t < 18:00). | SPEC §2538 names one cohesive rule. One pure-logic Unit test; the DTO stays the validation SSOT. |
| D5 | **`MarkAsGraduatedData` — none** | `MarkAsGraduatedAction::handle(Student)` takes NO DTO. Folio/book/sheet are server-generated; `graduation_date = today()`. | No client input → no DTO (KISS). The "ceremony passed" precondition is a domain guard, not user input. |
| D6 | **Precondition guard placement** | `MarkAsGraduatedAction` asserts `ceremony_date !== null && ! ceremony_date->isFuture()` → `ValidationException` (i18n) BEFORE the state machine. `ScheduleCeremonyAction` relies on the DTO `CeremonyDate` rule for date validity; the state machine guards the source state for both. | SPEC §3.6 CERE-02. Domain-level, inside the transaction, fail-loud. Authorization stays in HTTP. |
| D7 | **Two new broadcasts** | Ship `CeremonyScheduled` (`ceremony.scheduled`) + `StudentGraduated` (`student.graduated`), both private `student.{id}`. `StudentStatusChanged` still fires for the progress bar (terminal payload handled by `isTerminal()`). | Symmetry with 002/003. If the gate declines, drop the events + their tests + the student `.listen()`s. |
| D8 | **New domain namespace** | Ceremony code lives in `App\Domain\Ceremony\{Data,Actions,Events,Services,Models}` (the scaffolded `app/Domain/Ceremony/*` dirs). Ceremony MAY import Graduation (Student, GraduationStatus, state machine, StudentStatusChanged) + Academic (Program); Ceremony ↛ Identity (one-way). | SPEC §5 layout. The "Ceremony only uses Shared" arch sketch (§11.2) is NOT adopted — it contradicts the cross-domain reality (spec Open Q C). |
| D9 | **Single-student scheduling** | One student per request. Action is loop-friendly for a later multi-assign controller. | CERE-01 multi-assign deferred (spec §2 DEFERRED). |
| D10 | **No `students` migration** | Reuse `ceremony_date`/`ceremony_location`/`diploma_folio`/`record_book`/`record_sheet`/`graduation_date` (slice-001 migration, casts already present: `ceremony_date`→datetime, `graduation_date`→date). | SPEC §6.3.11; the prompt mandates reuse. |

## 1. Architecture (unchanged from 001/002/003 conventions)

Request lifecycle: **Controller (≤15 lines) → Spatie Data DTO (validation SSOT,
for the schedule path) → one Action `handle(...)` (`DB::transaction`, dispatches
event[s]) → redirect-back / `Inertia::render` with snake_case `->only()`/`->map()`
props.** Domain never touches HTTP or Identity. Status mutations **only** through
`GraduationStateMachine` (exists; edges 7→8 + 8→9 already legal). Ceremony-column
mutations only inside `ScheduleCeremonyAction`; folio/book/sheet/graduation-date
only inside `MarkAsGraduatedAction` (with the sequence lock inside its transaction).
Broadcast on the existing private `student.{id}` channel: `StudentStatusChanged`
(reused) for both advances, `CeremonyScheduled` + `StudentGraduated` (new) for detail.

## 2. Data model

### 2.1 `diploma_folio_sequences` — migration `2026_06_19_000050_create_diploma_folio_sequences_table`
- `id` bigint `$table->id()`.
- `program_id` `foreignId` → programs **restrictOnDelete** (`->constrained('programs')`).
- `year` `unsignedSmallInteger('year')`.
- `last_value` `unsignedInteger('last_value')->default(0)`.
- `timestamps()`.
- **UNIQUE** `(program_id, year)`: `$table->unique(['program_id', 'year']);` (the
  per-program-per-year counter is a singleton row).
- `down()`: `Schema::dropIfExists('diploma_folio_sequences')`.

> No `softDeletes` — a counter row is operational state, not a historical record.
> No `students` change. (Note: SPEC §6.3.11 lists a `[status, ceremony_date]` index
> that slice 001 did not implement; adding it retroactively is OUT of this slice's
> scope — flagged only.)

### 2.2 Migration order
After `2026_06_19_000040_create_jury_assignments_table` (slice 003), add
`2026_06_19_000050_create_diploma_folio_sequences_table` (needs `programs`, already
migrated). No other migration.

## 3. Domain layer (`app/Domain/Ceremony` + reuse of `app/Domain/Graduation`)

**Exists (reuse):** `Graduation\Enums\GraduationStatus` (edges 7→8 + 8→9 legal,
`isTerminal()` for Graduated), `Graduation\StateMachine\GraduationStateMachine`,
`Graduation\Exceptions\InvalidStatusTransitionException` (already mapped to a
graceful 302 in `bootstrap/app.php` — DO NOT re-handle), `Graduation\Models\Student`
(ceremony/graduation columns + casts present), `Graduation\Events\StudentStatusChanged`,
`Academic\Models\Program` (`code` column, VARCHAR(20), present).

**Create:**

- `app/Rules/CeremonyDate.php` — `final` `implements ValidationRule`. `validate(
  string $attribute, mixed $value, Closure $fail): void`: parse `$value` to Carbon
  (guard unparseable → `$fail('validation.ceremony.invalid')`); if
  `! $date->isFuture()` → `$fail('validation.ceremony.not_future')`; if
  `$date->isWeekend()` (Sat/Sun) → `$fail('validation.ceremony.not_weekday')`; if
  `$date->hour < 8 || $date->hour >= 18` → `$fail('validation.ceremony.out_of_hours')`.
  (Boundary: 08:00 valid, 18:00 invalid — D4/spec Open Q F. `hour >= 18` rejects
  18:00–18:59; minute granularity below 18:00 is allowed.) Pure logic → Unit test.
  > `App\Rules` is HTTP-validation infrastructure, NOT a domain class — it is
  > referenced from the DTO and may use Carbon; it is excluded from the
  > `App\Domain` arch rules. (Mirrors how Spatie validation attributes live outside
  > the domain.)
- `app/Domain/Ceremony/Data/ScheduleCeremonyData.php` — `#[TypeScript] final extends
  Data`:
  ```php
  public function __construct(
      #[Required, Date, Rule(new CeremonyDate)]
      public string $ceremony_date,
      #[Required, StringType, Max(200)]
      public string $ceremony_location,
  ) {}
  ```
  > `#[Date]` is the Spatie attribute; `#[Rule(new CeremonyDate)]` injects the
  > custom rule instance (same `Rule` raw-rule attribute slice 003 used for the
  > pairwise `different`s). The DTO is the validation SSOT and feeds the generated
  > TS type for the client mirror.
- `app/Domain/Ceremony/Services/DiplomaFolioGenerator.php` — `final`.
  `generate(Student $student): string`:
  ```php
  $year = (int) now()->year;
  $program = $student->program;                  // NOT NULL FK (eager or lazy ok)
  $sequence = DiplomaFolioSequence::query()
      ->where('program_id', $program->id)
      ->where('year', $year)
      ->lockForUpdate()
      ->first()
      ?? DiplomaFolioSequence::create([
          'program_id' => $program->id, 'year' => $year, 'last_value' => 0,
      ]);
  // Re-lock the freshly created row to be safe under the surrounding tx:
  $sequence = DiplomaFolioSequence::query()
      ->whereKey($sequence->id)->lockForUpdate()->first();
  $next = $sequence->last_value + 1;
  $sequence->last_value = $next;
  $sequence->save();
  $seq = str_pad((string) $next, 3, '0', STR_PAD_LEFT);   // 001, 002, …
  return sprintf('%d-%s-%s', $year, $program->code, $seq);  // {YEAR}-{CODE}-{SEQ}
  ```
  > MUST be called INSIDE `MarkAsGraduatedAction`'s `DB::transaction` so the
  > `lockForUpdate()` holds for the whole graduation. `record_book` / `record_sheet`
  > are derived from the same `$year` + `$seq` by the Action (book = `(string)$year`,
  > sheet = `$seq`) — see the Action below. (`firstOrCreate` then `lockForUpdate`
  > re-fetch avoids the lost-update race on the create path.)
- `app/Domain/Ceremony/Models/DiplomaFolioSequence.php` — `final`, `HasFactory`,
  `$fillable = ['program_id','year','last_value']`, `@property int $id`,
  `@property int $program_id`, `@property int $year`, `@property int $last_value`,
  `@property-read Program $program`; `program(): BelongsTo<Program,$this>`;
  `newFactory()`. + `DiplomaFolioSequenceFactory`.
- `app/Domain/Ceremony/Events/CeremonyScheduled.php` — `final implements
  ShouldBroadcast`, `Dispatchable`/`InteractsWithSockets`/`SerializesModels`,
  `readonly` promoted ctor `(Student $student)`;
  `broadcastOn(): [new PrivateChannel('student.'.$this->student->id)]`;
  `broadcastAs(): 'ceremony.scheduled'`;
  `broadcastWith(): {student_id, ceremony_date (ISO-8601 or null), ceremony_location
  (string|null), status (string)}`.
- `app/Domain/Ceremony/Events/StudentGraduated.php` — same shape, ctor `(Student
  $student)`; `broadcastAs(): 'student.graduated'`;
  `broadcastWith(): {student_id, diploma_folio (string|null), graduation_date
  (ISO-8601 date or null), status (string)}`.
- `app/Domain/Ceremony/Actions/ScheduleCeremonyAction.php` — `final`,
  ctor-inject `GraduationStateMachine`. `handle(ScheduleCeremonyData $data, Student
  $student): Student`:
  ```php
  return DB::transaction(function () use ($data, $student): Student {
      $from = $student->status;
      $to = GraduationStatus::CeremonyScheduled;
      $this->stateMachine->assertCanTransition($from, $to);   // source must be JuryAssigned
      $student->ceremony_date = Carbon::parse($data->ceremony_date);
      $student->ceremony_location = $data->ceremony_location;
      $student->status = $to;
      $student->save();
      StudentStatusChanged::dispatch($student, $from, $to);
      CeremonyScheduled::dispatch($student);
      return $student;
  });
  ```
- `app/Domain/Ceremony/Actions/MarkAsGraduatedAction.php` — `final`, ctor-inject
  `GraduationStateMachine` + `DiplomaFolioGenerator`. `handle(Student $student):
  Student`:
  ```php
  return DB::transaction(function () use ($student): Student {
      $ceremonyDate = $student->ceremony_date;
      if ($ceremonyDate === null || $ceremonyDate->isFuture()) {
          throw ValidationException::withMessages([
              'ceremony_date' => __('validation.ceremony.not_passed'),
          ]);
      }
      $from = $student->status;
      $to = GraduationStatus::Graduated;
      $this->stateMachine->assertCanTransition($from, $to);   // source must be CeremonyScheduled
      $year = (int) now()->year;
      $folio = $this->folioGenerator->generate($student);     // {YEAR}-{CODE}-{SEQ}, locked
      $seq = (string) str(...)  // the SEQ portion; see note
      $student->diploma_folio = $folio;
      $student->record_book = (string) $year;                 // derived (D-spec E)
      $student->record_sheet = /* the zero-padded SEQ from the folio */;
      $student->graduation_date = now()->toDateString();
      $student->status = $to;
      $student->save();
      StudentStatusChanged::dispatch($student, $from, $to);   // next_step=null (terminal) auto
      StudentGraduated::dispatch($student);
      return $student;
  });
  ```
  > **record_book/record_sheet derivation:** to avoid re-parsing the folio, have
  > `DiplomaFolioGenerator::generate()` return a small `readonly` result object /
  > or expose `$year` + `$seq` so the Action sets `record_book = (string)$year`,
  > `record_sheet = $seq`. Implementer's choice: either a value object
  > `DiplomaFolio(folio, book, sheet)` returned by the generator (cleanest), or the
  > generator returns the string and the Action splits on `-`. Prefer the value
  > object so the Action stays a one-liner and the format lives in one place.

## 4. HTTP layer

- `app/Http/Controllers/Graduation/CeremonyController.php` — `final extends
  Controller`.
  - `index(): Response` → two queues:
    - students at `JuryAssigned` (eager `program:id,name`, `graduationType:id,name`),
      mapped to the schedule-row shape (see §contract);
    - students at `CeremonyScheduled`, mapped to the graduate-row shape (carrying
      `ceremony_date` ISO + a server-computed `can_graduate` boolean = ceremony has
      passed). Paginate (`->paginate(15)->through(...)`). `Inertia::render(
      'Graduation/CeremonySchedule', ['scheduling' => ..., 'graduating' => ...])`.
  - `schedule(Student $student, ScheduleCeremonyData $data, ScheduleCeremonyAction
    $action): RedirectResponse` → `$action->handle($data, $student)` →
    `redirect()->back()->with('success', __('ceremony.scheduled'))`.
  - `graduate(Student $student, MarkAsGraduatedAction $action): RedirectResponse` →
    `$action->handle($student)` → `redirect()->back()->with('success',
    __('ceremony.graduated'))`.
  > Resource resolution: the `{student}` route-model-binding is fine (staff act on
  > any student); no client-supplied child ids. Keep each method ≤15 lines; push the
  > row-mapping into private `mapScheduling()` / `mapGraduating()` helpers like
  > `JuryController::mapStudent()`.
- **Routes** (`routes/web.php`, named, no closures), appended to the existing
  `role:admin,super_admin,secretary` group:
  - `GET /admin/graduation/ceremony` → `admin.graduation.ceremony.index`.
  - `POST /admin/graduation/ceremony/{student}/schedule` →
    `admin.graduation.ceremony.schedule`.
  - `POST /admin/graduation/ceremony/{student}/graduate` →
    `admin.graduation.ceremony.graduate`.
- **Channel auth:** unchanged — `student.{studentId}` (slice 001) authorises student
  + staff; `CeremonyScheduled` + `StudentGraduated` broadcast there. Asserted, not
  modified.

## 5. Frontend (Inertia 2 + React 19 + TS)

- `resources/js/Pages/Graduation/CeremonySchedule.tsx` — props snake_case
  (`scheduling`, `graduating` paginators). For each `scheduling` row: a `useForm`
  posting `ScheduleCeremonyData` (`ceremony_date` via a `datetime-local` input,
  `ceremony_location` via `TextField`) to `admin.graduation.ceremony.schedule`;
  **client mirror** — disable submit unless the picked datetime is future + weekday
  (`new Date(v).getDay()` ∈ 1..5) + business hours (hours 8..17), surfacing an
  inline message; `FormError` shows server session errors keyed `ceremony_date` /
  `ceremony_location`. For each `graduating` row: a "Mark as graduated" button
  (`router.post` to `admin.graduation.ceremony.graduate`), **disabled** unless the
  row's `can_graduate` is true (ceremony passed). Reuse `Components/form/
  {TextField,FormError}` + the dialog/echo patterns from `Graduation/JuryAssign`.
  `GraduationStatus` is a **type-only** import; compare raw strings cast to the type.
  Dark/light + ES/EN, WCAG 2.2 AA.
- **OPTIONAL student live signal** (spec Open Q B): extend `Student/Payment.tsx` (or
  `Student/Status.tsx`) Echo lifecycle — add `.listen('.ceremony.scheduled', …)` and
  `.listen('.student.graduated', …)` with matching `stopListening` in cleanup, so the
  student's live view reflects the final two advances. No new student page.
- i18n:
  - **UI copy** in `resources/locales/{es,en}.json`:
    `admin.ceremony.title|subtitle`, `admin.ceremony.scheduling.title|empty`,
    `admin.ceremony.graduating.title|empty`, `admin.ceremony.col.*`,
    `admin.ceremony.field.date`, `admin.ceremony.field.location`,
    `admin.ceremony.schedule`, `admin.ceremony.graduate`,
    `admin.ceremony.guard.future|weekday|hours` (client mirror messages),
    `admin.ceremony.guard.not_passed` (graduate-disabled hint),
    `admin.ceremony.folio`, `admin.ceremony.ceremony_date`.
  - **Flash + validation messages** in `lang/{es,en}.json`:
    `ceremony.scheduled`, `ceremony.graduated`,
    `validation.ceremony.invalid`, `validation.ceremony.not_future`,
    `validation.ceremony.not_weekday`, `validation.ceremony.out_of_hours`,
    `validation.ceremony.not_passed`.
    > EVERY `__('key')` a controller flashes or an Action/rule throws MUST exist in
    > BOTH `lang/es.json` and `lang/en.json` (the memory rule).

## 6. Tests

- `tests/Unit/Ceremony/CeremonyDateRuleTest.php` — pure logic on `App\Rules\
  CeremonyDate`: a future weekday 10:00 passes (no `$fail`); past date → `not_future`;
  a future Saturday → `not_weekday`; a future weekday 07:59 → `out_of_hours`; 18:00 →
  `out_of_hours`; 08:00 + 17:59 pass. (Drive the rule via a tiny harness collecting
  `$fail` keys — no DB.)
- `tests/Unit/Graduation/GraduationStateMachineTest.php` — **extend**: 7→8 + 8→9
  legal; representative illegal edges throw `InvalidStatusTransitionException`
  (e.g. `JuryAssigned`→`Graduated` skip; `CeremonyScheduled`→`JuryAssigned`;
  `Graduated`→`CeremonyScheduled`). Assert `GraduationStatus::Graduated->isTerminal()`
  is true and `allowedTransitions()` is `[]`.
- `tests/Feature/Ceremony/ScheduleCeremonyTest.php` — happy (7→8): `ceremony_date`
  + `ceremony_location` set, status `CeremonyScheduled`, `StudentStatusChanged(7→8)`
  + `CeremonyScheduled` dispatched (`Event::fake`), atomic; past/weekend/out-of-hours
  date → 302 + `assertSessionHasErrors('ceremony_date')`, no mutation; missing
  location → 302 + `assertSessionHasErrors('ceremony_location')`; wrong source state
  (e.g. `PaymentPending`) → graceful 302, no mutation; non-staff → 403.
- `tests/Feature/Ceremony/ScheduleCeremonyDataTest.php` — `covers(ScheduleCeremonyData
  ::class)`, in `tests/Feature/` (uses container/DB): a future-weekday-business-hours
  payload passes `ScheduleCeremonyData::validate(...)`; each of past / weekend /
  before-08:00 / 18:00 / missing-location throws `ValidationException` keyed on the
  right field.
- `tests/Feature/Ceremony/MarkAsGraduatedTest.php` — the core:
  - happy (8→9, terminal): with `ceremony_date` in the past, `status = Graduated`,
    `graduation_date = today`, `diploma_folio` matches `^\d{4}-[A-Z0-9]+-\d{3}$`,
    `record_book`/`record_sheet` set, `StudentStatusChanged(8→9)` (assert its
    `broadcastWith()['next_step']` is `null`) + `StudentGraduated` dispatched, atomic.
  - blocked when `ceremony_date` is still in the future (or null) → 302 +
    `assertSessionHasErrors('ceremony_date')`, no folio minted, status unchanged.
  - wrong source state (e.g. `JuryAssigned`) → graceful 302, no mutation.
  - non-staff → 403.
- `tests/Feature/Ceremony/DiplomaFolioGeneratorTest.php` — `covers(DiplomaFolio
  Generator::class)` (DB-backed): format `{YEAR}-{CODE}-{SEQ}` with the program's
  `code`; sequential `001` → `002` → `003` for the same program+year; a DIFFERENT
  program (or same program, different year via a seeded sequence row) restarts at
  `001`; **race-safety** — graduate two `CeremonyScheduled` students of the SAME
  program back-to-back inside the test and assert the two folios differ and increment
  (the lock prevents a collision; assert distinct + `…-001`/`…-002`).
- `tests/Feature/Ceremony/CeremonySchedulePagePropsTest.php` — Inertia contract:
  `->component('Graduation/CeremonySchedule')->has('scheduling.data')
  ->has('graduating.data')`; each scheduling row shape (`id`, `control_number`,
  `full_name`, `program_name`, `graduation_type_name`); each graduating row
  (`id`, `control_number`, `full_name`, `ceremony_date` ISO, `ceremony_location`,
  `can_graduate` boolean); status serialised as the enum **value** string.
- `tests/Feature/Ceremony/CeremonyScheduledBroadcastTest.php` +
  `tests/Feature/Ceremony/StudentGraduatedBroadcastTest.php` — `covers(...)`: build
  the event from an in-memory `Student` with forced ids/attributes (no DB; mirror
  `JuryAssignedBroadcastTest`); assert channel `private-student.{id}`, the event name
  (`ceremony.scheduled` / `student.graduated`), and the `broadcastWith` keys/values.
- `tests/Arch/ArchitectureTest.php` — **extend**: `App\Domain\Ceremony` classes
  final; `App\Domain\Ceremony\Actions`/`Events` final; `Ceremony ↛ Identity`. (Do
  NOT add the literal "Ceremony only uses Shared" rule — spec Open Q C. `App\Rules`
  is HTTP infra, not `App\Domain`, so the Domain↛Http rule does not apply to it; if
  `CeremonyDate` ever imported Carbon-via-`Illuminate\Http` it would, but it does not.)
- `resources/js/Pages/Graduation/__tests__/CeremonySchedule.test.tsx` (Vitest):
  the client date guard — a past datetime / a weekend / 07:30 / 18:30 disables the
  schedule submit + shows the matching guard message; a future weekday 10:00 enables
  it. The graduate button is disabled when `can_graduate` is false and enabled when
  true. (Stateful `useForm` mock + mocked `router.post`, mirroring the JuryAssign
  Vitest harness.)
- All DB tests on **pgsql** (`RefreshDatabase`). Factories:
  - `StudentFactory` — **add** `juryAssigned()` (status `JuryAssigned`, builds on
    `paymentVerified()` + a `juryAssignment`? — at minimum the status + the upstream
    flags) and `ceremonyScheduled()` (status `CeremonyScheduled`, `ceremony_date` set
    to a FUTURE weekday business-hours datetime + `ceremony_location`) and
    `ceremonyPassed()` (extends `ceremonyScheduled()` but `ceremony_date` in the PAST,
    so `MarkAsGraduatedAction` is allowed). Reuse the existing `paymentVerified()`
    chain. Do NOT `fake()->unique()` a small fixed pool.
  - `DiplomaFolioSequenceFactory` — `program_id` => `Program::factory()`, `year` =>
    `now()->year`, `last_value` => 0 (+ a `withValue(int)` state for the reset test).

## 7. Infra
- Reuse 001/002/003 `docker-compose.yml` + `phpunit.xml` (`pgsql`,
  `BROADCAST_CONNECTION=null`). No new config, no new disk. `.gitignore` unchanged.

## 8. File manifest

**Create (~18):** 1 migration (`diploma_folio_sequences`) · `App\Rules\CeremonyDate` ·
`ScheduleCeremonyData` DTO · `DiplomaFolioGenerator` service (+ optional `DiplomaFolio`
VO) · `DiplomaFolioSequence` model + `DiplomaFolioSequenceFactory` · 2 events
(`CeremonyScheduled`, `StudentGraduated`) · 2 Actions (`ScheduleCeremonyAction`,
`MarkAsGraduatedAction`) · 1 controller (`Graduation\CeremonyController`) · 1 Inertia
page (`Graduation/CeremonySchedule`) · ~10 test files (incl. Vitest).
**Modify (~6):** `StudentFactory.php` (juryAssigned/ceremonyScheduled/ceremonyPassed
states) · `routes/web.php` · `resources/locales/{es,en}.json` · `lang/{es,en}.json` ·
`tests/Arch/ArchitectureTest.php` · extend `tests/Unit/Graduation/
GraduationStateMachineTest.php` · (optional) `Student/Payment.tsx` echo listeners.
**Generated:** `resources/js/types/generated.d.ts` via `typescript:transform`.
**No new ADR** (continues ADR-001). **No `students` migration** (reuses columns).

## 9. Verify order (phase 5 — orchestrator runs the full suite; agents only self-check
PHPStan + tsc read-only per the build rules)
`composer format` → `composer analyse` (L9) → `typescript:transform` → `sail up -d` →
`sail artisan migrate:fresh` → `sail pest` (Unit+Arch+Feature on pgsql) → `pnpm tsc
--noEmit` → `pnpm lint` → `pnpm vitest run` → `pnpm build`. Evidence captured for the gate.

## 10. Constitution check
strict_types ✓ · explicit returns ✓ · final domain ✓ · readonly DTO/event ✓ · backed
enum reuse (`GraduationStatus`) ✓ · Spatie Data only (`ScheduleCeremonyData`; no
FormRequest) ✓ · Action + `DB::transaction` ✓ · folio lock INSIDE the tx ✓ · anemic
controllers ✓ · named routes / no closures ✓ · Domain ↛ Http/Identity (`App\Rules` is
HTTP infra, excluded) ✓ · status mutations only via `GraduationStateMachine` ✓ ·
authorization in HTTP only ✓ · Pest + arch on Postgres ✓ · bigint ids per ADR-001 ✓ ·
race-safe folio (`lockForUpdate`, not `count()`) ✓ · dates as casts (no float) ✓ ·
reversible migration ✓ · no PII in factories ✓ · **completes the 9-state pipeline;
Graduated terminal** ✓.
