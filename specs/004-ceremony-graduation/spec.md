# Spec 004 — Ceremony + Graduation completion (7→8→9, the FINAL stretch)

> **Phase:** Specify (the WHAT and WHY, not the HOW).
> **SSOT:** `SPEC.md` — §3.3 state machine transitions **7→8** and **8→9** (both
> already legal in `GraduationStatus::allowedTransitions()`); §3.6 Ceremony Domain
> (CERE-01 schedule, CERE-02 graduate); §3.5/GRAD-03 + GRAD-03 folio rule
> (`{YEAR}-{PROGRAM_CODE}-{SEQUENTIAL}`, unique per program per year, terminal);
> §6.3.11 `students` columns `ceremony_date`/`ceremony_location`/`diploma_folio`/
> `record_book`/`record_sheet`/`graduation_date` (ALL already migrated in slice
> 001); §6 routes rows 1635-1636 (`/admin/ceremony` + graduate); §9 test matrix
> row 12 (diploma folio sequential generation, Unit, Ceremony); the `CeremonyDate`
> validation rule (§2538: future, weekday Mon-Fri, business hours 8AM-6PM).
> **Builds on:** spec/plan 001 (Form B — canonical pattern), 002 (Documents), 003
> (Jury — payment + 6→7 advance, the immediately-preceding slice; this slice
> mirrors its structure VERBATIM).
> **Status:** DRAFT — awaiting human review gate before `/sdd-plan`.

## 1. Problem & why

Assigning the jury (slice 003) lands a student at `JuryAssigned` (state 7). The
**final two legs** of the 9-state machine remain unbuilt:

- **7 → 8 (Schedule Ceremony).** Staff set a ceremony date + location and advance
  `JuryAssigned (7) → CeremonyScheduled (8)`. SPEC §3.6 CERE-01: the date must be
  a **future** date, a **weekday** (Mon-Fri), within **business hours** (08:00–18:00).
- **8 → 9 (Graduate).** Staff mark the student graduated once the ceremony date has
  **passed**: status → `Graduated (9)`, `graduation_date = today`, a **diploma folio**
  is generated (`{YEAR}-{PROGRAM_CODE}-{SEQUENTIAL}`, unique per program per year),
  and a `record_book` / `record_sheet` are assigned. `Graduated` is the **TERMINAL**
  state — `allowedTransitions()` returns `[]`, `isTerminal()` is `true`.

The foundation already has **both enum edges legal** (`PaymentPending`→…→
`JuryAssigned → CeremonyScheduled → Graduated`, verified in `GraduationStatus`)
**and all six target columns** on `students` (slice-001 migration; `ceremony_date`
cast `datetime`, `graduation_date` cast `date`). What is missing:

- **Ceremony scheduling.** No `ScheduleCeremonyAction`, no `ScheduleCeremonyData`
  DTO with the future/weekday/business-hours rules, no `CeremonyScheduled` event.
- **Graduation completion.** No `MarkAsGraduatedAction`, no race-safe diploma-folio
  generator, no `StudentGraduated` event.
- **The HTTP/React surface.** No `Graduation\CeremonyController`, no
  `Graduation/CeremonySchedule` admin page, no routes, no lang keys.

This slice builds the **fourth (and final) vertical slice** end-to-end and
**COMPLETES the 9-state pipeline**. After it ships, a student can travel the full
`FormBPending (1) → … → Graduated (9)` road. Same conventions as 001/002/003:
controller (≤15 lines) → Spatie Data DTO (validation SSOT) → one Action
(`DB::transaction`, dispatch event) → redirect/`Inertia::render` with snake_case
props; status mutated **only** through `GraduationStateMachine`; broadcast on the
existing private `student.{id}` channel.

**Diploma/certificate DOCUMENT generation (the PDF/Word diploma, the judge
`.docx` certificates) is explicitly DEFERRED to a later slice** (SPEC §3.7 CERT-01,
§9 ANNEX notes; `barryvdh/laravel-dompdf` / `phpoffice/phpword`), mirroring how
001/002/003 carved generation out. This slice persists the folio **string** and the
state advance; it does not render a document.

## 2. Scope

### In scope

