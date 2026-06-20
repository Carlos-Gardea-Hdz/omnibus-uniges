# Plan 006 — Demo mode (the HOW)

> Companion to `spec.md`. Grounded in a read-only recon of slices 001-005 (the
> canonical pattern) + SPEC.md §13/§3.1/§6/§15/Appendix F. Gated on `CLAUDE.md
> §No-negociables`. Mirrors plan 004/005 structure VERBATIM. **Security
> (per-session isolation + cleanup never touching non-demo rows) is the
> load-bearing requirement — every decision below serves it.**

## 0. Decisions & flagged conflicts (resolve at implement)

| # | Topic | Decision | Why / flag |
|---|---|---|---|
| D1 | **Isolation** | Per-session `demo_session_id` (UUIDv7 string) tag on ephemeral rows over a shared, read-only seeded baseline; a symmetric `DemoScope` global scope. | SPEC §13.4 names this verbatim. Lighter than a 2nd DB/schema/connection. spec §2.1/D-ISO. |
| D2 | **Tagged tables** | `users`, `students`, `student_documents`, `jury_assignments` only. | The four a demo visitor writes via the existing pipeline. Baseline catalogs untagged. spec D-COL. |
| D3 | **DemoPreset enum** | `#[TypeScript] enum DemoPreset: string` with `role()`, `status()`, `studentFactoryState()`, `labelKey()`, `descriptionKey()`, `controlNumber()`. 6 cases per SPEC §13.1 mapped to real `GraduationStatus`/`StudentFactory` states. | Backed enum, no magic strings. spec §2.2 table. |
| D4 | **DTO** | `DemoLoginData { public DemoPreset $preset }` — Spatie casts string→enum; invalid value → 302 + session error. | Spatie Data SSOT; no FormRequest. |
| D5 | **Action returns a VO, not a logged-in session** | `ProvisionDemoSessionAction::handle(DemoLoginData): DemoSessionResult`. The controller does `Auth::login` + session writes. | Domain ↛ `Illuminate\Http`; mirrors `AuthenticateUserAction` returning a `User`. spec D-EMAIL/§5. |
| D6 | **Graduate blocked in demo** | `admin.graduation.ceremony.graduate` on the middleware's `DESTRUCTIVE_ROUTE_NAMES` block list → 302 + `demo.blocked`. | Avoids bumping the shared `diploma_folio_sequences`; so that table needs NO tag/cleanup. spec D-GRAD/E. |
| D7 | **Sliding idle TTL** | `demo_expires_at` bumped on each demo request; cleanup cuts on `created_at` age ≥ 30 min. No new column. | SPEC §13.2 "30 min". spec D-TTL/Open Q D. |
| D8 | **Symmetric scope** | `DemoScope`: demo request → `WHERE demo_session_id = <ctx id>`; non-demo request → `WHERE demo_session_id IS NULL`. | Protects both directions (demo can't see real; real can't see demo). spec D-SCOPE/§2.3. |
| D9 | **Rate limiter** | Named `RateLimiter::for('demo-login', fn=10/IP/hour)` in `AppServiceProvider::boot()`, `->middleware('throttle:demo-login')` on the route, `->response()` shaped to 302 + `withErrors(['preset' => __('demo.throttled')])`. Separate from `LoginThrottle`. | SPEC §13.5 (Laravel limiter, not file-based). spec Open Q I; no duplicated throttle. |
| D10 | **Cleanup force-deletes** | `students`/`student_documents`/`jury_assignments` are `SoftDeletes`; cleanup uses `withTrashed()->forceDelete()`. FK-safe order: jury → docs → students → users. | Demo rows must be truly gone. spec Open Q C. |
| D11 | **Email suppression deferred** | Set `is_demo` flag (the hook); no `EmailService` (none exists). | No mail wired in 001-005. spec Open Q F. |
| D12 | **Identity→Graduation import allowed** | `ProvisionDemoSessionAction` (in `App\Domain\Identity`) imports `Graduation\Models\Student` + `GraduationStatus`. Add NO arch rule forbidding it. | Existing rules only forbid *→Identity* and Graduation→Identity; neither violated. spec Open Q H. |
| D13 | **Chooser page** | Dedicated `GET /demo` → `Auth/DemoChooser.tsx`; `Welcome.tsx` CTA links to `/demo`. | Clean, testable prop contract. spec Open Q A. |

