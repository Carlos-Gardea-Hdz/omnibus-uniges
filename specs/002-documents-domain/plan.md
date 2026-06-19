# Plan 002 — Documents Domain (upload → review → stage advance) — the HOW

> Companion to `spec.md`. Grounded in a read-only recon of the complete slice-001
> implementation (the canonical pattern) + SPEC.md §3.4/§6.3.9-13/§9.x/§10.4.
> Gated on `CLAUDE.md §No-negociables`. Mirrors plan 001's structure.

## 0. Decisions & flagged conflicts (resolve at implement)

| # | Topic | Decision | Why / flag |
|---|---|---|---|
| D1 | **ID strategy** | `required_documents.id`, `student_documents.id` = bigint `$table->id()`; all FKs `foreignId`. | Continues ADR-001 (slice 001). SPEC §6.3.x says CHAR(26) ULID — same documented deviation; do **not** introduce a 3rd scheme. |
| D2 | **`file_token`** | UUIDv7 string (`Str::uuid7()`), wrapped in a `FileToken` VO; unique column. | SPEC §6.3.13 mandates a UUID token independent of the PK; UUIDv7 = global law for new opaque ids. |
| D3 | **Disk** | `local` disk (= `storage/app/private`, already configured). Never `public`. Download via `URL::temporarySignedRoute` (24h, configurable `config('documents.url_ttl')`). | SPEC §10.4 signed-temporary-only; §9.3 path layout. Private disk is already the default in `config/filesystems.php`. |
| D4 | **4→5 ownership** | `BeginDocumentStageAction` triggered by **student** (route `student.documents.begin`), staff also allowed via the admin review screen. Generation deferred (spec §A/§B). | Avoids coupling this slice to PhpWord. The same edge later gains generation in its own Action/listener. |
| D5 | **5→6 auto-advance** | Inside `ApproveDocumentAction`, after flipping the doc, call `DocumentCompletionChecker`; if complete, transition 5→6 in the **same** transaction. No separate admin "complete" button. | SPEC §3.3 5→6 = "System: ALL approved" (DOC-03). Atomicity (spec scenario 10). |
| D6 | **Completion check placement** | `app/Domain/Graduation/Services/DocumentCompletionChecker.php` — pure, read-only, injected into `ApproveDocumentAction`. | SPEC §9.1/§7 ("Service: stateless, read-only") names `DocumentCompletionChecker`. Keeps the Action thin. |
| D7 | **Test DB / infra** | Reuse slice-001 Sail compose + `pgsql` test connection. Add **`Storage::fake('local')`** in upload/download feature tests — no real files. | Law: never SQLite for DB tests; never commit uploads. |
| D8 | **Required-docs source of truth** | The `graduation_type_required_document` pivot. `BeginDocumentStageAction` reads it; `DocumentCompletionChecker` compares approved `student_documents` against it. | SPEC §6.3.10; DOC-03 "per graduation type". |

## 1. Architecture (unchanged from slice-001 conventions)

Request lifecycle: **Controller (≤15 lines) → Spatie Data DTO (validation SSOT) → one
Action `handle(...)` (`DB::transaction`, dispatches event[s]) → redirect/`Inertia::render`
with `->only()`/`->map()` props.** Domain never touches HTTP or Identity. Status mutations
**only** through `GraduationStateMachine` (exists, edges 4→5 & 5→6 already legal). Document
status mutations only inside the document Actions. Two broadcast events on the existing
private `student.{id}` channel: `StudentStatusChanged` (reused) for stage advances,
`DocumentStatusChanged` (new) for per-document changes.

## 2. Data model

### 2.1 `required_documents` (catalog) — migration `..._create_required_documents_table`
`id` bigint; `name` string(150); `description` text null; `allowed_mimes` string(255)
(CSV, e.g. `application/pdf,image/jpeg,image/png`); `max_size_kb` int default 10240;
timestamps. No softDeletes (small catalog, matches §6.3.9 "no indexes"). Minimal `final`
model + factory.

