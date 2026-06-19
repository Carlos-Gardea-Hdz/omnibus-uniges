# Spec 003 — Jury Domain (payment → jury assignment, the 6→7 transition)

> **Phase:** Specify (the WHAT and WHY, not the HOW).
> **SSOT:** `SPEC.md` (§3.5 Jury Domain, §6.3.15 `jury_assignments` + the
> all-different CHECK, §3.3 state machine transition 6→7, §6.1 FK matrix
> rows 1581-1585, JuryRole enum ~§Appendix, §3.4 payment columns on `students`,
> §8.4 broadcasting).
> **Builds on:** spec/plan 001 (Form B slice — canonical pattern) and 002
> (Documents slice — the second vertical slice; same conventions).
> **Status:** DRAFT — awaiting human review gate before `/sdd-plan`.

## 1. Problem & why

Approving the last required document (slice 002) lands a student in
`PaymentPending` (state 6). The next leg of the 9-state machine is the **jury
assignment** (`PaymentPending (6) → JuryAssigned (7)`), the only transition out
of state 6 (SPEC §3.3). Before it can fire, the student must have **paid** and an
admin must have **verified** that payment (SPEC §3.5 JURY-02 preconditions).

The foundation has the enum edge (`PaymentPending → JuryAssigned` is already
legal in `GraduationStatus`) **and** the payment columns on `students`
(`payment_reference`, `payment_verified`, `paid_at` — created in the slice-001
students migration). What it is missing:

- **Payment operations.** No way for a student to submit a payment reference, and
  no way for staff to mark a payment verified. Both mutate `students` payment
  columns **without** a state-machine transition (status stays 6).
- **Jury persistence.** No `jury_assignments` table, no `JuryAssignment` model, no
  `JuryRole` enum, no factory.
- **The 6→7 operation.** No `AssignJuryAction` that enforces the JURY-02
  preconditions, persists the four-professor jury, and advances the machine.

This slice builds the **third vertical slice** end-to-end: the payment + jury
lifecycle (submit payment → verify payment → assign jury), persisted,
transactional, guarded by the same single state-machine authority, and broadcast
in real time over the existing private `student.{id}` channel (reusing
`StudentStatusChanged` for the 6→7 advance, plus an **optional** `JuryAssigned`
event — see Open question C).

**Jury certificate / Annex generation (Word/PDF for the four professors and the
student) is explicitly DEFERRED to a later slice** (SPEC §3.7 CERT-01,
§3.5 JURY-03 emails), mirroring how slices 001/002 carved generation out.

## 2. Scope

### In scope

1. **`JuryRole` backed enum** (`app/Domain/Jury/Enums/JuryRole.php`) — cases
   `President='president'`, `Secretary='secretary'`, `Vocal='vocal'`,
   `Substitute='substitute'` (SPEC §JuryRole). `#[TypeScript]`, `labelKey()`
   (`jury_role.*`), `color()`, mirroring `GraduationStatus`/`DocumentStatus`
   conventions. Drives the frontend role labels and (optionally) badges.
2. **`jury_assignments` table** (SPEC §6.3.15) — `bigint id` (ADR-001, see D-ID);
   `student_id` FK→students **RESTRICT**, **UNIQUE** (1:1, historical);
   `president_professor_id`/`secretary_professor_id`/`vocal_professor_id` FK→
   professors **RESTRICT**; `substitute_professor_id` FK→professors **SET NULL**,
   nullable; timestamps; **softDeletes** (FK-restrict/historical rule); the
   **all-different CHECK** constraint via `DB::statement` (SPEC §6.3.15). One row
   per student.
3. **`JuryAssignment` model** (`app/Domain/Jury/Models/JuryAssignment.php`) —
   `final`, `HasFactory`, `SoftDeletes`, `$fillable`, `@property`/`@property-read`
   PHPDoc for every column + relation (including the NON-nullable belongsTo FKs,
   per the memory rule), `newFactory()`, relations `student()`, `president()`,
   `secretary()`, `vocal()`, `substitute()` (the last nullable). + `JuryAssignmentFactory`.