1. **`ScheduleCeremonyData` DTO** (`app/Domain/Ceremony/Data/ScheduleCeremonyData.php`)
   — `#[TypeScript] final extends Data`. Fields:
   - `ceremony_date` (string `Y-m-d H:i` / ISO): server-side rules express the
     `CeremonyDate` invariant (SPEC §2538) — **future** + **weekday** + **business
     hours**. Built from `#[Date, After('now')]` plus a **custom `CeremonyDate`
     validation rule** (`app/Rules/CeremonyDate.php`, an `Illuminate\Contracts\
     Validation\ValidationRule`) wired via `#[Rule(new CeremonyDate)]` that asserts
     `N` ∈ 1..5 (Mon-Fri) AND hour ∈ 8..17 inclusive (08:00–17:59 are inside
     business hours; the window is `>= 08:00` and `< 18:00`). A custom rule (not a
     stack of attributes) keeps the three sub-checks cohesive and emits one i18n
     message per failing facet.
   - `ceremony_location` (`#[Required, StringType, Max(200)]`; col is VARCHAR(200)).
2. **`ScheduleCeremonyAction`** (`app/Domain/Ceremony/Actions/ScheduleCeremonyAction.php`)
   — `final`, `handle(ScheduleCeremonyData $data, Student $student): Student`.
   In one `DB::transaction`:
   - `$from = $student->status; $to = GraduationStatus::CeremonyScheduled;`
   - `$this->stateMachine->assertCanTransition($from, $to);` (rejects any source
     ≠ `JuryAssigned` — the only legal predecessor of `CeremonyScheduled`; an
     illegal source surfaces as the already-mapped graceful 302).
   - set `ceremony_date` (a `Carbon`) + `ceremony_location`, `status = $to`, save.
   - `StudentStatusChanged::dispatch($student, $from, $to)` (reused, drives the
     progress bar) **and** `CeremonyScheduled::dispatch($student)` (new broadcast).
   - return `$student`. **No** migration; the DTO is the validation SSOT.
3. **`MarkAsGraduatedAction`** (`app/Domain/Ceremony/Actions/MarkAsGraduatedAction.php`)
   — `final`, `handle(Student $student): Student`. **No input DTO** (no client data;
   folio + book/sheet are server-generated). Constructor-injects
   `GraduationStateMachine` and `DiplomaFolioGenerator`. In one `DB::transaction`:
   - **Precondition guard (CERE-02):** if `ceremony_date` is null OR
     `ceremony_date->isFuture()` (the ceremony has NOT passed), throw
     `ValidationException::withMessages(['ceremony_date' =>
     __('validation.ceremony.not_passed')])`. (`now()` ≥ `ceremony_date` is required.)
   - `$from = $student->status; $to = GraduationStatus::Graduated;`
   - `$this->stateMachine->assertCanTransition($from, $to);` (rejects any source ≠
     `CeremonyScheduled`).
   - `$folio = $this->folioGenerator->generate($student);` — the **race-safe**
     `{YEAR}-{PROGRAM_CODE}-{SEQ}` algorithm (see §below + plan §2/§3).
   - set `diploma_folio = $folio`, `record_book`, `record_sheet`,
     `graduation_date = today()`, `status = Graduated`, save.
   - `StudentStatusChanged::dispatch($student, $from, $to)` (terminal: its
     `broadcastWith()` already emits `next_step = null` because
     `Graduated::isTerminal()` is true — DO NOT special-case it) **and**
     `StudentGraduated::dispatch($student)` (new broadcast).
   - return `$student`.
4. **`DiplomaFolioGenerator` service** (`app/Domain/Ceremony/Services/
   DiplomaFolioGenerator.php`) — `final`, `generate(Student $student): string`.
   The folio is `{YEAR}-{PROGRAM_CODE}-{SEQ}`, **unique per program per year**, and
   the SEQ **must be race-safe** (no naive `count()+1`). The chosen mechanism is a
   dedicated **`diploma_folio_sequences` table** with a UNIQUE `(program_id, year)`
   row carrying `last_value`, mutated under `lockForUpdate()` inside the **same
   transaction** as `MarkAsGraduatedAction` (so two concurrent graduations on the
   same program+year serialize and never collide). See "Race-safe folio" below.
   `record_book` / `record_sheet` are derived deterministically from the same SEQ
   (e.g. book = the year, sheet = the zero-padded SEQ) — see plan §3 for the exact
   format; they are NOT free-text staff input in this slice.