### 2.2 `graduation_type_required_document` (pivot) — migration `..._create_..._table`
`graduation_type_id` foreignId → graduation_types **cascadeOnDelete**; `required_document_id`
foreignId → required_documents **cascadeOnDelete**; composite PK `[graduation_type_id,
required_document_id]` via `$table->primary([...])` (unique). **No** `id`, no timestamps
(pure pivot, matches §6.3.10). `GraduationType::requiredDocuments(): BelongsToMany` and the
inverse on `RequiredDocument`.

### 2.3 `student_documents` (aggregate child) — migration `..._create_student_documents_table`
`id` bigint; `student_id` foreignId → students **cascadeOnDelete** (true child, §6.3.13 +
§6.1 FK matrix); `required_document_id` foreignId → required_documents **restrictOnDelete**;
`reviewed_by` foreignId null → users **nullOnDelete**; `file_path` string(255);
`file_token` string(255) unique; `original_filename` string(255); `mime_type` string(100);
`file_size` `unsignedBigInteger`; `status` string default `'pending'`;
`rejection_reason` text null; `uploaded_at` timestamp null; `reviewed_at` timestamp null;
timestamps; softDeletes. Unique `[student_id, required_document_id]`; index `status`;
index `[student_id, status]`. **CHECK** (Postgres, via `DB::statement` after create,
reversible by table drop):
`student_documents_status_check CHECK (status IN ('pending','uploaded','approved','rejected'))`.

> Note: the row is created `pending` with **placeholder** file columns at begin-stage. Since
> §6.3.13 marks `file_path`/`file_token`/`original_filename`/`mime_type`/`file_size` NOT NULL,
> `BeginDocumentStageAction` seeds them with empty-string / `0` placeholders and a unique
> `file_token` per row; `UploadDocumentAction` overwrites them. `uploaded_at` stays null until
> the first upload. (Alternative — make file columns nullable until upload — is the cleaner
> model; **flag at implement**, recommend nullable file columns + `status` default `'pending'`
> and drop the placeholders. Either is acceptable; pick one and make the migration + model
> `@property` hints consistent.)

### 2.4 Migration order (extends slice-001 order, §12.3 subset)
`... existing 7 ...` → `required_documents` → `graduation_type_required_document`
(needs graduation_types + required_documents) → `student_documents` (needs students +
required_documents + users). Timestamps after `2026_06_18_000070_create_students_table`,
e.g. `2026_06_19_000010/000020/000030`.

## 3. Domain layer (`app/Domain/Graduation` + `app/Domain/Academic` + `app/Domain/Shared`)

**Exists (reuse):** `Enums/GraduationStatus` (edges 4→5, 5→6 legal),
`StateMachine/GraduationStateMachine`, `Exceptions/InvalidStatusTransitionException`,
`Models/Student`, `Events/StudentStatusChanged`.

**Create:**
- `app/Domain/Graduation/Enums/DocumentStatus.php` — `#[TypeScript] enum DocumentStatus:
  string` cases `Pending='pending'`, `Uploaded='uploaded'`, `Approved='approved'`,
  `Rejected='rejected'`; `labelKey(): 'document_status.'.$value`; `color()`
  (rejected→danger, approved→success, uploaded→primary, pending→warning).
- `app/Domain/Shared/ValueObjects/FileToken.php` — `final readonly`, `string $value`,
  validate UUID in ctor (`Str::isUuid`), `static generate(): self` = `Str::uuid7()`,
  `Stringable`.
- `app/Domain/Graduation/Models/StudentDocument.php` — `final`, `HasFactory`, `SoftDeletes`,
  `$fillable`, `casts()` (`status`→DocumentStatus, `uploaded_at`/`reviewed_at`→datetime,
  `file_size`→int). Relations: `student(): BelongsTo<Student>`, `requiredDocument():
  BelongsTo<RequiredDocument>`, `reviewer(): BelongsTo<User,'reviewed_by'>`. `@property`
  PHPDoc for every typed cast/relation (PHPStan L9, per the memory rule). `newFactory()`.
- `app/Domain/Academic/Models/RequiredDocument.php` — `final`, `$fillable`, `casts`
  (`max_size_kb`→int), `graduationTypes(): BelongsToMany`, `newFactory()`. `@property` hints.
