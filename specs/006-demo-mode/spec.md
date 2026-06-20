# Spec 006 — Demo mode (friction-free, recruiter-facing exploration sandbox)

> **Phase:** Specify (the WHAT and WHY, not the HOW).
> **SSOT:** `SPEC.md` §13 (Demo Mode Specification) — §13.1 six presets, §13.2
> session flags (`is_demo`, `demo_expires_at`), §13.3 `DemoSessionMiddleware`
> (TTL + destructive-op block + email suppression + `demo_session_id` tag),
> §13.4 cleanup (`php artisan demo:cleanup`, every 15 min, purges only tagged
> rows, never the baseline), §13.5 rate limit (10 demo logins / IP / hour, Laravel
> rate limiter); plus §3.1 AUTH-05 (demo login w/ role selection) + AUTH-06
> (logout clears demo sessions), §6 routes row 1603 (`POST /demo-login`),
> §15 Phase 4 ("Demo mode with 6 presets + cleanup"), Appendix F (the 4 demo
> students at pipeline stages, fictional control numbers 20180001-04).
> **Also governed by** `CLAUDE.md` "Demo Mode Philosophy" / OMNIBUS data-privacy
> law (fictional PII only — no real CURP/RFC/names) and the "available: true only
> when URL 200" portfolio rule (this slice is the recruiter on-ramp).
> **Builds on:** the **auth slice (005)** — `AuthenticatedSessionController`,
> `AuthenticateUserAction`, `LoginThrottle`, the `guest`/`auth` route groups, the
> role→landing redirect `redirectRouteFor()`; the `UserRole` enum + `UserFactory`
> role states; the `StudentFactory` pipeline-stage states; `GraduationStatus`
> (9 states); `EnsureRole`; `bootstrap/app.php` (alias map + the
> `InvalidStatusTransitionException` 302 render); `HandleInertiaRequests`
> (shared `auth`/`flash`/`locale` props); `Welcome.tsx` (the `/demo` CTA
> placeholder, key `landing.cta.demo`). **This slice mirrors slice 004/005
> conventions VERBATIM** (controller ≤15 lines → Spatie Data DTO → Action with
> `DB::transaction` → redirect/`Inertia::render` w/ snake_case props; named
> routes/no closures; arch tests; Pest on PostgreSQL 18).
> **Status:** DRAFT — awaiting human review gate before `/sdd-plan`.

## 1. Problem & why

The portfolio narrative (CLAUDE.md) makes UNIGES a recruiter-facing showcase. A
recruiter must be able to **explore the full 9-state pipeline and every role's
console without registering, without a password, and without ever touching real
graduate data** — then walk away leaving no trace. SPEC §13 mandates exactly this:
a guest/visitor mode with full feature access, ephemeral per-visitor data, session
isolation, a fixed read-only baseline, periodic reset, six presets, an IP rate
limit, and a scheduled cleanup command.

Today the on-ramp is a dead end: `Welcome.tsx` links a "Probar la demo" / `landing.cta.demo`
CTA to `GET /demo`, but **no `/demo` route, controller, DTO, Action, middleware,
command, seeder, page, or `demo_session_id` column exists**. Nothing tags demo
data; nothing cleans it up; nothing rate-limits it; nothing isolates one visitor
from another or from real records.

This slice builds demo mode as a **self-contained vertical slice** under the
existing Identity domain + HTTP layer, with **security as the load-bearing
requirement**: a demo visitor must act **only inside their own `demo_session_id`
sandbox**, must **never see or mutate real (non-demo) students**, and the cleanup
command must **never touch a non-demo row**. After it ships, a recruiter clicks
"Probar la demo", picks one of six presets, is logged into an ephemeral sandbox,
explores for up to 30 minutes, and the scheduled `demo:cleanup` quietly garbage-
collects expired sandboxes every 15 minutes while the baseline catalog stays
pristine.

## 2. Scope

### 2.1 Isolation model (the security spine — decided here)

**Ephemeral, per-session, `demo_session_id`-tagged rows over a shared read-only
baseline.** Each demo login mints a fresh **demo `User`** (and, for the four
student presets, a fresh **`Student`** in the chosen pipeline stage built from the
existing `StudentFactory` states), both stamped with a freshly generated
**`demo_session_id`** (a UUIDv7 string). The visitor's sandbox is exactly the set
of rows carrying *their* `demo_session_id`. Every demo-tagged row is also a
**soft-or-hard-deletable, cleanup-eligible** row; non-demo rows have
`demo_session_id IS NULL` and are **invisible and immutable** to demo visitors.

- **Tagged tables (the exact set demo visitors create/mutate):**
  `users`, `students`, `student_documents`, `jury_assignments`. These are the
  only tables a demo visitor can write through the existing pipeline routes
  (a student preset can begin/upload Annex III → `student_documents`; staff/admin
  presets can verify payment + assign a jury → `jury_assignments`; both presets
  mutate their own `students` row and their own `users` row). Each gets a
  **nullable `demo_session_id` VARCHAR(36)** column + an index.
- **Baseline (shared, read-only, NEVER tagged):** the academic catalogs —
  `departments`, `programs`, `professors`, `graduation_types`, `study_plans`,
  `required_documents`, `graduation_type_required_document` — are the fixed demo
  dataset (a `DemoBaselineSeeder`, fictional per Appendix F). They have **no
  `demo_session_id` column**, are seeded once, and `demo:cleanup` never deletes
  them. Demo students reference them by FK (read-only).
