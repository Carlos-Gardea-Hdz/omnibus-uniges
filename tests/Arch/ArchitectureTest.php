<?php

declare(strict_types=1);

/*
 * Architecture tests encode the OMNIBUS Law as executable rules (SPEC §11.2,
 * testing-sdd §2.1). A violation FAILS the build at CI level.
 */

arch('php security preset')
    ->expect(['die', 'dd', 'dump', 'var_dump', 'eval', 'exec', 'shell_exec', 'system'])
    ->not->toBeUsed();

arch('strict types everywhere')
    ->expect('App')
    ->toUseStrictTypes();

arch('domain code is final')
    ->expect('App\Domain')
    ->classes()
    ->toBeFinal();

arch('value objects are immutable')
    ->expect('App\Domain\Shared\ValueObjects')
    ->toBeReadonly();

arch('enums are backed')
    ->expect('App\Domain\Graduation\Enums')
    ->toBeEnums()
    ->toBeStringBackedEnums();

arch('the domain layer never depends on HTTP')
    ->expect('App\Domain')
    ->not->toUse('Illuminate\Http');

arch('controllers never touch Eloquent directly')
    ->expect('App\Http\Controllers')
    ->not->toUse('Illuminate\Database\Eloquent\Model');

arch('cross-domain isolation: Graduation does not import Identity')
    ->expect('App\Domain\Graduation')
    ->not->toUse('App\Domain\Identity');
