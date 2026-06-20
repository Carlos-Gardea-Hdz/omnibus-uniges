<?php

declare(strict_types=1);

use App\Domain\Academic\Models\Department;
use App\Domain\Academic\Models\GraduationType;
use App\Domain\Academic\Models\Professor;
use App\Domain\Academic\Models\Program;
use App\Domain\Academic\Models\RequiredDocument;
use App\Domain\Academic\Models\StudyPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * RBAC gating for ALL 25 catalog routes (spec 009 §3 scenario 9, §2.3 — the #1
 * security control). Catalogs sit behind ['auth','demo','role:super_admin'] — the
 * NARROWEST gate (NOT the admin,super_admin,secretary staff trio the reports use),
 * decisively because no DemoPreset mints a super_admin (so a demo session can never
 * reach a catalog route). Therefore:
 *   - a guest is bounced to /login (302, never 403);
 *   - student / assistant_secretary / school_services / SECRETARY / ADMIN → 403
 *     (only super_admin passes — secretary & admin are forbidden HERE, unlike the
 *     report gate, which is the whole point of this slice's narrower gate);
 *   - super_admin → reaches the controller (GET 200; a valid mutation 302).
 * PostgreSQL 18 via RefreshDatabase.
 */

/**
 * Every catalog route as [method, routeName, needsModel] (the model-bound mutation
 * routes resolve a freshly created row of the right type). Read-only routes carry a
 * null model resolver. 1 hub + 6 × (index, store, update, destroy) = 25 routes.
 *
 * @return array<string, array{0: string, 1: string, 2: ?callable}>
 */
function catalogRoutes(): array
{
    return [
        // Hub + the six index GETs (read-only, no model).
        'hub' => ['get', 'admin.catalogs.index', null],
        'departments.index' => ['get', 'admin.catalogs.departments.index', null],
        'programs.index' => ['get', 'admin.catalogs.programs.index', null],
        'professors.index' => ['get', 'admin.catalogs.professors.index', null],
        'graduation-types.index' => ['get', 'admin.catalogs.graduation-types.index', null],
        'study-plans.index' => ['get', 'admin.catalogs.study-plans.index', null],
        'required-documents.index' => ['get', 'admin.catalogs.required-documents.index', null],

        // The 18 mutation routes (store has no bound model; update/destroy do).
        'departments.store' => ['post', 'admin.catalogs.departments.store', null],
        'departments.update' => ['put', 'admin.catalogs.departments.update', fn () => Department::factory()->create()],
        'departments.destroy' => ['delete', 'admin.catalogs.departments.destroy', fn () => Department::factory()->create()],

        'programs.store' => ['post', 'admin.catalogs.programs.store', null],
        'programs.update' => ['put', 'admin.catalogs.programs.update', fn () => Program::factory()->create()],
        'programs.destroy' => ['delete', 'admin.catalogs.programs.destroy', fn () => Program::factory()->create()],

        'professors.store' => ['post', 'admin.catalogs.professors.store', null],
        'professors.update' => ['put', 'admin.catalogs.professors.update', fn () => Professor::factory()->create()],
        'professors.destroy' => ['delete', 'admin.catalogs.professors.destroy', fn () => Professor::factory()->create()],

        'graduation-types.store' => ['post', 'admin.catalogs.graduation-types.store', null],
        'graduation-types.update' => ['put', 'admin.catalogs.graduation-types.update', fn () => GraduationType::factory()->create()],
        'graduation-types.destroy' => ['delete', 'admin.catalogs.graduation-types.destroy', fn () => GraduationType::factory()->create()],

        'study-plans.store' => ['post', 'admin.catalogs.study-plans.store', null],
        'study-plans.update' => ['put', 'admin.catalogs.study-plans.update', fn () => StudyPlan::factory()->create()],
        'study-plans.destroy' => ['delete', 'admin.catalogs.study-plans.destroy', fn () => StudyPlan::factory()->create()],

        'required-documents.store' => ['post', 'admin.catalogs.required-documents.store', null],
        'required-documents.update' => ['put', 'admin.catalogs.required-documents.update', fn () => RequiredDocument::factory()->create()],
        'required-documents.destroy' => ['delete', 'admin.catalogs.required-documents.destroy', fn () => RequiredDocument::factory()->create()],
    ];
}

/** Build the URL, resolving the bound model when the route needs one. */
function catalogUrl(string $name, ?callable $model): string
{
    return $model === null ? route($name) : route($name, $model());
}

it('counts exactly 25 catalog routes under the gate', function (): void {
    expect(catalogRoutes())->toHaveCount(25);
});

it('redirects a guest to login (302, never 403) on every catalog route', function (string $method, string $name, ?callable $model): void {
    $this->{$method}(catalogUrl($name, $model))
        ->assertRedirect(route('login'));
})->with(catalogRoutes());

it('forbids a student on every catalog route with a 403', function (string $method, string $name, ?callable $model): void {
    actingAs(User::factory()->student()->create())
        ->{$method}(catalogUrl($name, $model))
        ->assertForbidden();
})->with(catalogRoutes());

it('forbids assistant_secretary on every catalog route with a 403', function (string $method, string $name, ?callable $model): void {
    actingAs(User::factory()->assistantSecretary()->create())
        ->{$method}(catalogUrl($name, $model))
        ->assertForbidden();
})->with(catalogRoutes());

it('forbids school_services on every catalog route with a 403', function (string $method, string $name, ?callable $model): void {
    actingAs(User::factory()->schoolServices()->create())
        ->{$method}(catalogUrl($name, $model))
        ->assertForbidden();
})->with(catalogRoutes());

it('forbids secretary on every catalog route with a 403 (narrower than the report gate)', function (string $method, string $name, ?callable $model): void {
    actingAs(User::factory()->secretary()->create())
        ->{$method}(catalogUrl($name, $model))
        ->assertForbidden();
})->with(catalogRoutes());

it('forbids admin on every catalog route with a 403 (super_admin-only gate)', function (string $method, string $name, ?callable $model): void {
    actingAs(User::factory()->admin()->create())
        ->{$method}(catalogUrl($name, $model))
        ->assertForbidden();
})->with(catalogRoutes());

it('grants super_admin every catalog GET route with a 200', function (): void {
    $superAdmin = User::factory()->superAdmin()->create();

    foreach (['admin.catalogs.index', 'admin.catalogs.departments.index', 'admin.catalogs.programs.index', 'admin.catalogs.professors.index', 'admin.catalogs.graduation-types.index', 'admin.catalogs.study-plans.index', 'admin.catalogs.required-documents.index'] as $name) {
        actingAs($superAdmin)->get(route($name))->assertOk();
    }
});

it('lets super_admin reach a catalog mutation controller (never 403, never login)', function (string $method, string $name, ?callable $model): void {
    $response = actingAs(User::factory()->superAdmin()->create())
        ->{$method}(catalogUrl($name, $model));

    // The gate passes: the controller runs. A bare/empty payload yields a 302 with
    // validation errors (web convention), NOT a 403 and NOT a redirect to login.
    expect($response->getStatusCode())->not->toBe(403)
        ->and($response->headers->get('Location'))->not->toBe(route('login'));
})->with(array_filter(catalogRoutes(), fn ($route): bool => $route[0] !== 'get'));