- **`diploma_folio_sequences` is NOT tagged and NOT cleaned** (Open question E):
  a demo graduation would mint a folio that bumps the shared per-program counter.
  This slice **does not let a demo visitor reach the graduate (8→9) action** for
  this reason — see §2.4 destructive-op block — so the sequence is never touched
  in practice; flagged so the gate confirms the carve.
- **Why tag-over-baseline, not a separate DB / schema / connection:** a second
  connection or a tenant schema is heavier than SPEC §13 asks for (§13.4 says
  literally "purges all records tagged with `demo_session_id`") and would fork the
  whole migration set. The tag is the SPEC-named mechanism, is sufficient for
  isolation when **every demo query is scoped by the session's tag** (enforced via
  a global scope + middleware-set context, §2.3), and keeps one schema. The
  isolation guarantee is therefore: *real rows have a NULL tag and are never
  selected by a demo-scoped query; cleanup deletes strictly `WHERE demo_session_id = ?`
  / `IS NOT NULL` rows.* Both halves are proven by tests (§3 scenarios 8-12).

### 2.2 In scope — backend

1. **`demo_session_id` migration(s)** — one migration
   `2026_06_21_000010_add_demo_session_id_to_demo_tables` adding a **nullable
   `string('demo_session_id', 36)->nullable()->index()`** to **`users`,
   `students`, `student_documents`, `jury_assignments`** (exactly those four).
   Reversible `down()` drops the index + column on each. No baseline-catalog table
   is touched. (`demo_session_id` is a UUIDv7 string ⇒ 36 chars.)
2. **`DemoPreset` enum** (`app/Domain/Identity/Enums/DemoPreset.php`) —
   `#[TypeScript] enum DemoPreset: string`, backed, the six presets verbatim from
   SPEC §13.1, each carrying: its `role(): UserRole`, the student pipeline
   `status(): ?GraduationStatus` (null for staff/admin), a `studentFactoryState():
   ?string` (the existing `StudentFactory` state method name, null for staff/admin),
   a `labelKey()` / `descriptionKey()` for i18n. Cases + mapping:

   | case (value)        | role                 | pipeline status            | StudentFactory state | control # (display only) |
   |---------------------|----------------------|----------------------------|----------------------|--------------------------|
   | `sustentante_1`     | `Student`            | `FormBPending` (1)         | *(default state)*    | 20180001                 |
   | `sustentante_2`     | `Student`            | `FormBReview` (2)          | `formBReview`        | 20180002                 |
   | `sustentante_3`     | `Student`            | `AnnexIiiPending` (5)      | `documentsStage`     | 20180003                 |
   | `sustentante_4`     | `Student`            | `JuryAssigned` (7)         | `juryAssigned`       | 20180004                 |
   | `personal`          | `AssistantSecretary` | — (no student)             | —                    | —                        |
   | `admin`             | `Admin`              | — (no student)             | —                    | —                        |

   > The status/state column maps SPEC §13.1's named stages to the **actual**
   > `GraduationStatus` cases + `StudentFactory` states that exist after slices
   > 001-004 (e.g. §13.1 "Documents stage / ANNEX_III_PENDING(5)" → `AnnexIiiPending`
   > + `documentsStage()`; "Jury assigned (7)" → `JuryAssigned` + `juryAssigned()`).
3. **`DemoLoginData` DTO** (`app/Domain/Identity/Data/DemoLoginData.php`) —
   `#[TypeScript] final extends Data`, single field `public DemoPreset $preset`
   (Spatie casts the incoming string to the enum; an out-of-set value fails
   validation → 302 + session error, never a 422). The DTO is the validation SSOT;
   **no FormRequest, no `$request->validate()`**.
