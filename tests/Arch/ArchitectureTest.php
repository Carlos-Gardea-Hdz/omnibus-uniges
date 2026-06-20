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
    ->not->toUse('Illuminate\Http')
    // UploadedFile is the standard Spatie Data file-upload type; the validation
    // DTO is its legitimate boundary. No request/response coupling leaks in.
    ->ignoring('Illuminate\Http\UploadedFile');

arch('controllers never touch Eloquent directly')
    ->expect('App\Http\Controllers')
    ->not->toUse('Illuminate\Database\Eloquent\Model');

arch('cross-domain isolation: Graduation does not import Identity')
    ->expect('App\Domain\Graduation')
    ->not->toUse('App\Domain\Identity');

arch('graduation actions are final')
    ->expect('App\Domain\Graduation\Actions')
    ->classes()
    ->toBeFinal();

arch('graduation events are final')
    ->expect('App\Domain\Graduation\Events')
    ->classes()
    ->toBeFinal();

arch('graduation services are final')
    ->expect('App\Domain\Graduation\Services')
    ->classes()
    ->toBeFinal();

arch('the DocumentStatusChanged event is final')
    ->expect('App\Domain\Graduation\Events\DocumentStatusChanged')
    ->toBeFinal();

/*
 * Slice 003 — Jury domain. The cross-domain isolation rule is the load-bearing
 * one: Jury may lean on Graduation (it advances the same machine) but must never
 * reach into Identity. The final/string-backed rules below are already covered
 * by the broad `App\Domain` rules; they are kept explicit so a future refactor
 * that narrows those rules cannot silently relax the Jury layer.
 */

arch('cross-domain isolation: Jury does not import Identity')
    ->expect('App\Domain\Jury')
    ->not->toUse('App\Domain\Identity');

arch('jury actions are final')
    ->expect('App\Domain\Jury\Actions')
    ->classes()
    ->toBeFinal();

arch('jury events are final')
    ->expect('App\Domain\Jury\Events')
    ->classes()
    ->toBeFinal();

arch('jury enums are string-backed')
    ->expect('App\Domain\Jury\Enums')
    ->toBeEnums()
    ->toBeStringBackedEnums();

/*
 * Slice 004 — Ceremony domain (the 7→8→9 final stretch). Like Jury, Ceremony
 * legitimately leans on Graduation (the state machine, Student, StudentStatusChanged)
 * and Academic (Program) — so the "only Shared" SPEC sketch is deliberately NOT
 * adopted (spec Open Q C). The load-bearing rule is the same one Jury keeps: the
 * domain must never reach into Identity. Final/string-backed are already covered by
 * the broad App\Domain rules; they stay explicit so a future narrowing cannot
 * silently relax the Ceremony layer.
 */

arch('cross-domain isolation: Ceremony does not import Identity')
    ->expect('App\Domain\Ceremony')
    ->not->toUse('App\Domain\Identity');

arch('ceremony actions are final')
    ->expect('App\Domain\Ceremony\Actions')
    ->classes()
    ->toBeFinal();

arch('ceremony events are final')
    ->expect('App\Domain\Ceremony\Events')
    ->classes()
    ->toBeFinal();

arch('ceremony services are final')
    ->expect('App\Domain\Ceremony\Services')
    ->classes()
    ->toBeFinal();

arch('ceremony value objects are immutable')
    ->expect('App\Domain\Ceremony\ValueObjects')
    ->toBeReadonly();

/*
 * Slice 008 — Reporting domain (read-only staff analytics). Reporting
 * legitimately READS the Graduation / Jury / Academic models (Student,
 * JuryAssignment, Program, GraduationType, Professor) to aggregate them — so the
 * "only Shared" SPEC sketch is deliberately NOT adopted (spec 008 Open Q J). The
 * load-bearing rule is the same one Jury / Ceremony keep: the domain must never
 * reach into Identity (authorization stays in HTTP middleware, never the domain).
 * The services are read-only (no Action / event / mutation); final is enforced
 * explicitly so a future narrowing of the broad App\Domain rule cannot silently
 * relax this layer.
 */

arch('cross-domain isolation: Reporting does not import Identity')
    ->expect('App\Domain\Reporting')
    ->not->toUse('App\Domain\Identity');

arch('reporting services are final')
    ->expect('App\Domain\Reporting\Services')
    ->classes()
    ->toBeFinal();