5. **`CeremonyScheduled` event** (`app/Domain/Ceremony/Events/CeremonyScheduled.php`)
   — `final implements ShouldBroadcast`, `readonly` promoted ctor `(Student $student)`,
   private `student.{id}`, name **`ceremony.scheduled`**, `broadcastWith()` =
   `{student_id, ceremony_date (ISO-8601), ceremony_location, status}`.
6. **`StudentGraduated` event** (`app/Domain/Ceremony/Events/StudentGraduated.php`)
   — `final implements ShouldBroadcast`, `readonly` promoted ctor `(Student $student)`,
   private `student.{id}`, name **`student.graduated`**, `broadcastWith()` =
   `{student_id, diploma_folio, graduation_date (ISO-8601 date), status}`.
7. **`diploma_folio_sequences` migration** (the ONLY migration) — `bigint id`
   (ADR-001); `program_id` FK→programs **RESTRICT**; `year` SMALLINT/INTEGER;
   `last_value` INTEGER default 0; UNIQUE `(program_id, year)`; timestamps. Reversible
   `down()`. No `students` change (the six columns already exist). See plan §2.
8. **`DiplomaFolioSequence` model + factory** — `final`, `HasFactory`, `$fillable`,
   `@property` PHPDoc for every column, `program()` belongsTo, `newFactory()`.
9. **Anemic `Graduation\CeremonyController`** (≤15 lines/method): `index` (admin
   queue of `JuryAssigned` students to schedule + a separate list / flag of
   `CeremonyScheduled` students ready to graduate once the date passes), `schedule`
   (DTO→`ScheduleCeremonyAction`→redirect-back+flash), `graduate`
   (Student→`MarkAsGraduatedAction`→redirect-back+flash). Props via `->only()`/
   `->through()`/`->map()`; child resources resolved through the parent.
10. **Routes** (`->name()`, no closures, role-gated `admin,super_admin,secretary`,
    appended to the existing admin group, mirroring 003):
    `GET /admin/graduation/ceremony` → `admin.graduation.ceremony.index`;
    `POST /admin/graduation/ceremony/{student}/schedule` →
    `admin.graduation.ceremony.schedule`;
    `POST /admin/graduation/ceremony/{student}/graduate` →
    `admin.graduation.ceremony.graduate`.
11. **Channel reuse** — `student.{studentId}` (slice 001) already authorises the
    student + staff; both new events broadcast there. **No new channel**; asserted.
12. **Inertia 2 + React 19 + TS page** `resources/js/Pages/Graduation/CeremonySchedule.tsx`
    (admin-focused): per `JuryAssigned` student, a date+time + location form (the
    schedule action, posting `ScheduleCeremonyData`); per `CeremonyScheduled`
    student whose `ceremony_date` has passed, a "Mark as graduated" button (the
    graduate action). Client-side mirror of the server rules (disable schedule
    submit unless the picked datetime is future + a weekday + within 08:00–17:59;
    disable graduate until `ceremony_date` is in the past) — the **server stays the
    authority**. Dark/light + ES/EN, WCAG 2.2 AA, reusing `Components/form/
    {TextField,SelectField,FormError}`, the dialog/echo patterns from
    `Graduation/JuryAssign` + `Student/Documents`.
13. **OPTIONAL student read-only signal** — extend the existing `Student/Payment`
    (or `Student/Status`) Echo lifecycle to also `.listen('.ceremony.scheduled')`
    and `.listen('.student.graduated')` so the student's live view reflects the
    final two advances. (No new student page; reuse the prior echo pattern. Open
    question B.)