- `app/Domain/Graduation/Models/Student.php` — **modify**: add `documents(): HasMany<
  StudentDocument>`. (No business logic.)
- `app/Domain/Academic/Models/GraduationType.php` — **modify**: add `requiredDocuments():
  BelongsToMany<RequiredDocument>`.
- `app/Domain/Graduation/Services/DocumentCompletionChecker.php` — `final readonly`;
  `allRequiredApproved(Student $student): bool` (compares approved doc ids against the
  student's graduation-type required-doc ids; true only if the required set is non-empty and
  fully covered). Read-only, no writes.
- `app/Domain/Graduation/Events/DocumentStatusChanged.php` — `final implements
  ShouldBroadcast`, `readonly` promoted ctor `(StudentDocument $document, bool $allApproved)`;
  `broadcastOn(): [PrivateChannel('student.'.$document->student_id)]`; `broadcastAs():
  'document.status.changed'`; `broadcastWith(): {document_id, required_document_id, status,
  rejection_reason, all_approved}`.
- `app/Domain/Graduation/Actions/BeginDocumentStageAction.php` — `handle(Student): Student`.
  tx: assert `AnnexesPending→AnnexIiiPending`; for each required doc of the student's
  graduation type, `firstOrCreate` a `pending` `StudentDocument` (idempotent); set status 5;
  save; `StudentStatusChanged::dispatch(4→5)`.
- `app/Domain/Graduation/Actions/UploadDocumentAction.php` — `handle(UploadDocumentData,
  Student): StudentDocument`. tx: store file on `local` disk at the per-student path with a
  new `FileToken`; upsert the `StudentDocument` for `(student, required_document_id)` →
  `uploaded`, set file_*, `uploaded_at=now()`, clear `rejection_reason`/`reviewed_by`/
  `reviewed_at`; save; `DocumentStatusChanged::dispatch(doc, allApproved:false)`. No state
  transition. (Delete the prior stored file if replacing — best-effort.)
- `app/Domain/Graduation/Actions/ApproveDocumentAction.php` — `handle(StudentDocument, User
  $reviewer): StudentDocument`. tx: set `approved` + `reviewed_by`/`reviewed_at`; save;
  `$complete = DocumentCompletionChecker->allRequiredApproved(student)`;
  `DocumentStatusChanged::dispatch(doc, $complete)`; **if** `$complete`: assert
  `AnnexIiiPending→PaymentPending`, set `documents_completed_at=now()` + status 6, save,
  `StudentStatusChanged::dispatch(5→6)`.
- `app/Domain/Graduation/Actions/RejectDocumentAction.php` — `handle(StudentDocument,
  ReviewDocumentData, User $reviewer): StudentDocument`. tx: set `rejected` +
  `rejection_reason` + `reviewed_by`/`reviewed_at`; save; `DocumentStatusChanged::dispatch(
  doc, allApproved:false)`. No state change.
- `app/Domain/Graduation/Data/UploadDocumentData.php` — `#[TypeScript] final extends Data`.
  `required_document_id` `#[Required, IntegerType, Exists('required_documents','id')]`;
  `file` `Illuminate\Http\UploadedFile` with `#[Required, File, Max(10240)]` (KB) — and a
  per-row MIME/size guard applied in the Action (or a custom rule) against the chosen
  `required_documents.allowed_mimes`/`max_size_kb`, since attributes are static.
- `app/Domain/Graduation/Data/ReviewDocumentData.php` — `rejection_reason` `#[Required,
  StringType, Min(5)]`.

## 4. HTTP layer

- `app/Http/Controllers/Student/DocumentController.php` — `final`. `index()` →
  `Inertia::render('Student/Documents', [...])` (the per-document checklist with statuses,
  `->map()`); `begin()` → `BeginDocumentStageAction` → redirect; `upload(UploadDocumentData)`
  → `UploadDocumentAction` → redirect back with flash.
- `app/Http/Controllers/Student/DocumentDownloadController.php` — `show(StudentDocument)`:
  authorize owner-or-staff (policy/inline `abort_unless`), `return Storage::disk('local')
  ->download($document->file_path, $document->original_filename)` — reached only via a fresh
  `URL::temporarySignedRoute('student.documents.download', now()->addDay(), [...])` the
  index page renders. Route declares `->middleware('signed')`.
