<?php

declare(strict_types=1);

use App\Domain\Academic\Models\Department;
use App\Domain\Academic\Models\GraduationType;
use App\Domain\Academic\Models\Professor;
use App\Domain\Academic\Models\Program;
use App\Domain\Academic\Models\RequiredDocument;
use App\Domain\Academic\Models\StudyPlan;
use App\Domain\Identity\Enums\DemoPreset;
use App\Http\Middleware\DemoSessionMiddleware;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * CRITICAL #1-risk security guard (spec 009 §2.6, scenario 10). Catalogs are the
 * SHARED baseline (NOT demo-scoped) — a demo visitor mutating one would corrupt
 * every other user's and every other demo session's data, with no sandbox to
 * contain it. Two independent, falsifiable controls:
 *
 *  (a) THE LIVE CONTROL — the super_admin gate. No DemoPreset mints a super_admin
 *      (admin→Admin, personal→AssistantSecretary, 4×student→Student), so a demo
 *      session is 403'd by EnsureRole BEFORE any catalog controller runs. Every
 *      catalog route (GET + a mutation per catalog) is hit by a demo session of
 *      each preset → 403, with the catalog row count UNCHANGED.
 *
 *  (b) DEFENSE-IN-DEPTH — the 18 mutation route names live in
 *      DemoSessionMiddleware::DESTRUCTIVE_ROUTE_NAMES, so even if the gate were ever
 *      widened to the demo-reachable `admin` role, the middleware would still block
 *      every catalog mutation with a graceful 302 + demo.blocked. A reflection
 *      assertion pins the membership (removing a name OR widening the gate fails a
 *      test) and a forced-guard test drives the middleware directly to prove it
 *      returns the block, not the mutation.
 *
 * PostgreSQL 18 via RefreshDatabase.
 */

/**
 * A demo user (of the given preset) + the session payload the DemoSessionMiddleware
 * reads. Returns [user, sessionId, sessionPayload]. Mirrors the demoSession() helper
 * in DemoSessionMiddlewareTest.
 *
 * @return array{0: User, 1: string, 2: array<string, mixed>}
 */
function catalogDemoSession(DemoPreset $preset): array
{
    $sessionId = (string) Str::uuid7();

    $user = User::factory()->create(['role' => $preset->role()]);
    $user->forceFill(['demo_session_id' => $sessionId])->save();

    $payload = [
        'is_demo' => true,
        'demo_session_id' => $sessionId,
        'demo_preset' => $preset->value,
        'demo_expires_at' => now()->addMinutes(30)->timestamp,
    ];

    return [$user, $sessionId, $payload];
}

/** The 18 catalog mutation route names that MUST be demo-blocked. */
function catalogMutationRouteNames(): array
{
    $names = [];

    foreach (['departments', 'programs', 'professors', 'graduation-types', 'study-plans', 'required-documents'] as $catalog) {
        foreach (['store', 'update', 'destroy'] as $verb) {
            $names[] = "admin.catalogs.{$catalog}.{$verb}";
        }
    }

    return $names; // 6 × 3 = 18
}

// ── (a) THE LIVE CONTROL: a demo session can never mutate a catalog ───────────────
//
// Middleware order on the group is auth → demo → role:super_admin. Two distinct
// (both falsifiable) outcomes, EITHER of which guarantees "no mutation occurs":
//   * a catalog GET (index/hub) is NOT in the destructive list, so `demo` passes
//     and `role:super_admin` returns 403 (a demo Admin/Personal is never super_admin);
//   * a catalog MUTATION (store/update/destroy) IS in the destructive list, so the
//     `demo` middleware short-circuits with a graceful 302 + demo.blocked BEFORE the
//     role gate even runs — the write never reaches the controller.
// A demo session can never escalate to super_admin (no preset mints it), so neither
// outcome is bypassable.

it('forbids a demo session (every preset) from GETting the catalog hub with a 403', function (DemoPreset $preset): void {
    [$user, , $payload] = catalogDemoSession($preset);

    actingAs($user)
        ->withSession($payload)
        ->get(route('admin.catalogs.index'))
        ->assertForbidden(); // role gate: a demo Admin/Personal is not super_admin.
})->with([
    'admin preset' => [DemoPreset::Admin],
    'personal preset' => [DemoPreset::Personal],
]);

it('blocks a demo session creating a department with a 302 + demo.blocked, changing nothing', function (DemoPreset $preset): void {
    [$user, , $payload] = catalogDemoSession($preset);

    $before = Department::query()->count();

    actingAs($user)
        ->withSession($payload)
        ->post(route('admin.catalogs.departments.store'), [
            'code' => 'DEP-HACK',
            'name' => 'No debería crearse',
        ])
        ->assertRedirect() // the demo write-guard 302s before the role gate / controller.
        ->assertSessionHas('error', __('demo.blocked'));

    expect(Department::query()->count())->toBe($before);
    $this->assertDatabaseMissing('departments', ['code' => 'DEP-HACK']);
})->with([
    'admin preset' => [DemoPreset::Admin],
    'personal preset' => [DemoPreset::Personal],
]);

it('blocks a demo session deleting an existing catalog row (302 + blocked, count unchanged)', function (): void {
    [$user, , $payload] = catalogDemoSession(DemoPreset::Admin);

    $department = Department::factory()->create();
    $before = Department::query()->count();

    actingAs($user)
        ->withSession($payload)
        ->delete(route('admin.catalogs.departments.destroy', $department))
        ->assertRedirect()
        ->assertSessionHas('error', __('demo.blocked'));

    expect(Department::query()->count())->toBe($before);
    $this->assertNotSoftDeleted($department);
});