## 1. Architecture (unchanged from 001-005 conventions)

Demo-login lifecycle: **`Welcome` CTA → `GET /demo` (`DemoChooser`, lists 6 presets
from the generated `DemoPreset` enum) → `POST /demo-login` → `DemoLoginController`
(≤15 lines): `throttle:demo-login` gate → `DemoLoginData` DTO (validation SSOT) →
`ProvisionDemoSessionAction` (`DB::transaction`, mints tagged demo `User` [+ tagged
`Student` for student presets]) returns `DemoSessionResult` → controller
`Auth::login` + `session(['is_demo','demo_session_id','demo_expires_at'])` +
`regenerate()` → `redirect()` to the preset role's landing route.**

Per-request demo lifecycle: **`DemoSessionMiddleware` (on the `auth`+`role`
groups): no-op unless `is_demo` → TTL check (logout on expiry) → slide
`demo_expires_at` → populate `DemoContext` (the session's tag) → block destructive
routes.** The `DemoScope` global scope reads `DemoContext` to constrain every
`Student`/child query (demo → own tag; real → `IS NULL`).

Cleanup lifecycle: **`Schedule::command('demo:cleanup')->everyFifteenMinutes()
->withoutOverlapping()` → `DemoCleanupCommand` (thin) → `DemoCleanupAction`
(`DB::transaction`, FK-safe force-deletes scoped strictly by `demo_session_id IS
NOT NULL AND created_at < cutoff`).**

Domain never touches HTTP. `Auth`/`Support`/`RateLimiter` facades in the Action/
controller are allowed (as `AuthenticateUserAction` does). Authorization stays in
`EnsureRole` + the new `demo` middleware.

## 2. Data model

### 2.1 `demo_session_id` — migration `2026_06_21_000010_add_demo_session_id_to_demo_tables`
For **each** of `users`, `students`, `student_documents`, `jury_assignments`:
```php
Schema::table('<table>', function (Blueprint $table): void {
    $table->string('demo_session_id', 36)->nullable()->index()->after('id');
});
```
`down()` reverses each (`dropIndex` then `dropColumn`, mirroring
`add_role_to_users_table`'s down). **No** baseline-catalog table touched. **No**
`diploma_folio_sequences` change (D6 — demo never graduates).

> 36 = UUIDv7 canonical string length. Nullable so every existing/real row stays
> `NULL` (and the symmetric scope treats it as real). Indexed because both the
> scope (`WHERE demo_session_id = ?`) and cleanup (`WHERE demo_session_id IS NOT
> NULL`) filter on it.

### 2.2 Migration order
After `2026_06_20_000010_create_login_attempts_table` (slice 005), add
`2026_06_21_000010_add_demo_session_id_to_demo_tables`. One migration.

### 2.3 Models — add `demo_session_id`
On `App\Models\User`, `App\Domain\Graduation\Models\Student`,
`App\Domain\Graduation\Models\StudentDocument`, `App\Domain\Jury\Models\
JuryAssignment`:
- add `'demo_session_id'` to `$fillable`;
- add `@property string|null $demo_session_id` to the class PHPDoc (the memory rule:
  models outside `app/Models` wire property PHPDoc for every typed attr — here a
  plain nullable string, no cast needed);
- **`Student` + `StudentDocument` + `JuryAssignment` get the `DemoScope` global
  scope** via `protected static function booted(): void { static::addGlobalScope(
  new DemoScope()); }`. **`User` does NOT** get the scope (auth must resolve the
  logged-in demo user by id; scoping `users` would break `Auth`/route-model-binding
  on the user). User isolation is by ownership (a demo user only ever authenticates
  as themselves) + the tag column for cleanup.

### 2.4 `DemoScope` — `app/Domain/Identity/Models/Scopes/DemoScope.php` (or `app/Support/`)
`final implements Illuminate\Database\Eloquent\Scope`:
```php
public function apply(Builder $builder, Model $model): void {
    $sessionId = app(DemoContext::class)->sessionId(); // null when not a demo request
    $sessionId === null
        ? $builder->whereNull($model->getTable().'.demo_session_id')
        : $builder->where($model->getTable().'.demo_session_id', $sessionId);
}
```
> Reads the active demo tag from the `DemoContext` singleton the middleware sets.
> **Symmetric:** non-demo requests (CLI, real users, the cleanup command BEFORE it
> opts out) see only `IS NULL`. **The cleanup command MUST bypass this scope** (it
> targets demo rows) via `Model::withoutGlobalScope(DemoScope::class)` /
> `withTrashed()` — see §3.5. Console requests have a null `DemoContext` so the
> scope would otherwise hide demo rows from cleanup; bypass explicitly.

### 2.5 `DemoContext` — `app/Support/DemoContext.php`
`final class DemoContext { private ?string $sessionId = null; public function
set(?string $id): void; public function sessionId(): ?string; }`. Bound as a
**singleton** in `AppServiceProvider::register()` so the scope + middleware share
one instance per request. Default null (real/CLI requests).

## 3. Domain + Console layer

### 3.1 `app/Domain/Identity/Enums/DemoPreset.php` — `#[TypeScript] enum DemoPreset: string`
Cases + methods (final mapping; `status()`/`studentFactoryState()` null for staff):
```php
case Sustentante1 = 'sustentante_1';
case Sustentante2 = 'sustentante_2';
case Sustentante3 = 'sustentante_3';
case Sustentante4 = 'sustentante_4';
case Personal     = 'personal';
case Admin        = 'admin';

public function role(): UserRole { match: S1-4 => Student; Personal => AssistantSecretary; Admin => Admin }
public function status(): ?GraduationStatus { S1 => FormBPending; S2 => FormBReview; S3 => AnnexIiiPending; S4 => JuryAssigned; Personal/Admin => null }
public function studentFactoryState(): ?string { S1 => null (default); S2 => 'formBReview'; S3 => 'documentsStage'; S4 => 'juryAssigned'; Personal/Admin => null }
public function controlNumber(): ?string { S1 => '20180001'; S2 => '20180002'; S3 => '20180003'; S4 => '20180004'; else null } // Appendix F
public function isStudentPreset(): bool { return $this->role() === UserRole::Student; }
public function labelKey(): string { return 'demo.preset.'.$this->value.'.title'; }
public function descriptionKey(): string { return 'demo.preset.'.$this->value.'.desc'; }
```
> Imports `UserRole` (same Identity domain) + `GraduationStatus` (Graduation —
> allowed, D12). `studentFactoryState()` returns the **exact existing**
> `StudentFactory` state-method names verified in recon (`formBReview`,
> `documentsStage`, `juryAssigned`); S1 uses the factory default (`FormBPending`).

### 3.2 `app/Domain/Identity/Data/DemoLoginData.php` — `#[TypeScript] final extends Data`
```php
public function __construct(public DemoPreset $preset) {}
```
> Spatie casts the request string → `DemoPreset`; an out-of-set value yields a
> validation error keyed `preset` → 302 (web). The DTO feeds the generated TS type.

### 3.3 `app/Domain/Identity/ValueObjects/DemoSessionResult.php` — `final readonly`
```php
public function __construct(
    public User $user,
    public string $demoSessionId,
    public DemoPreset $preset,
) {}
```
> Not `#[TypeScript]` (server-only). Carries an Eloquent `User` (a VO holding a
> model is acceptable here as a transport object between Action and controller; it
> is not persisted). Lives under `Identity\ValueObjects`; the arch "value objects
> are immutable" rule targets `App\Domain\Shared\ValueObjects` only, but `readonly`
> satisfies `App\Domain` finality regardless.

### 3.4 `app/Domain/Identity/Actions/ProvisionDemoSessionAction.php` — `final`
`handle(DemoLoginData $data): DemoSessionResult`:
```php
return DB::transaction(function () use ($data): DemoSessionResult {
    $preset = $data->preset;
    $demoSessionId = (string) Str::uuid7();

    $user = User::factory()->create([
        'role' => $preset->role(),
        'email' => 'demo+'.$demoSessionId.'@uniges.demo',
        'name' => __unused — use a fixed fictional display name per preset, NO faker PII,
        'demo_session_id' => $demoSessionId,
    ]);

    if ($preset->isStudentPreset()) {
        $factory = Student::factory();
        $state = $preset->studentFactoryState();
        if ($state !== null) { $factory = $factory->{$state}(); }
        $student = $factory->create([
            'user_id' => $user->id,
            'control_number' => $preset->controlNumber(),
            'demo_session_id' => $demoSessionId,
            // program/graduation_type/study_plan: resolve from the seeded BASELINE
            // (do NOT let StudentFactory mint new catalogs — pick existing rows so
            // the demo student references the shared read-only baseline), see note.
        ]);
        // Tag the child rows the chosen state seeded (so cleanup + scope see them):
        $student->documents()->withoutGlobalScope(DemoScope::class)->update(['demo_session_id' => $demoSessionId]);
        $student->juryAssignment()?->update(['demo_session_id' => $demoSessionId]);
    }

    return new DemoSessionResult($user, $demoSessionId, $preset);
});
```
> **Baseline FK resolution (important):** `StudentFactory` default uses
> `Program::factory()` etc., which would mint NEW catalog rows per demo login —
> polluting/duplicating the baseline. Instead the Action (or a dedicated
> `demoStudent()` StudentFactory state added in this slice) must pick **existing
> seeded baseline rows** (`Program::query()->inRandomOrder()->first()`, etc.) so
> demo students reference the shared read-only catalogs. Add a
> `StudentFactory::demoBaseline()` state (resolves existing program/type/plan) and
> compose it: `Student::factory()->demoBaseline()->{$state}()`. Flag: if no baseline
> is seeded in the test/runtime, `demoBaseline()` falls back to `factory()` (so
> tests that don't seed still pass) — but the runtime path always seeds the baseline
> first. See §6 factories.
> **No faker PII:** the demo user `name` is a fixed fictional per-preset label
> (e.g. "Estudiante Demo 1", "Personal Demo"), NOT `fake()->name()`. The student's
> first/last names come from the factory but are fictional — acceptable (no real
> data); prefer fixed demo names to keep the showcase deterministic (Open Q in spec
> not raised — implementer may keep factory faker for student names since they are
> fictional, but the email + control number are deterministic).
> **Atomic + distinct ids:** the whole provision is one `DB::transaction`; each call
> mints a fresh UUIDv7 ⇒ distinct sessions (tested).

### 3.5 `app/Domain/Identity/Actions/DemoCleanupAction.php` — `final`
`handle(?CarbonInterface $now = null): int` (returns rows deleted; injectable `now`
for tests):
```php
$cutoff = ($now ?? now())->copy()->subMinutes(30);
return DB::transaction(function () use ($cutoff): int {
    $deleted = 0;
    // FK-safe order, ALWAYS scoped by the tag, bypassing the DemoScope + soft-deletes.
    $deleted += JuryAssignment::withoutGlobalScope(DemoScope::class)->withTrashed()
        ->whereNotNull('demo_session_id')->where('created_at', '<', $cutoff)->forceDelete();
    $deleted += StudentDocument::withoutGlobalScope(DemoScope::class)->withTrashed()
        ->whereNotNull('demo_session_id')->where('created_at', '<', $cutoff)->forceDelete();
    $deleted += Student::withoutGlobalScope(DemoScope::class)->withTrashed()
        ->whereNotNull('demo_session_id')->where('created_at', '<', $cutoff)->forceDelete();
    $deleted += User::where('role', '!=', null) // User has no scope/soft-delete
        ->whereNotNull('demo_session_id')->where('created_at', '<', $cutoff)->delete();
    return $deleted;
});
```
> **Every** delete carries `whereNotNull('demo_session_id')` — the falsifiable
> invariant (scenario 12). Force-delete on the soft-deleted models (D10). `users`
> is a plain delete (no soft-delete). Children before parents to respect
> `restrictOnDelete` on `jury_assignments.student_id` and `users ← students`.
> A `purgeSession(string $id): int` sibling method (same body but
> `where('demo_session_id', $id)` and no cutoff) supports the optional
> middleware-triggered immediate purge (Open Q D — recommend NOT wiring it; the
> scheduled sweep is enough).

### 3.6 `app/Console/Commands/DemoCleanupCommand.php` — `final extends Command`
`$signature = 'demo:cleanup'`, `$description = 'Purge expired demo-session data ...'`.
`handle(DemoCleanupAction $action): int { $n = $action->handle(); $this->info("Cleaned
{$n} demo session row(s)."); return self::SUCCESS; }` (≤15 lines; plain English
operator output — no `__()` key needed, so nothing to add to lang).

### 3.7 Schedule (`routes/console.php`)
Append (Laravel 12 facade style — `routes/console.php` is the scheduling home):
```php
use Illuminate\Support\Facades\Schedule;
Schedule::command('demo:cleanup')->everyFifteenMinutes()->withoutOverlapping();
```

## 4. HTTP layer

### 4.1 `app/Http/Controllers/Auth/DemoLoginController.php` — `final extends Controller`
- `create(): Response` → `Inertia::render('Auth/DemoChooser', ['presets' =>
  collect(DemoPreset::cases())->map(fn (DemoPreset $p) => [
    'value' => $p->value, 'role' => $p->role()->value,
    'title_key' => $p->labelKey(), 'description_key' => $p->descriptionKey(),
    'control_number' => $p->controlNumber(),
  ])])` (snake_case; enum **value** strings).
- `store(DemoLoginData $data, ProvisionDemoSessionAction $action): RedirectResponse`:
  ```php
  $result = $action->handle($data);
  Auth::guard('web')->login($result->user);
  $request->session()->put([
      'is_demo' => true,
      'demo_session_id' => $result->demoSessionId,
      'demo_expires_at' => now()->addMinutes(30)->timestamp,
  ]);
  $request->session()->regenerate(); // keeps the put() keys (regenerate ≠ invalidate)
  return redirect()->route($this->landingRouteFor($result->preset->role()));
  ```
  ≤15 lines. Rate limiting is applied via route middleware (§4.2), not here.
- **Shared role→route mapping:** extract `AuthenticatedSessionController::
  redirectRouteFor()` into a small reusable `App\Http\Support\RoleLandingRoute`
  (a `final` helper with `static for(UserRole): string`) and have BOTH the auth
  controller and this controller call it — **do not duplicate** the match (the
  prompt's "do not duplicate cross-cutting logic" rule). The mapping is unchanged:
  Student → `student.status`; Admin/SuperAdmin/Secretary → `admin.graduation.review`;
  AssistantSecretary/SchoolServices → `landing`.
  > `personal` preset = `AssistantSecretary` → lands on `landing`. That is correct
  > per the existing mapping; the recruiter still navigates the staff console via
  > links. If a richer staff landing is wanted, that is a separate auth-mapping
  > change, OUT of scope here.

### 4.2 Routes (`routes/web.php`) — named, no closures
In the existing `guest` group (a logged-in user shouldn't re-demo):
```php
Route::get('/demo', [DemoLoginController::class, 'create'])->name('demo.create');
Route::post('/demo-login', [DemoLoginController::class, 'store'])
    ->middleware('throttle:demo-login')
    ->name('demo.store');
```
> Route name for the chooser is `demo.create`; the `Welcome` CTA links to
> `route('demo.create')` / `/demo`. SPEC §6 row 1603 names the action endpoint
> `/demo-login` (`Auth\DemoLoginController@store`) — matched.

### 4.3 `DemoSessionMiddleware` (`app/Http/Middleware/DemoSessionMiddleware.php`, `final`)
Alias `demo` in `bootstrap/app.php` `$middleware->alias([... 'demo' =>
DemoSessionMiddleware::class])`. Wrap the authenticated route groups — change
`->middleware(['auth', 'role:student'])` → `['auth', 'demo', 'role:student']` and
the staff group likewise (the signed-download route stays as-is). Behavior:
```php
public function handle(Request $request, Closure $next): Response {
    if ($request->session()->get('is_demo') !== true) {
        return $next($request); // no-op for real users (scenario 7)
    }
    $expiresAt = (int) $request->session()->get('demo_expires_at', 0);
    if (now()->timestamp > $expiresAt) {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('landing')->with('error', __('demo.expired'));
    }
    // populate context for the DemoScope this request
    app(DemoContext::class)->set((string) $request->session()->get('demo_session_id'));
    // destructive-op block
    if (in_array($request->route()?->getName(), self::DESTRUCTIVE_ROUTE_NAMES, true)) {
        return back()->with('error', __('demo.blocked'));
    }
    // slide the idle TTL
    $request->session()->put('demo_expires_at', now()->addMinutes(30)->timestamp);
    return $next($request);
}
private const DESTRUCTIVE_ROUTE_NAMES = ['admin.graduation.ceremony.graduate'];
```
> `DESTRUCTIVE_ROUTE_NAMES` is the single documented extension point (Open Q B):
> future slices add their delete/import/settings route names here. Today only the
> live folio-minting graduate route is blocked. The block is a 302 + flash (web
> convention), not a 403 page. **Order:** the `demo` alias must run AFTER `auth`
> (needs the session/user) and BEFORE `role` is fine (block/TTL is independent of
> role) — place it `['auth','demo','role:...']`.

### 4.4 Shared Inertia `demo` prop (`HandleInertiaRequests::share()`)
Add to the returned array:
```php
'demo' => $request->session()->get('is_demo') === true ? [
    'active' => true,
    'preset' => $request->session()->get('demo_preset'), // store preset value at login too
    'expires_at' => (int) $request->session()->get('demo_expires_at'),
] : null,
```
> **SECURITY:** never include `demo_session_id` (server-only isolation token). Store
> the preset *value* in the session at login (`'demo_preset' => $result->preset->
> value`) so the banner can label it without exposing the id. Public-safe fields
> only (mirrors the `auth.user` `->only()` discipline; the shared-props security
> note in `HandleInertiaRequests` already warns about this).

### 4.5 Rate limiter (`AppServiceProvider::boot()`)
```php
RateLimiter::for('demo-login', fn (Request $request) =>
    Limit::perHour(10)->by($request->ip())
        ->response(fn () => back()->withErrors([
            'preset' => __('demo.throttled'),
        ])));
```
> 10/IP/hour (SPEC §13.5). The `->response()` callback turns the limit hit into a
> **302 + session error** (not a 429 JSON) for the web path. Separate limiter name
> from anything `LoginThrottle` uses (they don't intersect — `LoginThrottle` is a
> DB ledger keyed by email, this is the in-memory `RateLimiter` keyed by IP).

## 5. Frontend (Inertia 2 + React 19 + TS)

### 5.1 `resources/js/Pages/Auth/DemoChooser.tsx`
- Props: `presets: Array<{ value: string; role: string; title_key: string;
  description_key: string; control_number: string | null }>` (snake_case from the
  controller). Type the `value` against the **generated** `DemoPreset` enum via a
  **type-only** import (`import type { DemoPreset } from '@/types/generated'`),
  comparing raw strings cast to the type — **never value-import** it (build rule).
- Renders 6 cards (label = `t(p.title_key)`, desc = `t(p.description_key)`,
  optional control-number chip), each with a "start" button that `router.post(
  route('demo.store'), { preset: p.value })`. Reuse the `useLocale` + DarkMode +
  LanguageSwitcher chrome from `Welcome.tsx`; WCAG 2.2 AA.
- `Welcome.tsx`: change the CTA `href="/demo"` (already there) to
  `href={route('demo.create')}` if Ziggy is available, else keep `/demo` — verify
  in recon whether `route()` is wired on the client; if not, plain `/demo` is fine
  (the GET route exists).

### 5.2 `resources/js/Components/DemoBanner.tsx`
- Reads the shared `demo` prop via `usePage().props.demo`. Renders nothing when
  `demo` is null/absent. When `{ active, preset, expires_at }`:
  a `role="status"` bar with `t('demo.banner.label')`, a remaining-minutes readout
  (`Math.max(0, Math.ceil((expires_at*1000 - Date.now())/60000))` via
  `t('demo.banner.remaining', {minutes})` — the `useLocale().t` supports no interp
  today; either extend `t` to accept params or compose the string in the component;
  **decide in implement** — simplest: `${t('demo.banner.remaining')} ${minutes}`),
  and an **exit** control = a `<Link as="button" method="post" href={route('logout')}>`
  / `router.post(route('logout'))` labeled `t('demo.banner.exit')`. Non-color cue
  (icon + text). Dark/light.
- **Mounting:** render `<DemoBanner />` once in the shared layout used by the
  authenticated pages (recon: identify the layout component the Student/Graduation
  pages wrap with; if pages render standalone, add `<DemoBanner />` near the top of
  each authenticated page or in the root `app.tsx` resolve wrapper). Flag the exact
  mount point for implement based on recon of the layout structure.

### 5.3 i18n
- **UI copy** `resources/locales/{es,en}.json`: `demo.chooser.title|subtitle`,
  `demo.preset.{sustentante_1,sustentante_2,sustentante_3,sustentante_4,personal,
  admin}.{title,desc}`, `demo.start`, `demo.banner.label`, `demo.banner.remaining`,
  `demo.banner.exit`. (`landing.cta.demo` already exists — reused.)
- **Flash/validation** `lang/{es,en}.json`: `demo.expired`, `demo.blocked`,
  `demo.throttled` (with `:seconds`? — the limiter response above doesn't pass
  seconds; keep `demo.throttled` parameterless OR add `:seconds` and pass
  `$headers['Retry-After']` — implementer choice, keep it parameterless for
  simplicity), and `demo.preset_invalid` only if a custom message is wired for the
  enum-cast failure (otherwise Spatie's default keyed on `preset` is fine — but to
  satisfy the lang-key resolution test, only register keys actually flashed).
  > EVERY `__()` key the controller/middleware/limiter flashes MUST exist in BOTH
  > `lang/es.json` and `lang/en.json` (memory rule) → asserted by `DemoLangKeyTest`.

## 6. Tests (Pest, PostgreSQL 18 — never SQLite)

(Full list in spec §2.9; here are the placement + factory wiring specifics.)

- **`tests/Feature/Demo/*`** — all the login/rate-limit/middleware/isolation/cleanup/
  langkey/props/action tests (Spatie-Data/Auth/DB → `tests/Feature`, per the memory
  rule). `uses(RefreshDatabase::class)`. Where a baseline is needed
  (`DemoLoginTest`, `DemoIsolationTest`, `DemoCleanupTest`), seed it via
  `$this->seed(DemoBaselineSeeder::class)` in a `beforeEach`.
- **`tests/Unit/Identity/DemoPresetTest.php`** — pure enum logic, no DB.
- **`tests/Arch/ArchitectureTest.php`** — extend with: `DemoSessionMiddleware`/
  `DemoLoginController` obey `controllers never touch Eloquent directly` (already a
  global rule — they don't); the Action stays out of `Illuminate\Http` (the global
  `domain never depends on HTTP` rule covers it). **Add NO** rule forbidding
  Identity→Graduation (D12). Optionally assert the new Identity Actions are final
  (covered by the broad `App\Domain` rule). Keep `DemoContext`/`DemoScope` placement
  consistent: if `DemoScope` lives under `App\Domain\Identity\Models\Scopes`, the
  `domain is final` rule applies (make it `final`); `DemoContext` under `App\Support`
  is outside the domain rules.
- **Factories:**
  - `UserFactory` — **add** a `demo(string $sessionId)` state setting
    `demo_session_id` (+ optionally a fixed fictional `name`), reused by tests and
    (optionally) the Action. Keep existing role states.
  - `StudentFactory` — **add** `demo(string $sessionId)` (sets `demo_session_id`)
    and `demoBaseline()` (resolves EXISTING seeded `Program`/`GraduationType`/
    `StudyPlan`/(optionally advisor) rows instead of minting new ones, with a
    `factory()` fallback when none seeded — §3.4 note). Reuse the existing
    pipeline-stage states (`formBReview`, `documentsStage`, `juryAssigned`).
    **Do NOT** add a fake-unique on a small fixed pool.
  - **No new model factories** (no new tables besides the column add).
- **Vitest:** `Components/__tests__/DemoBanner.test.tsx`,
  `Pages/Auth/__tests__/DemoChooser.test.tsx` (the setup already mocks
  `window.matchMedia`).
- All DB tests on **pgsql** (`RefreshDatabase`).

## 7. Infra
Reuse the 001-005 `docker-compose.yml` + `phpunit.xml` (`pgsql`,
`BROADCAST_CONNECTION=null`, session driver `database` / cache `database` per
`config/*`; Valkey/redis used in prod but tests use the array/database defaults).
**No new config, no new disk, no new package.** The named rate limiter lives in
`AppServiceProvider`. `.gitignore` unchanged (still blocks db/secrets — the demo
emails/control numbers are fictional, but verify no real PII before push).

## 8. File manifest

**Create (~18):**
- 1 migration `2026_06_21_000010_add_demo_session_id_to_demo_tables`.
- `app/Domain/Identity/Enums/DemoPreset.php`.
- `app/Domain/Identity/Data/DemoLoginData.php`.
- `app/Domain/Identity/ValueObjects/DemoSessionResult.php`.
- `app/Domain/Identity/Actions/ProvisionDemoSessionAction.php`.
- `app/Domain/Identity/Actions/DemoCleanupAction.php`.
- `app/Domain/Identity/Models/Scopes/DemoScope.php` (or `app/Support/DemoScope.php`).
- `app/Support/DemoContext.php`.
- `app/Http/Controllers/Auth/DemoLoginController.php`.
- `app/Http/Middleware/DemoSessionMiddleware.php`.
- `app/Http/Support/RoleLandingRoute.php` (extracted shared role→route helper).
- `app/Console/Commands/DemoCleanupCommand.php`.
- `database/seeders/DemoBaselineSeeder.php`.
- `resources/js/Pages/Auth/DemoChooser.tsx`.
- `resources/js/Components/DemoBanner.tsx`.
- ~9 test files (Feature/Demo/*, Unit/Identity/DemoPresetTest, 2 Vitest).

**Modify (~12):**
- `app/Models/User.php` (+ `demo_session_id` fillable + PHPDoc).
- `app/Domain/Graduation/Models/Student.php` (+ fillable/PHPDoc + `booted()`
  DemoScope).
- `app/Domain/Graduation/Models/StudentDocument.php` (+ fillable/PHPDoc + DemoScope).
- `app/Domain/Jury/Models/JuryAssignment.php` (+ fillable/PHPDoc + DemoScope).
- `app/Http/Controllers/Auth/AuthenticatedSessionController.php` (use the extracted
  `RoleLandingRoute`).
- `app/Http/Middleware/HandleInertiaRequests.php` (+ shared `demo` prop).
- `app/Providers/AppServiceProvider.php` (`DemoContext` singleton + the
  `demo-login` named rate limiter).
- `bootstrap/app.php` (+ `demo` alias).
- `routes/web.php` (+ `/demo`, `/demo-login`; add `demo` to the two auth groups).
- `routes/console.php` (+ the `demo:cleanup` schedule).
- `resources/js/Pages/Welcome.tsx` (CTA → `demo.create` / `/demo`).
- `resources/locales/{es,en}.json` + `lang/{es,en}.json` (i18n).
- `tests/Arch/ArchitectureTest.php` + `database/factories/{UserFactory,
  StudentFactory}.php` (demo states).
- `database/seeders/DatabaseSeeder.php` (call `DemoBaselineSeeder`).

**Generated:** `resources/js/types/generated.d.ts` (`DemoPreset` + `DemoLoginData`)
via `typescript:transform`. **No new ADR** (continues ADR-001; `demo_session_id` is
a tag column, not a PK). **No new package.**

## 9. Verify order (phase 5 — orchestrator runs the full suite; agents only self-check PHPStan + tsc read-only per the build rules)
`composer format` → `composer analyse` (L9/10) → `typescript:transform` → `sail up
-d` → `sail artisan migrate:fresh --seed` → `sail pest` (Unit+Arch+Feature on pgsql,
incl. the isolation + cleanup security groups) → `sail artisan schedule:list`
(assert `demo:cleanup` every 15 min) → `pnpm tsc --noEmit` → `pnpm lint` → `pnpm
vitest run` → `pnpm build`. Evidence captured for the gate.

## 10. Constitution check
strict_types ✓ · explicit returns ✓ · final domain (DemoPreset/Action/VO/cleanup) ✓
· readonly DTO + VO ✓ · backed enum (`DemoPreset: string`; reuses
`UserRole`/`GraduationStatus`) ✓ · Spatie Data only (`DemoLoginData`; no FormRequest)
✓ · Action one-op + `DB::transaction` (provision + cleanup multi-write) ✓ · anemic
controller (≤15 lines, `->only()`/`->map()` props, no Eloquent) ✓ · named routes /
no closures ✓ · Domain ↛ `Illuminate\Http` (Action returns VO; controller does
`Auth::login`+session; facades allowed as in `AuthenticateUserAction`) ✓ ·
Identity→Graduation import allowed, no other domain→Identity (rules intact) ✓ ·
authorization in HTTP/middleware only (`EnsureRole` + `demo`) ✓ · rate limiter =
Laravel `RateLimiter`, separate from `LoginThrottle` (no duplicated throttle) ✓ ·
Pest + arch on Postgres ✓ · bigint ids per ADR-001 (`demo_session_id` is a tag, not
a PK) ✓ · every flashed/thrown `__()` key in BOTH `lang/es.json`+`lang/en.json` +
resolution test ✓ · Inertia props snake_case + prop-contract test ✓ · generated TS
enum type-only imported ✓ · dark/light + ES/EN (chooser + banner) ✓ · no PII
(fictional baseline + synthetic demo emails/control numbers; no faker on the demo
user name) ✓ · reversible migration ✓ · shared `demo` prop never leaks
`demo_session_id` ✓ · **isolation proven (cross-session + demo⇄real) and cleanup
never touches non-demo/baseline rows — the load-bearing security guarantees** ✓.
```
