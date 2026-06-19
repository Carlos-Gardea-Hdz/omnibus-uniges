# Spec 002 — Documents Domain (upload → review → stage advance, real-time)

> **Phase:** Specify (the WHAT and WHY, not the HOW).
> **SSOT:** `SPEC.md` (§3.4 Documents Domain, §3.3 state machine 4→5→6, §6.3.9
> `required_documents`, §6.3.10 `graduation_type_required_document`, §6.3.13
> `student_documents`, §9.2/§9.3 storage, §10.4 document access security, §8.4
> broadcasting, §15 Phase 2).
> **Builds on:** spec/plan 001 (Graduation Form B slice — the canonical pattern).
> **Status:** DRAFT — awaiting human review gate before `/sdd-plan`.

## 1. Problem & why

Form B approval (slice 001) lands a student in `AnnexesPending` (state 4). The next
two legs of the 9-state machine are **document-driven**:

- `AnnexesPending (4) → AnnexIiiPending (5)` — annexes are dispatched; the student is
  invited to start uploading.
- `AnnexIiiPending (5) → PaymentPending (6)` — the student uploads every document their
  graduation type requires; staff approve each one; **when all required documents reach
  `approved`, the student auto-advances to state 6** (SPEC §3.3 transition 5→6, DOC-03:
  "one unapproved document blocks the entire transition").

The foundation has the enum edges (4→5, 5→6 are already legal in `GraduationStatus`) but
**no document persistence, no required-document catalog, no upload/review operations, and
no per-document real-time feedback**. Students cannot upload; staff cannot approve/reject;
nobody sees a document's status change live.

This slice builds the **second vertical slice** end-to-end: the document lifecycle
(upload → individual approve/reject → automatic stage advance), persisted, transactional,
guarded by the same single state-machine authority, and broadcast in real time over Reverb
(both the existing `graduation.step.completed` for stage advances and a **new
`document.status.changed`** event for per-document updates, both on the private
`student.{id}` channel per SPEC §8.4).

## 2. Scope

### In scope

1. **Catalog: `required_documents`** (SPEC §6.3.9) — schema + minimal `final` model +
   factory. Columns: `name`, `description`, `allowed_mimes` (CSV), `max_size_kb`
   (default 10240 = 10MB).
2. **Pivot: `graduation_type_required_document`** (SPEC §6.3.10) — which documents each
   graduation type requires. `belongsToMany` wired on both sides. This is the authority
   for "what does this student still owe?".
3. **`StudentDocument` aggregate child** (SPEC §6.3.13) — `students`-CASCADE Eloquent
   model + `student_documents` migration with FK rules, the `document_status` ENUM via
   string + CHECK, unique `[student_id, required_document_id]`, unique `file_token`,
   SoftDeletes. One row per (student, required document).
4. **`DocumentStatus` backed enum** (`pending`/`uploaded`/`approved`/`rejected`) with
   `labelKey()` and `color()`, `#[TypeScript]`, mirroring `GraduationStatus` conventions.
5. **File storage** on the **`local` (private) disk** under
   `documents/{student_id}/uploads/{required_document_id}_{file_token}.{ext}` (SPEC §9.3),
   `file_token` = UUIDv7 string. MIME + size validated by the DTO against the
   `required_documents` row. Download only via **Laravel signed temporary URLs**, default
   24h, regenerated per request (SPEC §10.4) — never a permanent/public URL.
6. **Document Actions** (each `final`, transactional, dispatching the right event):
   - `BeginDocumentStageAction` — `AnnexesPending (4) → AnnexIiiPending (5)`. Materialises
     one `pending` `StudentDocument` row per required document for the student's graduation
     type (idempotent), sets status 5, dispatches `StudentStatusChanged(4→5)`.
   - `UploadDocumentAction` — stores the file, upserts the `StudentDocument` to `uploaded`
     (clears any prior `rejection_reason`/`reviewed_*`), dispatches
     `DocumentStatusChanged`. **No state-machine transition** (status stays 5).
   - `ApproveDocumentAction` — sets one document `approved` + `reviewed_by`/`reviewed_at`,
     dispatches `DocumentStatusChanged`; **then** runs the completion check and, if every
     required document is `approved`, advances `AnnexIiiPending (5) → PaymentPending (6)`,
     sets `documents_completed_at`, dispatches `StudentStatusChanged(5→6)`.
   - `RejectDocumentAction` — sets one document `rejected` + `rejection_reason` +
     `reviewed_by`/`reviewed_at`, dispatches `DocumentStatusChanged`. No stage change.
   - **Completion is a domain service** `DocumentCompletionChecker` (SPEC §9.1 cites it):
     pure, read-only, "are all required docs for this student approved?".
7. **`DocumentStatusChanged` event** — `ShouldBroadcast`, private `student.{id}`, event
   name **`document.status.changed`** (SPEC §8.4), `readonly` promoted ctor, payload
   `{document_id, required_document_id, status, rejection_reason, all_approved}`.
