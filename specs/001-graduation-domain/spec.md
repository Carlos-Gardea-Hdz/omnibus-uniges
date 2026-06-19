# Spec 001 — Graduation Domain (Form B vertical slice + real-time status)

> **Phase:** Specify (the WHAT and WHY, not the HOW).
> **SSOT:** `SPEC.md` (§3.3 state machine, §5.7 state-machine architecture, §6.3.11
> students table, §7.2 student routes, §8.4/§9.1 broadcasting, §15.1 Phase 1).
> **Status:** DRAFT — awaiting human review gate before `/sdd-plan`.

## 1. Problem & why

UNIGES manages a student's path to graduation as a strict **9-state machine**
(`GraduationStatus`, already coded and verified). The foundation has the enum but
**no aggregate root, no persistence, no business operations, and no real-time
feedback**. Students cannot submit a Form B; admins cannot review it; nobody sees
status change live.

This slice builds the **first vertical slice** of the Graduation domain end-to-end:
the **Form B lifecycle** (states 1→2→3→2 and 2→4), persisted, transactional, guarded
by a single state-machine authority, and **broadcast in real time** over Reverb so a
student's status tracker updates without a refresh. It is the spine every later phase
(documents, jury, ceremony, graduation) hangs off.

## 2. Scope

### In scope
1. **`Student` aggregate root** (Eloquent model) + `students` migration with the
   fields, FK rules, CHECK constraints, ENUMs, SoftDeletes per SPEC §6.3.11.
2. **Prerequisite catalog migrations** that `students` FK-references, schema-first
   (minimal models, no business logic yet): `departments`, `programs`, `professors`,
   `graduation_types`, `study_plans`. (SPEC §15.1 Phase 1 + §12.3 migration order.)
3. **`GraduationStateMachine`** — the single authority for status transitions
   (`assertCanTransition`/`transitionTo`), throwing `InvalidStatusTransitionException`
   on illegal moves. No code outside it mutates `student.status` (SPEC §5.7).
4. **Form B Actions** (each `final`, transactional, dispatching `StudentStatusChanged`):
   - `SubmitFormBAction` — 1→2 (and resubmit 3→2; clears observations).
   - `ApproveFormBAction` — 2→4 (sets `form_b_approved`).
   - `RejectFormBAction` — 2→3 (requires observations).
5. **`StudentStatusChanged` event** — `ShouldBroadcast`, on **private channel
   `student.{id}`**, event name `graduation.step.completed`, payload
   `{completed_step, next_step, progress, message}` (SPEC §8.4/§9.1) + the
   `routes/channels.php` authorization for the channel.
6. **Spatie Data DTOs** (validation = SSOT, `#[TypeScript]`): `SubmitFormBData`,
   `ReviewFormBData`. **No FormRequests, no `$request->validate()`.**
7. **Value objects** applied on the model: `ControlNumber`, `GPA`, `Email`, `Address`
   (validate-in-constructor). Create any not already in `app/Domain/Shared`.
8. **Tests (Pest, PostgreSQL 18 — never SQLite for DB tests):**
   - Unit: state-machine legality (every legal edge passes, a representative illegal
     edge throws), enum already covered.
   - Feature: submit / approve / reject happy paths + guard rejections, asserting the
     event is dispatched and the DB transition persisted.
   - Arch: domain↛HTTP, `final` domain classes, `declare(strict_types=1)`, no
     cross-domain imports (extends the existing arch suite).

### HTTP layer — IN scope (gate decision: end-to-end slice)
9. **Anemic controllers** (≤15 lines): `Student\FormBController` (create/store/update),
   admin `Graduation\FormBReviewController` (approve/reject). Receive Spatie DTO → call
   Action → return `Inertia::render`/redirect. Props shaped with `->only()`.
10. **Routes** (`->name()`, no closures) under the role middleware (student / admin),
    per SPEC §7.2; `routes/channels.php` authorizes `student.{id}`.
11. **Inertia 2 + React 19 + TS pages**: `Student/FormB` (create/edit form bound to a
    Spatie-generated type via `useForm`), `Student/Status` (real-time tracker), an admin
    `Graduation/Review` list+action page. Dark/light + ES/EN, WCAG 2.2 AA.
12. **Frontend Echo consumption**: `echo.ts` wires Laravel Echo→Reverb (public key only);
    `GraduationProgress` updates the 9-step bar live from `graduation.step.completed`.