- `app/Http/Controllers/Graduation/DocumentReviewController.php` — `index()` →
  `Inertia::render('Graduation/DocumentReview', [...])` (students in `AnnexIiiPending` with
  their docs, paginated, `->through()`); `approve(StudentDocument)` → `ApproveDocumentAction`
  → redirect back; `reject(StudentDocument, ReviewDocumentData)` → `RejectDocumentAction`
  → redirect back. Reviewer = `$request->user()`.
- **Routes** (`routes/web.php`, named, no closures):
  - `role:student`: `GET /student/documents` `student.documents.index`; `POST
    /student/documents/begin` `student.documents.begin`; `POST /student/documents/upload`
    `student.documents.upload`; `GET /student/documents/{studentDocument}/download`
    `student.documents.download` **(+ `signed` middleware)**.
  - `role:admin,super_admin,secretary`: `GET /admin/graduation/documents`
    `admin.graduation.documents.index`; `POST /admin/graduation/documents/{studentDocument}/
    approve` `admin.graduation.documents.approve`; `POST /admin/graduation/documents/
    {studentDocument}/reject` `admin.graduation.documents.reject`.
- **Channel auth:** unchanged — `student.{studentId}` from slice 001 already authorises both
  the student and staff; `DocumentStatusChanged` broadcasts there. Asserted, not modified.
- `config/documents.php` (new): `url_ttl` (hours, default 24). `.env.example` documents it.

## 5. Frontend (Inertia 2 + React 19 + TS)

- `resources/js/Pages/Student/Documents.tsx` — props snake_case (see §contract). Renders the
  checklist; per row: status badge (`DocumentStatus` color via the generated **type-only**
  import + raw-string compare, e.g. `status === ('rejected' as DocumentStatus)`), a file
  input + upload button (`useForm` posting `multipart` to `student.documents.upload`), a
  download link (the server-provided signed URL), the `rejection_reason` when rejected.
  Subscribes via `createEcho()` to `student.{student_id}` `.document.status.changed`, updates
  the affected row live; cleanup on unmount (mirror `Student/Status`). Empty-state with a
  "begin document stage" button posting to `student.documents.begin` when status is
  `annexes_pending`.
- `resources/js/Pages/Graduation/DocumentReview.tsx` — admin table grouped by student;
  approve posts directly, reject opens the accessible modal (reuse the `Graduation/Review`
  dialog pattern) bound to `ReviewDocumentData` (`rejection_reason`, min 5).
- Reuse `Components/form/{TextField,SelectField,FormError}` + the Echo lifecycle from
  `Status.tsx`. WCAG 2.2 AA, dark/light, ES/EN.
- i18n: add to `resources/locales/{es,en}.json` → `documents.*` (page title, upload, begin,
  download, statuses `document_status.*`, admin.review labels, validation messages).

## 6. Tests

- `tests/Unit/Graduation/DocumentStatusTest.php` — every case `labelKey()`/`color()`; backed.
- `tests/Unit/Shared/FileTokenTest.php` — valid UUID accepted, malformed rejected, `generate`
  is a UUIDv7.
- `tests/Unit/Graduation/DocumentCompletionCheckerTest.php` — false when any required doc not
  approved; true only when the full non-empty required set is approved; false when the
  graduation type requires no docs (flag the empty-set policy at implement).
- Extend `tests/Unit/Graduation/GraduationStateMachineTest.php` (or a doc-focused file) — 4→5
  and 5→6 legal; an illegal doc-stage edge throws.
- `tests/Feature/Graduation/BeginDocumentStageTest.php` — 4→5 materialises one pending row per
  required doc, idempotent, event dispatched; begin from non-`AnnexesPending` → no change.
- `tests/Feature/Graduation/UploadDocumentTest.php` — `Storage::fake('local')`; valid upload →
  `uploaded` + file stored + event; bad MIME → 302 + `assertSessionHasErrors('file')`;
  oversize → session error; non-student → 403; replacing a rejected doc clears
  `rejection_reason`.