14. **Tests (Pest, PostgreSQL 18 — never SQLite):**
    - **Unit:** the `CeremonyDate` rule (future/weekday/business-hours pass+fail
      cases — pure logic, `tests/Unit/`); the `DiplomaFolioGenerator` format
      (`{YEAR}-{CODE}-{SEQ}`) + sequential increment + **per-program-per-year reset**
      (needs DB → `tests/Feature/`, since it touches the sequence table); the
      state-machine edges 7→8 + 8→9 legal and representative illegal edges
      (`JuryAssigned`→`Graduated` skip; `Graduated`→anything) throw
      `InvalidStatusTransitionException` (extend the existing state-machine test).
    - **Feature:** schedule happy (7→8, both events, atomic); schedule rejects
      past/weekend/out-of-hours dates as 302 + session errors; schedule from a
      wrong source state guarded; graduate happy (8→9, folio + book + sheet +
      graduation_date set, both events, atomic, terminal); graduate blocked when
      `ceremony_date` has not passed (no mutation, no event); graduate from a wrong
      source state guarded; **folio uniqueness under concurrency** (two graduations
      same program+year get distinct sequential folios); non-staff → 403; web
      validation = 302.
    - **DTO validation:** `ScheduleCeremonyDataTest` in `tests/Feature/` (uses the
      container/DB) — valid weekday-future-business-hours passes; each of
      past/weekend/before-8/after-6/missing-location fails.
    - **Inertia contract:** `CeremonySchedulePagePropsTest` — exact snake_case shape.
    - **Broadcast contract:** `CeremonyScheduledBroadcastTest`,
      `StudentGraduatedBroadcastTest` (in-memory Student, forced ids; mirror
      `JuryAssignedBroadcastTest`).
    - **Arch:** extend `tests/Arch/ArchitectureTest.php` for `App\Domain\Ceremony`
      (final classes, Actions/Events final, Ceremony ↛ Identity; honour the existing
      SPEC §11.2 rule `Ceremony domain only uses Shared`? — see Open question C).
    - **Vitest:** `CeremonySchedule.test.tsx` — the client date guard (past/weekend/
      out-of-hours disables schedule submit; a valid weekday-future-business-hours
      datetime enables it) + the graduate-button-enabled-only-when-past guard.

### DEFERRED (later slices / phases) — explicitly OUT

- **Diploma document generation** — the diploma PDF/Word and any printable artifact.
  This slice persists the `diploma_folio` **string** + the state advance only.
  (`barryvdh/laravel-dompdf` / `phpoffice/phpword`; Open question A.)
- **Judge certificate generation** — the four `.docx` certificates (CERT-01).
- **Multi-assign ceremony** — SPEC §3.6 CERE-01's "same date to multiple students
  in one transaction" (and the React `Ceremony.tsx` multi-student picker, SPEC
  §1705/§1732). This slice ships **single-student** scheduling (one student per
  request) to keep the slice focused; the Action is written so a later multi-assign
  controller can loop it inside one transaction. (Open question D.)
- **Email notifications** — ceremony-notification + graduation-congratulations
  emails (SPEC §1837). The real-time broadcasts are the live signal; transactional
  email is a later notification slice.
- **Cancel / emergency transition** (Any→CANCELLED, SPEC §3.3 emergency table,
  `CancelProcessAction`) — separate concern, not part of the happy-path pipeline.
- **Reporting / analytics** (REPORT-*, ANALYTICS-*) — depend on graduated data this
  slice finally produces, but are their own chapter.
- **`record_book`/`record_sheet` as free-text staff input** — this slice derives
  them deterministically from the folio sequence (no input field). A future slice
  may make them editable. (Open question E.)

### Deviation / decisions flagged for the gate

- **ID strategy (D-ID).** `diploma_folio_sequences.id` stays bigint `$table->id()`
  per **ADR-001** (slices 001/002/003). No new ID scheme. The diploma *folio* itself
  is a human-readable `VARCHAR(50)` string (`{YEAR}-{CODE}-{SEQ}`), independent of
  the PK — unchanged from SPEC §6.3.11.
- **New sequence table (justified).** A naive `Student::where(program, year)->count()`
  is NOT race-safe (two concurrent requests read the same count and mint duplicate
  folios). A dedicated `diploma_folio_sequences` row locked via `lockForUpdate()`
  inside the graduation transaction is the minimal race-safe mechanism and keeps
  the folio counter independent of soft-deleted/cancelled students. This is the
  ONE migration this slice adds; SPEC §9 row 12 ("folio sequential generation",
  Unit, Ceremony) mandates testing exactly this.