it('forbids a demo session on every catalog index GET with a 403 (role gate)', function (string $name): void {
    [$user, , $payload] = catalogDemoSession(DemoPreset::Admin);

    actingAs($user)
        ->withSession($payload)
        ->get(route($name))
        ->assertForbidden();
})->with([
    'departments' => ['admin.catalogs.departments.index'],
    'programs' => ['admin.catalogs.programs.index'],
    'professors' => ['admin.catalogs.professors.index'],
    'graduation-types' => ['admin.catalogs.graduation-types.index'],
    'study-plans' => ['admin.catalogs.study-plans.index'],
    'required-documents' => ['admin.catalogs.required-documents.index'],
]);

it('blocks a demo session from every catalog destroy (302 + blocked), leaving each catalog count unchanged', function (): void {
    [$user, , $payload] = catalogDemoSession(DemoPreset::Admin);

    $department = Department::factory()->create();
    $program = Program::factory()->create();
    $professor = Professor::factory()->create();
    $type = GraduationType::factory()->create();
    $plan = StudyPlan::factory()->create();
    $document = RequiredDocument::factory()->create();

    $counts = [
        'departments' => Department::query()->count(),
        'programs' => Program::query()->count(),
        'professors' => Professor::query()->count(),
        'graduation_types' => GraduationType::query()->count(),
        'study_plans' => StudyPlan::query()->count(),
        'required_documents' => RequiredDocument::query()->count(),
    ];

    $session = actingAs($user)->withSession($payload);

    // Every destroy is short-circuited by the demo write-guard (302 + demo.blocked).
    $session->delete(route('admin.catalogs.departments.destroy', $department))
        ->assertRedirect()->assertSessionHas('error', __('demo.blocked'));
    $session->delete(route('admin.catalogs.programs.destroy', $program))
        ->assertRedirect()->assertSessionHas('error', __('demo.blocked'));
    $session->delete(route('admin.catalogs.professors.destroy', $professor))
        ->assertRedirect()->assertSessionHas('error', __('demo.blocked'));
    $session->delete(route('admin.catalogs.graduation-types.destroy', $type))
        ->assertRedirect()->assertSessionHas('error', __('demo.blocked'));
    $session->delete(route('admin.catalogs.study-plans.destroy', $plan))
        ->assertRedirect()->assertSessionHas('error', __('demo.blocked'));
    $session->delete(route('admin.catalogs.required-documents.destroy', $document))
        ->assertRedirect()->assertSessionHas('error', __('demo.blocked'));

    expect(Department::query()->count())->toBe($counts['departments'])
        ->and(Program::query()->count())->toBe($counts['programs'])
        ->and(Professor::query()->count())->toBe($counts['professors'])
        ->and(GraduationType::query()->count())->toBe($counts['graduation_types'])
        ->and(StudyPlan::query()->count())->toBe($counts['study_plans'])
        ->and(RequiredDocument::query()->count())->toBe($counts['required_documents']);
});

// ── (b) DEFENSE-IN-DEPTH: the 18 mutation names ARE in DESTRUCTIVE_ROUTE_NAMES ────

/**
 * Read the private DESTRUCTIVE_ROUTE_NAMES constant via reflection so the assertion
 * is falsifiable: dropping a catalog mutation name from the list fails this test.
 *
 * @return list<string>
 */
function destructiveRouteNames(): array
{
    $reflection = new ReflectionClass(DemoSessionMiddleware::class);

    /** @var list<string> $names */
    $names = $reflection->getConstant('DESTRUCTIVE_ROUTE_NAMES');

    return $names;
}

it('registers all 18 catalog mutation routes in the demo destructive write-guard list', function (): void {
    $destructive = destructiveRouteNames();

    foreach (catalogMutationRouteNames() as $name) {
        expect($destructive)->toContain($name);
    }
});

it('does NOT add the read-only catalog index/hub routes to the destructive list', function (string $name): void {
    expect(destructiveRouteNames())->not->toContain($name);
})->with([
    'hub' => ['admin.catalogs.index'],
    'departments index' => ['admin.catalogs.departments.index'],
    'graduation-types index' => ['admin.catalogs.graduation-types.index'],
]);

it('proves the write-guard would block a demo session reaching a catalog mutation if the gate were ever widened', function (string $name): void {
    // Drive the middleware directly with a demo session whose resolved route is one
    // of the (gated-403, but destructive-listed) catalog mutations. This forces the
    // defense-in-depth branch even though the live gate 403s first in production —
    // proving the middleware returns the graceful 302 + demo.blocked and NEVER calls
    // $next (the mutation), for every one of the 18 names.
    [$user, , $payload] = catalogDemoSession(DemoPreset::Admin);

    $session = app('session.store');
    $session->start();
    foreach ($payload as $key => $value) {
        $session->put($key, $value);
    }

    $route = (new Route('POST', "/{$name}", []))->name($name);

    $request = Request::create("/{$name}", 'POST');
    $request->setLaravelSession($session);
    $request->setUserResolver(fn () => $user);
    $request->setRouteResolver(fn () => $route);

    $response = (new DemoSessionMiddleware)->handle($request, function (): never {
        throw new RuntimeException('The destructive write-guard did NOT block a catalog mutation.');
    });

    // The middleware short-circuits with a graceful 302 + demo.blocked, never calling
    // the $next closure above (which would have thrown).
    expect($response->getStatusCode())->toBe(302)
        ->and($session->get('error'))->toBe(__('demo.blocked'));
})->with(catalogMutationRouteNames());