4. **`ProvisionDemoSessionAction`** (`app/Domain/Identity/Actions/ProvisionDemoSessionAction.php`)
   — `final`, ctor-injects nothing it cannot resolve (uses factories + `Str::uuid7`).
   `handle(DemoLoginData $data): DemoSessionResult`. In one `DB::transaction`:
   - generate `$demoSessionId = (string) Str::uuid7();`
   - create a demo `User` via `UserFactory` with the preset's `role()`, a unique
     synthetic email (`'demo+'.$demoSessionId.'@uniges.demo'`), a random password,
     and `demo_session_id = $demoSessionId`.
   - if the preset is a student preset, create a `Student` via the preset's
     `StudentFactory` state, `user_id` = the demo user, `control_number` = the
     preset's fictional control number (Appendix F), `demo_session_id =
     $demoSessionId`. The pipeline child rows the chosen state seeds (e.g. a
     `juryAssignment` for `juryAssigned()`, or `student_documents` rows) are
     **also tagged** with the same `demo_session_id` (the Action stamps them; see
     plan §3 for how factory `afterCreating` / explicit tagging is wired).
   - return a `DemoSessionResult` value object `{ user, demoSessionId, preset }`.
   - **No login here** (Action stays free of `Auth`/`Illuminate\Http` per the Law,
     mirroring `AuthenticateUserAction` returning a `User`). The controller logs in
     + stamps the session.
5. **`DemoSessionResult` value object** (`app/Domain/Identity/ValueObjects/DemoSessionResult.php`)
   — `final readonly`, `{ User $user, string $demoSessionId, DemoPreset $preset }`.
6. **`DemoLoginController`** (`app/Http/Controllers/Auth/DemoLoginController.php`) —
   anemic (≤15 lines/method), `final extends Controller`:
   - `store(DemoLoginData $data, ProvisionDemoSessionAction $action): RedirectResponse`
     → **rate-limit gate** (10/IP/hour, §2.5) → `$result = $action->handle($data)`
     → `Auth::guard('web')->login($result->user)` → `request()->session()->put([
     'is_demo' => true, 'demo_session_id' => $result->demoSessionId,
     'demo_expires_at' => now()->addMinutes(30)->timestamp ])` →
     `request()->session()->regenerate()` (preserve the demo keys across
     regeneration) → `redirect()->route($landingRouteFor($result->preset->role()))`.
     Reuse the **same** role→route mapping shape as
     `AuthenticatedSessionController::redirectRouteFor()` (extract to a shared
     helper rather than duplicating — plan §4).
   - **No `create()` / no separate GET demo page is required**; the chooser lives
     on `Welcome.tsx` (or a thin `/demo` GET that re-renders Welcome with the preset
     list) — see §2.6 + Open question A.
7. **`DemoSessionMiddleware`** (`app/Http/Middleware/DemoSessionMiddleware.php`),
   alias `demo` registered in `bootstrap/app.php`, applied to **all authenticated
   pipeline routes** (the `auth`+`role` groups) — behavior in §2.4.
8. **Rate limiter** (§2.5): a named `RateLimiter::for('demo-login', …)` (10 per
   IP per hour) registered in a service provider, applied to the demo-login route
   via `->middleware('throttle:demo-login')`, OR an in-controller
   `RateLimiter::tooManyAttempts('demo-login:'.$ip, 10)` gate that on-exceed
   redirects 302 + `withErrors(['preset' => __('demo.throttled')])`. Decide in plan
   (§4) — **must be a 302 + friendly session error, NOT a 429 JSON** for the web
   path (the project validation convention). It is a **separate** limiter from the
   `LoginThrottle` (per-email brute-force) — do **not** entangle them.
9. **`demo:cleanup` artisan command** (`app/Console/Commands/DemoCleanupCommand.php`,
   signature `demo:cleanup`) + a `DemoCleanupAction`
   (`app/Domain/Identity/Actions/DemoCleanupAction.php`) holding the logic
   (command is a thin wrapper, ≤15 lines). Logic (idempotent):
   - Determine **expired** demo sessions. A demo row is expired when it is older
     than the TTL window — concretely the command **deletes every row whose
     `demo_session_id IS NOT NULL` and whose `created_at < now()->subMinutes(30)`**
     (the 30-min idle TTL; a session that is still active keeps refreshing — see
     §2.4 — so "active" rows are younger than 30 min from last activity; for
     simplicity and the SPEC "every 15 min reset" intent, age-based on `created_at`
     ≥ 30 min is the cut, with a per-session `last_seen` refinement flagged as
     Open question D).
   - Delete in **FK-safe order**, scoped strictly by the tag:
     `jury_assignments` → `student_documents` → `students` → `users`
     (children first; `student_documents`/`jury_assignments` cascade/restrict per
     their FKs — delete the demo-tagged children explicitly first to satisfy the
     `restrictOnDelete` on `jury_assignments.student_id` and the `users` ←
     `students` restrict). All deletes are **`WHERE demo_session_id IS NOT NULL AND
     created_at < cutoff`** (or `IN (<expired session ids>)`).
   - **NEVER** issues a delete without the `demo_session_id IS NOT NULL` predicate;
     **NEVER** touches the baseline catalogs. Hard-deletes (bypasses soft-delete on
     students/student_documents/jury_assignments via `forceDelete()`/`->withTrashed()`
     so cleanup is total — Open question C).
   - Returns a count; the command prints `Cleaned N demo session(s).` (i18n-free
     operator output is acceptable for an artisan command, but the command MUST
     not flash any `__()` key it has not registered; it prints a plain English
     status line — no lang key needed. Flagged in plan §5.)
   - **Idempotent:** running it twice in a row with no expired sessions deletes 0
     rows and never errors.
10. **Schedule registration** (`routes/console.php`) — `Schedule::command('demo:cleanup')
    ->everyFifteenMinutes()->withoutOverlapping();` using the `Illuminate\Support\
    Facades\Schedule` facade (Laravel 12 style — `routes/console.php` is the
    scheduling home; do NOT add a `Console\Kernel`). SPEC §13.4 "every 15 minutes".

### 2.3 Demo query scoping (defense in depth)

A demo visitor must never see a real student in any list/query. Two layers:

- **Layer 1 — the data is already isolated by ownership:** student pipeline pages
  (`Student/Status`, `Student/Documents`, `Student/Payment`) resolve *the
  authenticated user's own* student (`$request->user()->student`), so a demo
  student only ever sees their own tagged row. The student path needs no extra
  scope.
- **Layer 2 — staff/admin list pages (`Graduation/Review`, `DocumentReview`,
  `JuryAssign`, `CeremonySchedule`) query ALL students.** A demo staff/admin must
  see **only demo-tagged students from their own session**, never real graduates.
  This is enforced by a **`DemoScope` global scope** on `Student` (and the child
  models) that, **when the current request is a demo session** (the
  `DemoSessionMiddleware` has set a `demo_session_id` in a request-scoped
  context / container binding), constrains every query to `WHERE demo_session_id =
  <current session id>`; **when NOT a demo session**, constrains to
  `WHERE demo_session_id IS NULL` (real users never see demo rows either — the
  isolation is symmetric). The scope reads the active demo session id from a
  lightweight `DemoContext` singleton the middleware populates. See plan §3.4.
  > This is the load-bearing security control for staff/admin presets. Scenario 8
  > proves a demo session cannot see another session's students; scenario 9 proves
  > a real staff user cannot see demo students and vice-versa.

### 2.4 `DemoSessionMiddleware` behavior

Applied (via the `demo` alias) **wrapping every authenticated pipeline route group**
(the student `auth`+`role:student` group and the staff `auth`+`role:admin,…` group);
it is a **no-op for non-demo sessions** (returns `$next` immediately if
`session('is_demo') !== true`), so real users are unaffected. For a demo session:

1. **TTL check (idle 30-min).** If `now()->timestamp > session('demo_expires_at')`
   → `Auth::logout()` + `session()->invalidate()` + `session()->regenerateToken()`
   → `redirect()->route('landing')->with('error', __('demo.expired'))`. (Optionally
   trigger an immediate purge of *this* session's rows via `DemoCleanupAction
   ::purgeSession($id)` — Open question D; recommend: leave purge to the scheduled
   command to keep the middleware cheap, but mark the session expired so the next
   cleanup sweep collects it.)
2. **Sliding refresh.** On a valid demo request, **bump**
   `session(['demo_expires_at' => now()->addMinutes(30)->timestamp])` so 30 min is
   *idle* TTL (the spec wording "30 min inactivity"), not absolute.
3. **Populate `DemoContext`** with `session('demo_session_id')` so the `DemoScope`
   (and any demo-aware code) can constrain queries this request.
4. **Block destructive ops on real data / system (SPEC §13.3).** Map a small set of
   **disallowed route names** for demo sessions and `abort(403, __key)` /
   redirect-back-with-error if a demo request targets one:
   - the **graduate (8→9) action** `admin.graduation.ceremony.graduate` — would
     mint a shared diploma folio (§2.1) → blocked.
   - any future **destructive/system route** (delete records, modify system
     settings, Excel import, change super user — SPEC §13.3). None of those routes
     exist yet in slices 001-005; the middleware ships with the **graduate** block
     now and a documented `DESTRUCTIVE_ROUTE_NAMES` allowlist constant so later
     slices add their route names in one place. Flagged: only `graduate` is live
     today (Open question B).
   - the block is a **302 redirect-back with `error` flash** `__('demo.blocked')`
     for web (validation convention), not a hard 403 page — so the recruiter sees a
     friendly "not available in demo" toast and stays in the sandbox.
5. **Email suppression** is **already structurally satisfied** in slices 001-005
   because **no email is actually sent** (`BROADCAST_CONNECTION=null`, no Mailable
   wired yet). SPEC §13.3's "EmailService checks `is_demo`" is **DEFERRED** to the
   future notification/email slice; this slice **documents the hook** (the
   `is_demo` session flag is set and available) but adds **no EmailService**.
   Flagged OUT in §2.7. (Open question F.)

### 2.5 Rate limit

10 demo logins per IP per hour, via Laravel's `RateLimiter` (SPEC §13.5 "not
file-based like legacy"). Key = `'demo-login:'.$request->ip()`. On exceed →
**302 redirect-back + `withErrors(['preset' => __('demo.throttled', ['seconds' =>
$retryAfter])])`** (never a 429 JSON for the web path). Distinct limiter from
`LoginThrottle`; do not share state.

### 2.6 In scope — frontend

1. **Wire the `Welcome.tsx` CTA** (`landing.cta.demo`) to a **preset chooser**.
   Two acceptable shapes (decide in plan, Open question A): (a) the `/demo` GET
   route renders a small `Auth/DemoChooser.tsx` Inertia page listing the six
   presets (each a card with label + description + a `POST /demo-login` button
   carrying `preset`); or (b) a modal/disclosure on `Welcome.tsx` itself. Either
   way the six presets come from the **generated `DemoPreset` TS enum** (type-only
   import; compare raw string values cast to the type — per the build rule) and a
   static label/description map in `resources/locales/{es,en}.json`.
   **Recommend (a)**: a dedicated `Auth/DemoChooser` page keeps `Welcome` lean and
   gives a clean prop contract to test.
2. **Demo-mode banner** — a persistent `Components/DemoBanner.tsx` shown on every
   authenticated page **when the shared `demo` prop is present**, with: a label
   ("Modo demostración — los datos son temporales"), a **time-remaining** readout
   (computed from `demo.expires_at`), an **Exit/Reset control** (a `POST /logout`
   button styled as "Salir de la demo" — reuses the existing logout route; logging
   out ends the sandbox and the next cleanup sweep collects its rows). Dark/light +
   ES/EN, WCAG 2.2 AA (role="status", non-color cue).
3. **Shared Inertia `demo` prop** — extend `HandleInertiaRequests::share()` to add
   a `demo` key: `null` for non-demo sessions, else `{ active: true, preset:
   <DemoPreset value string>, expires_at: <unix ts int> }`. **SECURITY:** the
   `demo_session_id` itself is **NOT** exposed in props (it is a server-side
   isolation token; only the public-safe `preset`/`expires_at`/`active` go to the
   client — mirror the existing `auth.user` `->only()` discipline).

### 2.7 i18n

- **Frontend UI copy** in `resources/locales/{es,en}.json`:
  `demo.chooser.title`, `demo.chooser.subtitle`, `demo.preset.sustentante_1..4.title`
  + `.desc`, `demo.preset.personal.title`+`.desc`, `demo.preset.admin.title`+`.desc`,
  `demo.start` (the per-card start button), `demo.banner.label`,
  `demo.banner.remaining` (with a `{minutes}` placeholder), `demo.banner.exit`,
  `landing.cta.demo` (already exists — reused).
- **Flash / validation / middleware messages** in `lang/{es,en}.json` (every
  `__()` key a controller flashes or the middleware/command/rate-limiter throws):
  `demo.expired` (TTL logout flash), `demo.blocked` (destructive-op block flash),
  `demo.throttled` (rate-limit error, `:seconds` placeholder),
  `demo.preset_invalid` (out-of-set preset value — optional; Spatie's enum-cast
  failure may already key `preset`).
  > EVERY such key MUST exist in BOTH `lang/es.json` and `lang/en.json` (memory
  > rule) — a resolution test asserts it (§3 scenario 14).

### 2.8 Seeder

- **`DemoBaselineSeeder`** (`database/seeders/DemoBaselineSeeder.php`) — seeds the
  **fixed, read-only baseline catalogs** (departments, programs, professors,
  graduation_types, study_plans, required_documents + the
  graduation_type_required_document pivot) with the **fictional** Appendix F data
  (programs ISC/II/IE/MCC/LA; 4 departments; ≥10 fictional professors; the 7
  default graduation types). **No `demo_session_id`** on any of these (baseline is
  shared). Registered in `DatabaseSeeder` (or invoked by deploy). Demo *students*
  are **NOT** seeded here — they are minted per-session by
  `ProvisionDemoSessionAction` (ephemeral). PII law: fictional names only.
  > This is the "fixed demo dataset (seeded baseline, read-only)" of CLAUDE.md /
  > SPEC §13. Whether slices 001-004 already created a catalog seeder is checked in
  > plan §recon — if one exists, `DemoBaselineSeeder` reuses/extends it rather than
  > duplicating. (Open question G.)

### 2.9 Tests (Pest, PostgreSQL 18 — never SQLite; Spatie-Data/Auth/DB tests in `tests/Feature/`)

**Backend (Feature):**
- `tests/Feature/Demo/DemoLoginTest.php` — for **each** preset: `POST /demo-login`
  with `preset=<value>` creates exactly one demo `User` (correct `role`, non-null
  `demo_session_id`), logs the visitor in (`assertAuthenticatedAs`), redirects to
  the preset's landing route (student → `student.status`; `personal`/`admin` →
  their mapped routes mirroring `redirectRouteFor`), sets `is_demo=true` +
  `demo_expires_at` in session; for the 4 student presets it also creates exactly
  one demo `Student` in the **correct `GraduationStatus`** (enum-cast assertion:
  `toBe(GraduationStatus::JuryAssigned)`, not the value) tagged with the same
  `demo_session_id`, and the student's child rows (jury_assignment for
  `sustentante_4`) carry the same tag; an **invalid preset value** → 302 + session
  error (never 422), no rows created.
- `tests/Feature/Demo/DemoRateLimitTest.php` — 10 demo logins from one IP succeed;
  the 11th within the hour → 302 + `assertSessionHasErrors('preset')`, no 11th
  user created; the limiter is keyed by IP (a different IP is unaffected). Asserts
  it does NOT consume the per-email `LoginThrottle` ledger.
- `tests/Feature/Demo/DemoSessionMiddlewareTest.php` — a demo session past its
  `demo_expires_at` hitting any pipeline route is logged out + 302 → `landing` with
  `error = __('demo.expired')`; a within-TTL demo request slides `demo_expires_at`
  forward; a **non-demo** authenticated user is unaffected (no logout, no slide); a
  demo session hitting the **graduate** route is blocked (302 + `error =
  __('demo.blocked')`, no graduation, status unchanged, no folio minted).
- `tests/Feature/Demo/DemoIsolationTest.php` — **the security core:**
  1. **Cross-session invisibility:** provision two demo staff/admin sessions A & B
     (each with their own demo students); acting as A's user, `GET
     admin.graduation.review` (and the jury/documents queues) returns **only A's**
     demo students — **never B's**, and **never any real student**. (Asserts the
     `DemoScope` works.)
  2. **Real ⇄ demo invisibility:** a **real** (non-demo) staff user's review queue
     contains the real students and **excludes** every demo-tagged student; a demo
     staff user's queue **excludes** every real student.
  3. **No cross-session mutation:** acting as demo session A, attempt a pipeline
     write against **B's** demo student id (e.g. `POST
     admin.graduation.documents/{B's studentDocument}/approve`) → **404/403** (the
     `DemoScope` makes B's row unresolvable for A), and B's row is unchanged.
- `tests/Feature/Demo/DemoCleanupTest.php` — **the cleanup core:**
  1. Seed the baseline + 1 **real** student (non-demo) + 2 demo sessions, one
     **expired** (`created_at` 31 min ago) and one **fresh** (just now). Run
     `artisan('demo:cleanup')`. Assert: the **expired** session's user/student/
     child rows are **gone** (force-deleted), the **fresh** session's rows
     **remain**, the **real** student + **all baseline catalog rows** are
     **untouched** (exact counts), and the command is **idempotent** (a second run
     deletes 0 and exits 0).
  2. **Never-touch-non-demo invariant:** assert no baseline-catalog row count
     changes and the real student still exists after cleanup. (Falsifiable: if any
     delete lacked the `demo_session_id IS NOT NULL` predicate this fails.)
- `tests/Feature/Demo/DemoLangKeyTest.php` — every PHP `__()` key this slice flashes
  /throws (`demo.expired`, `demo.blocked`, `demo.throttled`, `demo.preset_invalid`)
  resolves in BOTH `es` and `en` and differs from the raw key (mirror
  `AuthLangKeyTest`).
- `tests/Feature/Demo/DemoChooserPagePropsTest.php` (or `DemoLandingPropsTest`) —
  the chooser page renders `Auth/DemoChooser` with a `presets` array of exactly 6
  rows, each `{ value, role, title_key, description_key, control_number|null }`
  snake_case, status/role serialized as enum **value** strings; and, on an
  authenticated demo session, the shared `demo` prop shape `{ active:true, preset,
  expires_at }` is present with **no `demo_session_id` leaked** (`->missing(
  'demo.demo_session_id')`).
- `tests/Feature/Demo/ProvisionDemoSessionActionTest.php` —
  `covers(ProvisionDemoSessionAction::class)`: returns a `DemoSessionResult` whose
  `user.demo_session_id` is set, the student (for student presets) is in the right
  status + tagged, the whole thing is atomic (`DB::transaction`), and two
  invocations produce **distinct** `demo_session_id`s.

**Backend (Unit):**
- `tests/Unit/Identity/DemoPresetTest.php` — pure logic on the enum: every case's
  `role()` is the expected `UserRole`; student cases map to the expected
  `GraduationStatus` + a real `StudentFactory` state-method name; staff/admin cases
  return `null` status/state; `DemoPreset::cases()` count is exactly 6; `labelKey()`
  /`descriptionKey()` shape.

**Arch:**
- extend `tests/Arch/ArchitectureTest.php`: `App\Domain\Identity` already covered
  by the broad rules; assert the new `DemoLoginController` etc. obey
  `controllers never touch Eloquent directly` and `domain never depends on HTTP`
  (the Action must not import `Illuminate\Http`/`Auth` is fine via facade? — note:
  `AuthenticateUserAction` already uses the `Auth` facade, so `Auth`/`Support`
  facades are allowed in the Action; the **login** still happens in the controller).
  Add `cross-domain isolation: Identity does not import other domains'` *only if*
  consistent with existing rules — Identity legitimately imports `Graduation`
  (`GraduationStatus`, `Student`) for the demo student provisioning, so DO NOT add a
  rule forbidding that (Open question H). Keep the `DemoSessionMiddleware`/command
  in the HTTP/Console layers (not `App\Domain`).

**Frontend (Vitest):**
- `resources/js/Components/__tests__/DemoBanner.test.tsx` — renders when the `demo`
  prop is active (label + remaining-minutes + exit button), renders nothing when
  `demo` is null/absent; the remaining-minutes readout reflects `expires_at`.
- `resources/js/Pages/Auth/__tests__/DemoChooser.test.tsx` (if shape (a)) — renders
  6 preset cards, each with a start button posting the right `preset` value.
  (Vitest setup already mocks `window.matchMedia` for ThemeProvider.)

### 2.10 DEFERRED (explicitly OUT)

- **`EmailService` `is_demo` suppression** (SPEC §13.3 bullet) — no email is sent
  in slices 001-005; deferred to the email/notification slice. The `is_demo` flag
  is set and available as the hook. (Open question F.)
- **Demo access to the graduate (8→9) action / any folio-minting** — blocked by the
  middleware this slice; a recruiter still *sees* a `JuryAssigned`/`CeremonyScheduled`
  student and can schedule a ceremony, but **graduating is out** to avoid bumping
  the shared `diploma_folio_sequences` counter. (Open question E.)
- **Real-time / Reverb in demo** — broadcasts already fire on `student.{id}`; no
  demo-specific change. Live updates work in demo automatically.
- **Excel bulk import, system-settings edit, super-user change, record deletes**
  (SPEC §13.3 destructive list) — none of those routes exist yet; the middleware
  ships the `DESTRUCTIVE_ROUTE_NAMES` allowlist with the live `graduate` block and a
  documented extension point. (Open question B.)
- **Multi-tenant / separate-DB isolation** — the `demo_session_id` tag-over-baseline
  is the SPEC mechanism; a heavier connection/schema split is OUT (§2.1).
- **`last_seen`-based precise idle eviction** — this slice uses `created_at`-age ≥
  30 min as the cleanup cut (plus the middleware's per-request slide for the live
  session). A precise per-session `last_seen` column is a refinement deferred.
  (Open question D.)

### 2.11 Deviations / decisions flagged for the gate

- **D-ISO (isolation = tag-over-baseline).** Per-session `demo_session_id`-tagged
  ephemeral rows over a shared read-only seeded baseline + a `DemoScope` global
  scope that symmetric-isolates demo from real. This is the SPEC §13 mechanism;
  argued in §2.1/§2.3. The alternative (separate DB/schema/connection) is rejected
  as heavier than required.
- **D-COL (which tables get the column).** Exactly `users`, `students`,
  `student_documents`, `jury_assignments` — the four a demo visitor can write
  through the existing pipeline. Baseline catalogs deliberately untagged.
- **D-GRAD (graduate blocked in demo).** The 8→9 action is on the demo
  destructive-block list to avoid mutating the shared folio sequence. Gate confirms.
- **D-TTL (idle, sliding 30 min).** `demo_expires_at` slides on each demo request;
  cleanup cuts on `created_at` age ≥ 30 min. (Open question D refinement.)
- **D-EMAIL (suppression deferred).** No EmailService exists; flag set, suppression
  deferred (Open question F).
- **D-SCOPE (symmetric global scope).** Real users see only `demo_session_id IS
  NULL`; demo users see only their own tag. Protects both directions.

## 3. Acceptance scenarios (When… Then)

1. **Demo login provisions a sandbox and signs in (per preset).** *When* a guest
   `POST`s `/demo-login` with a valid `preset`, *then* a fresh demo `User`
   (preset's role, non-null `demo_session_id`) is created, the visitor is
   authenticated, the session carries `is_demo=true` + a 30-min `demo_expires_at`,
   and they are redirected to the preset role's landing route — and for the four
   student presets a demo `Student` in the **correct `GraduationStatus`** (enum
   instance) tagged with the same `demo_session_id` is created (with its stage's
   child rows tagged too).
2. **Invalid preset is rejected.** *When* `/demo-login` is posted with a preset
   value outside the six, *then* the `DemoLoginData` DTO rejects it → 302 + session
   error (never 422), and **no** demo user/student row is created.
3. **Rate limit (10/IP/hour).** *When* an IP performs 10 demo logins within an
   hour, *then* the 11th is refused with a 302 + `error` on `preset`
   (`demo.throttled`), no 11th user is minted; a different IP is unaffected; the
   per-email `LoginThrottle` ledger is untouched.
4. **TTL expiry logs out.** *When* a demo request arrives after
   `now() > demo_expires_at`, *then* the visitor is logged out, the session is
   invalidated, and they are redirected to `landing` with `error = __('demo.expired')`.
5. **TTL slides on activity.** *When* a demo request arrives within the window,
   *then* `demo_expires_at` is bumped to `now()+30min` (idle, not absolute, TTL).
6. **Destructive op blocked.** *When* a demo session hits the graduate
   (`admin.graduation.ceremony.graduate`) route, *then* the request is blocked with
   a 302 + `error = __('demo.blocked')`, **no** graduation occurs, the student's
   status is unchanged, and **no diploma folio is minted** (the shared sequence is
   untouched).
7. **Non-demo users are unaffected.** *When* a real authenticated user hits any
   pipeline route, *then* `DemoSessionMiddleware` is a no-op (no logout, no TTL
   slide, no block), and the `DemoScope` shows them only real (`demo_session_id IS
   NULL`) rows.
8. **Cross-session isolation (read).** *When* demo session A's staff/admin views
   any student list/queue, *then* it shows **only A's** demo students — never
   another demo session B's students, and never any real student.
9. **Real ⇄ demo invisibility.** *When* a real staff user views the review queue,
   *then* it **excludes** every demo-tagged student; *and when* a demo staff user
   views it, *then* it **excludes** every real student.
10. **Cross-session isolation (write).** *When* demo session A attempts a pipeline
    write against demo session B's student/child row, *then* the row is
    unresolvable (404/403) for A and B's row is unchanged — one demo session can
    neither see nor mutate another's data.
11. **Cleanup deletes only expired demo rows.** *When* `demo:cleanup` runs, *then*
    every **expired** (`created_at` ≥ 30 min, `demo_session_id IS NOT NULL`) demo
    user/student/child row is force-deleted, **fresh** demo rows remain, and the
    command returns a count.
12. **Cleanup never touches non-demo rows (CRITICAL).** *When* `demo:cleanup` runs
    with real students + baseline catalogs present, *then* **zero** real or baseline
    rows are deleted (exact counts unchanged) — every delete is scoped by
    `demo_session_id IS NOT NULL`.
13. **Cleanup is idempotent + scheduled.** *When* `demo:cleanup` runs twice with no
    new expirations, *then* the second run deletes 0 and exits 0; *and* it is
    registered in `routes/console.php` to run **every 15 minutes** with
    `withoutOverlapping()`.
14. **Lang keys resolve.** *When* the slice flashes/throws `demo.expired`,
    `demo.blocked`, `demo.throttled` (+ any preset-invalid key), *then* each
    resolves in **both** `es` and `en` and differs from the raw key.
15. **Banner + chooser contract.** *When* the chooser page renders, *then* it
    exposes exactly 6 presets (snake_case `presets` array, enum **value** strings);
    *and when* a demo session loads any authenticated page, *then* the shared `demo`
    prop is `{ active:true, preset, expires_at }` with **no `demo_session_id`
    leaked** to the client, and the `DemoBanner` shows the label + remaining minutes
    + an exit (logout) control.

## 4. Success criteria (definition of done for this slice)

- `composer format` (Pint) clean, `composer analyse` (Larastan **level 9/10**)
  green, `composer test` (Pest: arch + new Unit/Feature/contract tests on
  PostgreSQL 18) green.
- `php artisan typescript:transform` emits `DemoPreset` and `DemoLoginData` into
  `resources/js/types/generated.d.ts`.
- `php artisan migrate:fresh` applies the `demo_session_id` migration cleanly on
  PostgreSQL 18 (nullable + indexed on exactly the four tables; reversible
  `down()`); baseline catalogs unchanged.
- `php artisan demo:cleanup` runs cleanly and is scheduled every 15 min
  (`schedule:list` shows it).
- A recruiter can: click "Probar la demo" → pick a preset → land in a working,
  isolated sandbox → see the demo banner with time remaining → explore the
  pipeline → exit (or be auto-logged-out at 30 min idle) → leave no real-data trace.
- **Security gates (falsifiable):** the isolation + cleanup test groups (§2.9
  `DemoIsolationTest`, `DemoCleanupTest`) pass — proving one demo session cannot
  see or cleanup another's data, demo ⇄ real are mutually invisible, and cleanup
  never deletes a non-demo or baseline row.
- No real PII anywhere (fictional baseline + synthetic demo emails/control numbers);
  `.gitignore` still blocks db/secrets.

## 5. Constitution (§No-negociables) this slice must satisfy

`declare(strict_types=1)` everywhere · explicit return types · `final` domain
classes (`DemoPreset` enum, `ProvisionDemoSessionAction`, `DemoCleanupAction`,
`DemoSessionResult` VO) · `readonly` + constructor promotion on the DTO + VO ·
backed enum (`DemoPreset: string`, reuses `UserRole`/`GraduationStatus`) · **Spatie
Data DTOs only** (`DemoLoginData`; no FormRequest, no `$request->validate()`) ·
Actions one-operation + `DB::transaction` on the multi-write provision/cleanup ·
anemic controllers (≤15 lines, `final`, `->only()` props) · named routes / no
closures · Domain ↛ `Illuminate\Http` (the Action returns a `User`/VO; the
controller does the `Auth::login` + session writes; `Auth`/`Support` facades in
the Action are allowed, as in `AuthenticateUserAction`) · Identity MAY import
`Graduation` (Student/GraduationStatus) for demo provisioning — do not forbid ·
authorization stays in HTTP/middleware (`EnsureRole` + the new `demo` middleware),
never the domain · the rate limiter is the Laravel `RateLimiter` (not file-based),
**separate** from `LoginThrottle` (no duplicated throttle) · Pest with arch tests
on PostgreSQL (never SQLite) · bigint ids (ADR-001; `demo_session_id` is a tag
column, NOT a PK) · every flashed/thrown `__()` key present in BOTH `lang/es.json`
+ `lang/en.json` with a resolution test · Inertia props snake_case + a prop-contract
test per page; the generated TS enum is **type-only** imported (compare raw string
values cast to the type — never value-imported) · dark/light + ES/EN on the chooser
+ banner · **no PII in factories/seeders** · reversible migration · the shared
`demo` Inertia prop **never leaks `demo_session_id`** (public-safe fields only).

## 6. Open questions for the gate

- **A. Chooser shape.** A dedicated `/demo` GET → `Auth/DemoChooser` Inertia page,
  or a modal on `Welcome.tsx`? **Recommend: the dedicated page** (clean prop
  contract + testable; `Welcome` stays lean; the `landing.cta.demo` CTA links to
  `/demo`).
- **B. Destructive-route allowlist scope.** Only the live **graduate** route is on
  the demo block list today (the other §13.3 destructive routes don't exist yet).
  Confirm shipping the block + a documented `DESTRUCTIVE_ROUTE_NAMES` extension
  constant. **Recommend: yes.**
- **C. Hard vs soft delete in cleanup.** `students`/`student_documents`/
  `jury_assignments` use `SoftDeletes`; cleanup should `forceDelete()` (incl.
  `withTrashed()`) so demo rows are truly gone. **Recommend: force-delete.**
- **D. TTL precision.** Idle, sliding 30-min `demo_expires_at` (middleware bumps it)
  + cleanup cut on `created_at` age ≥ 30 min, no extra column. Acceptable, or add a
  `last_seen`? **Recommend: sliding session TTL + `created_at` cut now; defer
  `last_seen`.**
- **E. Demo graduation carve.** Block 8→9 in demo (avoids bumping the shared
  `diploma_folio_sequences`)? **Recommend: yes, block it** (and so the sequence
  table needs no tag/cleanup).
- **F. Email suppression deferral.** No `EmailService` exists; ship the `is_demo`
  flag as the hook and defer suppression to the email slice. **Recommend: defer.**
- **G. Baseline seeder reuse.** Does a catalog seeder already exist from slices
  001-004? Plan §recon checks; if so, `DemoBaselineSeeder` reuses/extends it rather
  than duplicating. **Recommend: reuse if present.**
- **H. Identity→Graduation import.** Provisioning a demo `Student` makes
  `App\Domain\Identity` import `App\Domain\Graduation` (Student, GraduationStatus,
  StudentFactory states). Confirm NOT adding an arch rule forbidding it (the
  existing rules only forbid *other* domains importing Identity, and forbid
  Graduation→Identity — neither is violated). **Recommend: allow; add no new
  forbidding rule.** (If the gate prefers strict isolation, move
  `ProvisionDemoSessionAction` to a thin `App\Domain\Demo` namespace that may import
  both — flagged.)
- **I. Where the rate limiter is registered.** A named `RateLimiter::for('demo-login')`
  in `AppServiceProvider::boot()` + `throttle:demo-login` on the route, vs an
  in-controller `RateLimiter` gate. **Recommend: named limiter + `throttle:` middleware**
  (declarative, testable), with the on-exceed response shaped to a 302 + session
  error via a custom `->response()` callback (so it's not a 429 JSON).