4. **Payment Actions** (each `final`, transactional, status stays `PaymentPending`):
   - `SubmitPaymentAction` — student records a `payment_reference`, sets
     `paid_at = now()`. **No** `payment_verified` change, **no** state transition.
   - `VerifyPaymentAction` — staff sets `payment_verified = true`. **No** state
     transition, **no** jury yet. Verification is a precondition gate, not the
     6→7 trigger.
5. **`AssignJuryAction`** — the 6→7 operation. Asserts the JURY-02 preconditions
   (`status === PaymentPending` AND `payment_verified === true`); creates **one**
   `jury_assignments` row (the unique `student_id` makes a second attempt fail
   loud); transitions `PaymentPending (6) → JuryAssigned (7)` via
   `GraduationStateMachine`; dispatches `StudentStatusChanged(6→7)` and (optional)
   `JuryAssigned`. All in one `DB::transaction`.
6. **Optional `JuryAssigned` event** (`app/Domain/Jury/Events/JuryAssigned.php`) —
   `ShouldBroadcast`, private `student.{id}`, event name **`jury.assigned`**,
   `readonly` promoted ctor, payload the four professor ids + role labels (see
   Open question C; recommend SHIP it for symmetry with slice 002).
7. **Spatie Data DTOs** (validation = SSOT, `#[TypeScript]`, no FormRequests):
   - `SubmitPaymentData` — `payment_reference` (`#[Required, StringType, Max(50)]`).
   - `AssignJuryData` — `president_professor_id`, `secretary_professor_id`,
     `vocal_professor_id` (`#[Required, IntegerType, Exists('professors','id')]`),
     `substitute_professor_id` (`#[Nullable, IntegerType, Exists('professors','id')]`).
     The **four-distinct** rule is expressed with pairwise `#[Different(...)]`
     attributes across all four ids (Laravel's `different` passes when the other
     value is null, so the optional substitute is handled correctly). This yields
     **field-keyed session errors** (302 + `assertSessionHasErrors`) and feeds the
     generated TS type for the client-side guard. The DB CHECK is defense in depth.
8. **Anemic controllers** (≤15 lines/method): `Student\PaymentController`
   (index/submit), `Graduation\JuryController` (index/verifyPayment/assign).
   Receive DTO → call Action → redirect/`Inertia::render`; props via
   `->only()`/`->map()`. Reviewer/student resolved through the request user;
   child resources resolved THROUGH the parent (the student), never raw client id.
9. **Routes** (`->name()`, no closures, role-gated, mirroring slice 002):
   student `role:student` payment index/submit; admin
   `role:admin,super_admin,secretary` jury queue index + verify-payment + assign.
10. **Channel reuse** — the existing `student.{studentId}` authorisation (slice
    001) already covers both `StudentStatusChanged` and the new `JuryAssigned`.
    **No new channel**; the spec asserts it.
11. **Inertia 2 + React 19 + TS pages**: `Student/Payment` (submit a payment
    reference + see verification status live), `Graduation/JuryAssign` (admin: for
    a `PaymentPending` + verified student, four professor `<select>`s — President,
    Secretary, Vocal required + Substitute optional — with a **client-side
    all-different guard** that disables submit until the four picks are distinct).
    Dark/light + ES/EN, WCAG 2.2 AA, reusing the slice-001/002 form primitives and
    the Echo lifecycle from `Student/Status`/`Student/Documents`.
12. **Tests (Pest, PostgreSQL 18 — never SQLite):** unit (`JuryRole` enum,
    state-machine edge 6→7 legal + a representative illegal jury edge), feature
    (submit/verify payment happy + guards; assign-jury happy 6→7 + every
    precondition guard + the four-distinct guard + the unique-student guard;
    non-staff/non-student 403; web-validation 302 + session errors), Inertia-
    contract tests for both pages, broadcast-contract test for `JuryAssigned`
    (if shipped), arch (extends the existing suite for the new `App\Domain\Jury`
    namespace), Vitest for the React all-different guard.

### DEFERRED (later slices / phases) — explicitly OUT