- **`CeremonyDate` as a custom rule, not attribute soup.** SPEC §2538 names a
  single `CeremonyDate` rule encapsulating future+weekday+business-hours; modelling
  it as one `Illuminate\Contracts\Validation\ValidationRule` (referenced from the
  DTO via `#[Rule(new CeremonyDate)]`) keeps the invariant cohesive and testable in
  isolation (pure-logic Unit test). The DTO remains the validation SSOT.
- **Business-hours boundary.** "8AM-6PM" is read as `>= 08:00` and `< 18:00`
  (08:00:00 valid; 18:00:00 invalid; last valid minute 17:59). Flagged so the
  implementer and test author agree on the boundary.

## 3. Acceptance scenarios (When… Then)

1. **Schedule ceremony advances the stage (7→8).** *When* staff schedule a future
   weekday business-hours `ceremony_date` + `ceremony_location` for a student in
   `JuryAssigned`, *then* `ceremony_date`/`ceremony_location` are stored, the status
   advances to `CeremonyScheduled`, `StudentStatusChanged(7→8)` **and**
   `CeremonyScheduled` are dispatched on `student.{id}`, and it commits atomically.
2. **Schedule rejects a non-future date.** *When* the picked `ceremony_date` is in
   the past (or now), *then* the `ScheduleCeremonyData` DTO rejects it (302 +
   `assertSessionHasErrors('ceremony_date')`), no Action runs, status unchanged.
3. **Schedule rejects a weekend.** *When* `ceremony_date` falls on Sat/Sun, *then*
   302 + session error on `ceremony_date`; nothing mutates.
4. **Schedule rejects out-of-hours.** *When* `ceremony_date`'s time is `< 08:00`
   or `>= 18:00`, *then* 302 + session error on `ceremony_date`; nothing mutates.
5. **Schedule from the wrong state is impossible.** *When* scheduling is attempted
   for a student NOT in `JuryAssigned` (e.g. still `PaymentPending`, or already
   `CeremonyScheduled`/`Graduated`), *then* `GraduationStateMachine` throws
   `InvalidStatusTransitionException` (graceful 302 per `bootstrap/app.php`) and no
   DB write occurs.
6. **Graduate completes the pipeline (8→9, TERMINAL).** *When* staff mark a student
   in `CeremonyScheduled` graduated **after** the ceremony date has passed, *then*
   `status = Graduated`, `graduation_date = today`, a `diploma_folio`
   (`{YEAR}-{CODE}-{SEQ}`) + `record_book` + `record_sheet` are assigned,
   `StudentStatusChanged(8→9)` (with `next_step = null` because terminal) **and**
   `StudentGraduated` are dispatched, and it all commits atomically.
7. **Graduate blocked before the ceremony passes (CERE-02, CRITICAL).** *When*
   graduation is attempted while `ceremony_date` is still in the future (or null),
   *then* the Action throws / fails the precondition (302 + session error on
   `ceremony_date`), no folio is minted, status stays `CeremonyScheduled`, and the
   transaction rolls back.
8. **Graduate from the wrong state is impossible.** *When* graduation is attempted
   for a student NOT in `CeremonyScheduled`, *then* `GraduationStateMachine` throws
   (graceful 302) and nothing mutates.
9. **Folio is race-safe + sequential + per-program-per-year (GRAD-03, CRITICAL).**
   *When* two students of the **same** program graduate in the **same** year, *then*
   they receive **distinct** sequential folios (`…-001`, `…-002`) with NO collision,
   even under concurrent transactions (the `(program_id, year)` row is taken with
   `lockForUpdate()`); *and* a student of a **different** program (or the same
   program in a different year) restarts the sequence at `001`.
10. **Graduated is terminal.** *When* any transition out of `Graduated` is
    attempted, *then* `allowedTransitions()` is `[]`, `isTerminal()` is true, and
    the state machine refuses every edge. **This slice completes the 9-state pipeline.**
11. **Authorization.** *When* a non-staff user hits any ceremony route, *then*
    `EnsureRole` returns `403` and nothing mutates.
12. **Atomicity.** *When* `MarkAsGraduatedAction` mints the folio, advances the
    state and dispatches the events, *then* the sequence increment, the `students`
    write and the dispatch commit together or not at all (`DB::transaction` with the
    folio lock inside it).