8. **Spatie Data DTOs** (validation = SSOT, `#[TypeScript]`, no FormRequests):
   `UploadDocumentData` (`required_document_id`, `file` with `File`/`Mimetypes`/`Max`
   rules), `ReviewDocumentData` (`rejection_reason`, required only on reject).
9. **Value object** `FileToken` in `app/Domain/Shared/ValueObjects` — `final readonly`,
   wraps a UUIDv7 string, validate-in-constructor.
10. **Anemic controllers** (≤15 lines/method): `Student\DocumentController`
    (index/upload), `Student\DocumentDownloadController` (signed-URL show), admin
    `Graduation\DocumentReviewController` (index/approve/reject). Receive DTO → call Action
    → redirect/`Inertia::render`; props via `->only()`/`->map()`.
11. **Routes** (`->name()`, no closures, role-gated): student document index/upload/begin/
    download; admin document review list + per-document approve/reject. The existing
    `student.{id}` channel authorisation (slice 001) already covers both events — **no new
    channel**, but the spec asserts it.
12. **Inertia 2 + React 19 + TS pages**: `Student/Documents` (upload + live per-document
    status list, subscribing to `document.status.changed`), `Graduation/DocumentReview`
    (admin per-student document queue with approve/reject). Dark/light + ES/EN, WCAG 2.2 AA,
    reusing slice-001 form primitives and the Echo pattern from `Student/Status`.
13. **Tests (Pest, PostgreSQL 18 — never SQLite):** unit (enum, `FileToken` VO,
    `DocumentCompletionChecker`, state-machine edges 4→5/5→6), feature (begin/upload/
    approve/reject happy + guard paths, the "one unapproved blocks 5→6" invariant, signed
    URL access control, non-staff 403, validation → 302 + session errors), Inertia-contract
    tests for both pages, broadcast-contract tests for `DocumentStatusChanged`, arch
    (extends the existing suite), Vitest for the React upload/list logic.

### DEFERRED (later slices / phases) — explicitly OUT

- **Heavy generation** — Annex I/II `.docx` (PhpWord), constancias/diplomas PDF
  (DomPDF/Browsershot), judge certificates, Excel reports. `BeginDocumentStageAction`
  represents "annexes dispatched" as a **state transition + flag only**; the actual
  PhpWord generation + email to School Services (ANNEX-01/02) is a later slice. Noted at
  the gate (Open question A).
- Email notifications (Form B rejection email, annex delivery email) — slice 003+.
- Jury (6→7), ceremony (7→8), graduation (8→9), reports, analytics, demo seeding.
- Team-project document sharing / `team_members` (table not created here).
- Annex documents table (`annex_documents`, §6.3.14) — belongs to the generation slice.

### Deviation / decisions flagged for the gate

- **SPEC §15 Phase 2** bundles documents + jury + ceremony + annex generation + email. This
  slice **carves out only the document upload/review + the two state advances** it gates,
  consistent with how slice 001 carved Form B out of Phase 1. → **Gate decision A.**
- **`BeginDocumentStageAction` ownership of 4→5.** SPEC labels 4→5 "Annexes Sent (System)".
  Since generation is deferred, this slice models 4→5 as a **staff-or-student-triggered**
  "begin document stage" that materialises the document checklist. Confirm the trigger
  actor and that representing annexes-sent as a flag (no file yet) is acceptable for now. →
  **Gate decision B.**
- **ID strategy** stays bigint `$table->id()` per **ADR-001** (slice 001); SPEC §6.3.x says
  CHAR(26) ULID — same documented deviation, not re-litigated here. `file_token` remains a
  UUIDv7 string (independent of the PK strategy; SPEC §6.3.13 specifies a UUID token).

## 3. Acceptance scenarios (When… Then)

1. **Begin document stage (4→5).** *When* a student in `AnnexesPending` begins the document
   stage, *then* status becomes `AnnexIiiPending`, exactly one `pending` `StudentDocument`
   row exists per document their graduation type requires, and `StudentStatusChanged(4→5)`
   is dispatched on `student.{id}`. Re-running it is idempotent (no duplicate rows, no
   re-transition).
2. **Upload a document.** *When* a student uploads a file for a required document whose MIME
   and size satisfy that `required_documents` row, *then* the file is stored on the private
   disk under the per-student path, the `StudentDocument` becomes `uploaded` with a fresh
   `file_token`, any prior `rejection_reason` is cleared, and `DocumentStatusChanged` is
   dispatched. Student status stays `AnnexIiiPending`.
3. **Reject MIME/size.** *When* the uploaded file's MIME type is not in `allowed_mimes`, or
   its size exceeds `max_size_kb`, *then* the Spatie DTO rejects it (302 + session error on
   `file`) before any Action runs and no file is stored.