- **Jury certificate / Annex generation** — the four `.docx` judge certificates
  (CERT-01) and any jury Annex PDF/Word. This slice persists the assignment + the
  state advance only. (Open question A.)
- **Email notifications** — JURY-03's "email to 4 professors + student after
  assignment". The `JuryAssigned` broadcast is the real-time signal; transactional
  email is a later (notification) slice. (Open question B.)
- **Real payment gateway / money handling.** The student submits a free-text
  `payment_reference`; staff verify out-of-band. No `exam_cost_cents`, no gateway,
  no money value object in this slice (SPEC §6.3.16 `system_settings.exam_cost_cents`
  is untouched). Payment columns already exist; no payment migration is added.
- **`annex_iii_completed` / `form_b_approved` re-checks beyond status.** SPEC
  §3.5 lists four preconditions; states 4→5→6 already guarantee `form_b_approved`
  and `annex_iii_completed` were satisfied to reach state 6. This slice enforces
  the two that are NOT implied by the status alone — `status === PaymentPending`
  and `payment_verified === true` — and MAY additionally assert the two flags as
  belt-and-braces. (Open question D.)
- Ceremony (7→8), graduation (8→9), reports, analytics, demo seeding.

### Deviation / decisions flagged for the gate

- **ID strategy (D-ID).** `jury_assignments.id` stays bigint `$table->id()` per
  **ADR-001** (slices 001/002). SPEC §6.3.15 says CHAR(26) ULID — same documented
  deviation, not re-litigated. **Consequence:** the SPEC's CHECK uses
  `COALESCE(substitute_professor_id, '0000…0')` (a ULID sentinel) for the nullable
  substitute; with bigint FKs the substitute is simply `NULL`, and the CHECK uses
  `(substitute_professor_id IS NULL OR <id> != substitute_professor_id)` per pair.
  Functionally identical, idiomatic for the bigint scheme.
- **`payment_verified` semantics.** SPEC §3.5 makes verification a *precondition*
  of 6→7, not a transition. This slice models it exactly so: `VerifyPaymentAction`
  flips the boolean with **no** state change; the 6→7 advance happens only in
  `AssignJuryAction`. (Open question C confirms verify ≠ assign.)

## 3. Acceptance scenarios (When… Then)

1. **Submit payment (no transition).** *When* a student in `PaymentPending`
   submits a `payment_reference`, *then* `payment_reference` is stored, `paid_at`
   is set, `payment_verified` stays `false`, the status stays `PaymentPending`,
   and **no** `StudentStatusChanged` is dispatched.
2. **Verify payment (no transition).** *When* an admin/secretary verifies a
   student's payment, *then* `payment_verified` becomes `true`, the status stays
   `PaymentPending`, and **no** `StudentStatusChanged` is dispatched. No jury
   exists yet.
3. **Assign jury advances the stage (6→7).** *When* an admin assigns four DISTINCT
   professors (President, Secretary, Vocal + optional Substitute) to a student in
   `PaymentPending` whose `payment_verified === true`, *then* exactly one
   `jury_assignments` row is created, the status advances to `JuryAssigned`,
   `StudentStatusChanged(6→7)` (and, if shipped, `JuryAssigned`) is dispatched on
   `student.{id}`, and it all commits atomically.
4. **Assign without verified payment is blocked (JURY-02, CRITICAL).** *When* an
   admin attempts to assign a jury to a student whose `payment_verified === false`
   (even with `paid_at` set), *then* the Action throws / fails the precondition, no
   `jury_assignments` row is created, the status stays `PaymentPending`, and the
   transaction rolls back.
5. **Assign from the wrong state is impossible.** *When* assignment is attempted
   for a student NOT in `PaymentPending` (e.g. still `AnnexIiiPending`, or already
   `JuryAssigned`), *then* `GraduationStateMachine` throws
   `InvalidStatusTransitionException` (or the precondition guard rejects first) and
   no DB write occurs.
6. **Four-distinct invariant (JURY-01, CRITICAL).** *When* any two of the four
   professor ids are equal (e.g. President == Vocal, or Vocal == Substitute), *then*
   the `AssignJuryData` DTO rejects it (302 + `assertSessionHasErrors` on the
   offending field) before any Action runs; and the DB CHECK would reject it as a
   second line of defence. The optional Substitute being `null` never trips the rule.