13. **Real-time.** *When* `CeremonyScheduled`/`StudentGraduated` broadcast, *then*
    they are on names `ceremony.scheduled` / `student.graduated`, private
    `student.{id}`, with the payloads in §2.5/§2.6 — and the reused
    `StudentStatusChanged` fires alongside on `graduation.step.completed`.

## 4. Success criteria (definition of done for this slice)

- `composer format` (Pint) clean, `composer analyse` (Larastan **level 9**) green,
  `composer test` (Pest: arch + new unit/feature/contract/broadcast tests on
  PostgreSQL) green.
- `php artisan typescript:transform` emits `ScheduleCeremonyData` into
  `resources/js/types/generated.d.ts` (`MarkAsGraduatedAction` has no DTO).
- `php artisan migrate:fresh` applies cleanly on PostgreSQL 18: the
  `diploma_folio_sequences` table with the UNIQUE `(program_id, year)`, the
  `program_id` RESTRICT FK, reversible `down()`. **No** `students` migration added.
- The full `FormBPending → … → Graduated` road is walkable end-to-end across slices
  001→004; `Graduated` is terminal.
- No real PII in factories (fictional programs/students); `.gitignore` still blocks
  db/secrets.

## 5. Constitution (§No-negociables) this slice must satisfy

`declare(strict_types=1)` everywhere · explicit return types · `final` domain
classes · `readonly` + constructor promotion on DTO/event · backed enum (reuses
`GraduationStatus`) · Spatie Data DTOs only (`ScheduleCeremonyData`; no FormRequest,
no `$request->validate()`) · Actions one-operation + `DB::transaction` on multi-write
+ event · the folio lock lives INSIDE that transaction · anemic controllers (≤15
lines) · named routes / no closures · Domain ↛ `Illuminate\Http` and ↛
`App\Domain\Identity` (Ceremony MAY import Graduation + Academic) · Pest with arch
tests on PostgreSQL · bigint ids (ADR-001) · status mutations only through
`GraduationStateMachine` · authorization in HTTP/middleware, never the domain · no
float money (no money here at all) · dates stored as proper casts (`ceremony_date`
datetime, `graduation_date` date — already cast) · folio generation race-safe
(`lockForUpdate`, never naive `count()`) · no PII in factories · reversible migration.

## 6. Open questions for the gate

- **A. Document-generation deferral.** Confirm the diploma PDF/Word + the judge
  `.docx` certificates are OUT; this slice ships the `diploma_folio` string +
  book/sheet + the 7→8→9 advances only. **Recommend: defer** (matches the
  001/002/003 carve).
- **B. Ship the student-side live listeners now?** Recommend: **yes** — extend the
  existing `Student/Payment`/`Student/Status` Echo lifecycle to listen for
  `.ceremony.scheduled` + `.student.graduated` so the student's view reflects the
  final advances. (If declined, `StudentStatusChanged` still drives the progress bar;
  drop the two extra `.listen()`s.)
- **C. Arch rule "Ceremony domain only uses Shared".** SPEC §11.2 (line ~1995)
  sketches `arch('Ceremony domain only uses Shared')->expect('App\Domain\Ceremony')
  ->toOnlyUse(...)`. But this slice's Ceremony Actions legitimately import
  `Graduation` (Student, GraduationStatus, state machine, StudentStatusChanged) and
  `Academic` (Program). **Recommend:** DO NOT adopt the literal "only Shared" rule
  (it contradicts the cross-domain reality 003 already established for Jury); instead
  add the same shape 003 used — `Ceremony ↛ Identity`, Ceremony classes/Actions/
  Events final. Flag for the gate so the SPEC sketch isn't silently violated.
- **D. Single-student vs multi-assign scheduling.** Confirm single-student
  scheduling is sufficient for this slice (CERE-01's multi-assign deferred), with the
  Action shaped so a later controller can loop it in one transaction.
  **Recommend: single-student now.**
- **E. `record_book`/`record_sheet` derivation.** Confirm deriving book/sheet
  deterministically from the folio sequence (no staff input field) is acceptable for
  this slice, deferring editable book/sheet to a later slice. **Recommend: derive.**
- **F. Business-hours boundary.** Confirm `>= 08:00` and `< 18:00` (08:00 valid,
  18:00 invalid). **Recommend: yes.**