13. **Vitest** tests for the React form/logic; **Pest browser/feature** asserting the
    Inertia responses and that POST/PUT hit the Actions.

### Out of scope (later specs / phases)
- Document upload/approval (4→6), jury (6→7), ceremony (7→8), graduation (8→9),
  Annex/PDF generation, reports, demo-mode seeding.
- Full domains behind the 5 catalog tables (only schema + minimal models here).

### Deviation from SPEC §15.1, flagged for the gate
SPEC Phase 1 defers real-time broadcasting to Phase 2. This slice **pulls
`StudentStatusChanged` + Reverb broadcasting forward** because (a) you requested it
explicitly and (b) ARCHITECTURE.md §3 states every transition dispatches it. The event
is dispatched from the state machine regardless; only the *client* consumption is
deferred. → **Gate decision A.**

## 3. Acceptance scenarios (When… Then)

1. **Submit Form B (1→2).** *When* a student in `FormBPending` submits valid Form B
   data (GPA 70–100, unique 8–12-digit control number, required fields present),
   *then* status becomes `FormBReview`, `form_b_submitted_at` is set, and a
   `StudentStatusChanged(FormBPending→FormBReview)` event is dispatched on
   `student.{id}`.
2. **Reject Form B (2→3).** *When* an admin rejects a student in `FormBReview` with
   observations, *then* status becomes `FormBRejected`, `form_b_observations` stores
   the notes, and the event is dispatched.
3. **Resubmit after rejection (3→2).** *When* a `FormBRejected` student resubmits
   corrected data, *then* status returns to `FormBReview`, `form_b_observations` is
   cleared, and the event is dispatched.
4. **Approve Form B (2→4).** *When* an admin approves a student in `FormBReview`,
   *then* status becomes `AnnexesPending`, `form_b_approved` is `true`, and the event
   is dispatched.
5. **Illegal transition is impossible.** *When* any code attempts a non-allowed move
   (e.g. `FormBPending`→`Graduated`, or skipping a state), *then*
   `GraduationStateMachine` throws `InvalidStatusTransitionException` and **no DB write
   occurs** (transaction rolls back).
6. **GPA / control-number invariants.** *When* Form B data has GPA < 70 or > 100, or a
   non-unique / malformed control number, *then* the Spatie DTO / VO rejects it before
   any Action runs (built ⇒ valid).
7. **Atomicity.** *When* an Action performs the status mutation + side-effect writes,
   *then* they commit together or not at all (`DB::transaction`).
8. **Broadcast contract.** *When* `StudentStatusChanged` broadcasts, *then*
   `broadcastWith()` returns `completed_step`, `next_step` (nullable at terminal),
   `progress` (1–9 → 0–100), and `message` (the `labelKey`), on event name
   `graduation.step.completed`.

## 4. Success criteria (definition of done for this slice)
- `composer format` (Pint) clean, `composer analyse` (Larastan **level 9**) green,
  `composer test` (Pest, incl. arch + the new unit/feature tests on PostgreSQL) green.
- `php artisan typescript:transform` emits types for the new `#[TypeScript]` DTOs.
- `php artisan migrate:fresh` applies cleanly on PostgreSQL 18; every FK rule and CHECK
  constraint from §6.3.11 present; only `student_documents`/`team_members` cascade
  (neither created here — noted for the FK audit).
- No real PII anywhere; `.gitignore` still blocks db/secrets.

## 5. Constitution (§No-negociables) this slice must satisfy
`declare(strict_types=1)` everywhere · explicit return types · `final` domain classes ·
`readonly` + constructor promotion on VOs/DTOs/events · backed enum (done) · Spatie Data
DTOs only · Actions one-operation + `DB::transaction` · anemic controllers (N/A this
slice) · UUIDv7/ULID ids · money N/A · Pest with arch tests · PostgreSQL for DB tests.

## 6. Open questions for the gate
- **A. Reverb now vs Phase 2?** Recommend: dispatch + server broadcast now (above),
  defer client consumption. Confirm.
- **B. Catalog tables depth.** Recommend: schema-first migrations + minimal models for
  the 5 catalog tables (enough to satisfy `students` FKs + factories), full domains
  later. Confirm vs. stubbing FKs nullable.
- **C. `CancelProcess` / `MarkAsGraduated`** and the 4→9 actions are explicitly out of
  this slice. Confirm the slice ends at Form B approval (state 4).