- `tests/Feature/Graduation/ReviewDocumentTest.php` — approve non-final (stays 5, no
  StudentStatusChanged); approve final → 5→6 + `documents_completed_at` + both events; "one
  unapproved blocks 5→6"; reject stores reason + event; empty reason → 302 +
  `assertSessionHasErrors('rejection_reason')`; non-staff → 403.
- `tests/Feature/Graduation/DocumentDownloadTest.php` — `Storage::fake('local')`; owner via
  signed URL → 200; unsigned → 403; expired → 403; other student → 403; staff → 200.
- `tests/Feature/Graduation/DocumentsPagePropsTest.php` — Inertia contract for
  `Student/Documents` (`->component()->has()/->where()` on the exact snake_case shape incl.
  `student_id`, `documents[]`).
- `tests/Feature/Graduation/DocumentReviewPagePropsTest.php` — Inertia contract for
  `Graduation/DocumentReview`.
- `tests/Feature/Graduation/DocumentStatusBroadcastTest.php` — `covers(DocumentStatusChanged
  ::class)`: channel `private-student.{id}`, name `document.status.changed`, `broadcastWith`
  keys/values, `all_approved` true/false.
- Extend `tests/Arch/ArchitectureTest.php` — `App\Domain\Graduation\Services` final;
  `DocumentStatusChanged` final; new enum string-backed (the existing `Enums` rule covers it);
  Domain ↛ Http/Identity holds.
- `resources/js/Pages/Student/__tests__/Documents.test.tsx` (Vitest) — renders the checklist,
  shows a rejection reason, disables upload while processing.
- All DB tests on **pgsql** (`RefreshDatabase`); factories: `RequiredDocumentFactory`,
  `StudentDocumentFactory` (+ states `pending()/uploaded()/approved()/rejected()`), reuse the
  graduation-type/student factories. `StudentFactory` gains a `documentsStage()` state
  (status `AnnexIiiPending`) and a helper to attach required docs to the graduation type.

## 7. Infra
- Reuse slice-001 `docker-compose.yml` + `phpunit.xml` (`pgsql`, `BROADCAST_CONNECTION=null`,
  `FILESYSTEM_DISK=local`). Tests use `Storage::fake('local')` — no real files, nothing to
  commit. `config/documents.php` added; `.gitignore` already blocks `storage/app/*`.

## 8. File manifest

**Create (~26):** 3 migrations · 2 models (RequiredDocument, StudentDocument) · 2 factories ·
DocumentStatus enum · FileToken VO · DocumentCompletionChecker service · DocumentStatusChanged
event · 4 Actions · 2 DTOs · 3 controllers · 2 Inertia pages · `config/documents.php` ·
~9 test files.
**Modify (~7):** `Student.php` (documents relation) · `GraduationType.php` (requiredDocuments)
· `StudentFactory.php` (states) · `routes/web.php` · `resources/locales/{es,en}.json` ·
`.env.example` · `tests/Arch/ArchitectureTest.php`.
**Generated:** `resources/js/types/generated.d.ts` via `typescript:transform`.
**No new ADR** (continues ADR-001).

## 9. Verify order (phase 5 — orchestrator runs the full suite; agents only self-check
PHPStan + tsc read-only per the build rules)
`composer format` → `composer analyse` (L9) → `typescript:transform` → `sail up -d` →
`sail artisan migrate:fresh` → `sail pest` (Unit+Arch+Feature on pgsql) → `pnpm tsc --noEmit`
→ `pnpm lint` → `pnpm vitest run` → `pnpm build`. Evidence captured for the gate.

## 10. Constitution check
strict_types ✓ · explicit returns ✓ · final domain ✓ · readonly VO/DTO/event ✓ · backed enum
`DocumentStatus` ✓ · Spatie Data only (no FormRequest) ✓ · Action + `DB::transaction` ✓ ·
anemic controllers ✓ · named routes / no closures ✓ · Domain ↛ Http/Identity ✓ · Pest + arch
on Postgres ✓ · signed-temporary URLs only / private disk ✓ · no PII in factories ✓ ·
reversible migrations ✓ · bigint ids per ADR-001 (flagged, not silently broken) ✓.