7. **One jury per student.** *When* a jury is assigned to a student who already has
   a `jury_assignments` row, *then* the unique `student_id` constraint rejects it and
   the status is unchanged (a student is graded once).
8. **Validation is web-shaped.** *When* `payment_reference` is missing/too long, or
   a professor id is missing/not in `professors`, *then* the response is **302 +
   session errors** (never 422), and no Action runs.
9. **Authorization.** *When* a non-student hits the student payment route, or a
   non-staff user hits the admin jury route, *then* `EnsureRole` returns `403` and
   nothing mutates.
10. **Atomicity.** *When* `AssignJuryAction` both creates the jury and advances the
    stage, *then* the jury insert, the `status` write and the event dispatch commit
    together or not at all (`DB::transaction`).
11. **Real-time (if `JuryAssigned` shipped).** *When* `JuryAssigned` broadcasts,
    *then* it is on event name `jury.assigned`, private `student.{id}`, and its
    `broadcastWith()` returns the four professor ids (substitute nullable) and role
    labels — and the existing `StudentStatusChanged(6→7)` fires alongside on
    `graduation.step.completed`.

## 4. Success criteria (definition of done for this slice)

- `composer format` (Pint) clean, `composer analyse` (Larastan **level 9**) green,
  `composer test` (Pest: arch + new unit/feature/contract tests on PostgreSQL) green.
- `php artisan typescript:transform` emits types for `JuryRole`, `SubmitPaymentData`,
  `AssignJuryData` into `resources/js/types/generated.d.ts`.
- `php artisan migrate:fresh` applies cleanly on PostgreSQL 18; FK rules per
  §6.3.15 (`student_id` RESTRICT + UNIQUE; three professor FKs RESTRICT;
  `substitute_professor_id` SET NULL nullable); the all-different CHECK; reversible
  `down()`.
- **No** payment migration added (reuses the existing `students` payment columns).
- No real PII in factories (fictional professors/students); `.gitignore` still
  blocks db/secrets.

## 5. Constitution (§No-negociables) this slice must satisfy

`declare(strict_types=1)` everywhere · explicit return types · `final` domain
classes · `readonly` + constructor promotion on DTO/event · backed enum
(`JuryRole`) · Spatie Data DTOs only (no FormRequest, no `$request->validate()`) ·
Actions one-operation + `DB::transaction` on multi-write · anemic controllers
(≤15 lines) · named routes / no closures · Domain ↛ `Illuminate\Http` and ↛
`App\Domain\Identity` · Pest with arch tests on PostgreSQL · bigint ids (ADR-001) ·
status mutations only through `GraduationStateMachine` · authorization in
HTTP/middleware, never the domain · no PII in factories · reversible migrations.

## 6. Open questions for the gate

- **A. Certificate/Annex deferral.** Confirm the four judge `.docx` certificates
  (CERT-01) and any jury Annex generation are OUT; this slice ships
  payment + jury persistence + the 6→7 advance only. Recommend: defer (matches the
  001/002 carve).
- **B. Notification emails.** Confirm JURY-03's emails to the four professors + the
  student are OUT of this slice (covered by the real-time `JuryAssigned` broadcast;
  transactional email is a later notification slice). Recommend: defer.
- **C. Ship `JuryAssigned` broadcast now?** Recommend: **yes** — symmetry with slice
  002's `DocumentStatusChanged`, and the student's status page already listens on
  `student.{id}`. (If declined, `StudentStatusChanged(6→7)` alone still drives the
  progress bar; drop the event + its broadcast test.)
- **D. Precondition breadth.** SPEC §3.5 lists four preconditions; states 4→6
  already imply `form_b_approved` + `annex_iii_completed`. Confirm enforcing the two
  not implied by status (`status === PaymentPending`, `payment_verified === true`)
  is sufficient, with the other two as optional belt-and-braces asserts. Recommend:
  enforce the two; assert the others defensively.