4. **Approve a non-final document.** *When* an admin approves one document while others are
   still pending/uploaded, *then* that document is `approved` (with `reviewed_by`/
   `reviewed_at`), `DocumentStatusChanged{all_approved:false}` is dispatched, and the
   student stays `AnnexIiiPending` (no `StudentStatusChanged`).
5. **Approve the last document advances the stage (5→6).** *When* an admin approves the
   final outstanding document so **all** required documents are `approved`, *then* the
   student advances to `PaymentPending`, `documents_completed_at` is set, both
   `DocumentStatusChanged{all_approved:true}` **and** `StudentStatusChanged(5→6)` are
   dispatched, and it all commits atomically.
6. **One unapproved blocks the transition (DOC-03, CRITICAL).** *When* every required
   document except one is `approved` and that one is `uploaded`/`rejected`/`pending`, *then*
   no `5→6` transition occurs and the student remains `AnnexIiiPending`.
7. **Reject a document.** *When* an admin rejects a document with a reason, *then* it becomes
   `rejected`, `rejection_reason` is stored, `DocumentStatusChanged` is dispatched, and the
   student stays `AnnexIiiPending`. Empty reason → 302 + session error on `rejection_reason`.
8. **Signed-URL access only (§10.4).** *When* a download is requested, *then* the controller
   issues a fresh **temporary signed URL** (default 24h); an unsigned/expired request is
   `403`; a student may only download **their own** documents and **staff** may download any;
   anyone else is `403`.
9. **Illegal transition is impossible.** *When* any code attempts a non-allowed move (e.g.
   approving a document for a student already in `PaymentPending`, or begin-stage from a
   non-`AnnexesPending` state), *then* `GraduationStateMachine` throws
   `InvalidStatusTransitionException` and no DB write occurs (transaction rolls back).
10. **Atomicity.** *When* `ApproveDocumentAction` both flips the document and advances the
    stage, *then* the document write, the `documents_completed_at`/`status` write and the
    event dispatch commit together or not at all (`DB::transaction`).
11. **Real-time list.** *When* `DocumentStatusChanged` broadcasts, *then* `broadcastWith()`
    returns `document_id`, `required_document_id`, `status` (string value), `rejection_reason`
    (nullable) and `all_approved` (bool), on event name `document.status.changed`, private
    `student.{id}`.
12. **Authorization.** *When* a non-student hits the student upload route, or a non-staff
    user hits the admin review route, *then* `EnsureRole` returns `403` and nothing mutates.

## 4. Success criteria (definition of done for this slice)

- `composer format` (Pint) clean, `composer analyse` (Larastan **level 9**) green,
  `composer test` (Pest: arch + the new unit/feature/contract tests on PostgreSQL) green.
- `php artisan typescript:transform` emits types for `DocumentStatus`, `UploadDocumentData`,
  `ReviewDocumentData` into `resources/js/types/generated.d.ts`.
- `php artisan migrate:fresh` applies cleanly on PostgreSQL 18; FK rules per §6.3.13
  (`student_id` CASCADE, `required_document_id` RESTRICT, `reviewed_by` SET NULL); the
  `document_status` CHECK; the two unique constraints; pivot composite PK; reversible `down()`.
- Files stored on the **private** disk; **no** permanent or public URL exists; download path
  is signed-temporary only; access is owner-or-staff.
- No real PII anywhere; factories use fictional data and `Storage::fake()` in tests (no real
  uploads committed); `.gitignore` still blocks db/secrets/`storage/app`.

## 5. Constitution (§No-negociables) this slice must satisfy

`declare(strict_types=1)` everywhere · explicit return types · `final` domain classes ·
`readonly` + constructor promotion on VO/DTO/event · backed enum (`DocumentStatus`) · Spatie
Data DTOs only (no FormRequest, no `$request->validate()`) · Actions one-operation +
`DB::transaction` on multi-write · anemic controllers (≤15 lines) · named routes / no closures
· Domain ↛ `Illuminate\Http` and ↛ `App\Domain\Identity` · Pest with arch tests on PostgreSQL
· bigint ids (ADR-001) · no PII in factories.

## 6. Open questions for the gate

- **A. Generation deferral.** Confirm Annex/PDF/Word generation + emails are OUT; this slice
  ships upload/review + the two state advances only. Recommend: defer (matches the 001 carve).
- **B. 4→5 trigger & "annexes sent" semantics.** SPEC says 4→5 is System/"Annexes Sent".
  Recommend: model it now as a `BeginDocumentStageAction` (staff- or student-triggered) that
  only materialises the checklist + transitions; wire the real PhpWord generation into this
  same edge in the generation slice. Confirm the trigger actor (recommend: **student**, "I'm
  ready to upload", with staff also allowed).
- **C. Auto-advance vs. explicit "mark complete".** SPEC §3.3 5→6 is "System: ALL approved".
  Recommend: **auto-advance inside `ApproveDocumentAction`** (the approval that completes the
  set triggers it) rather than a separate admin button. Confirm.
