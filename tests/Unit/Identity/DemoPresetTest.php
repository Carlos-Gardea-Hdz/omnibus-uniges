<?php

declare(strict_types=1);

use App\Domain\Graduation\Enums\GraduationStatus;
use App\Domain\Identity\Enums\DemoPreset;
use App\Domain\Identity\Enums\UserRole;
use Database\Factories\StudentFactory;

/*
 * Pure-logic coverage for the DemoPreset enum (CONTRACT §5, spec §2.2/§2.9).
 * No framework bootstrap, no database — the enum is a plain value object that
 * maps each of the six recruiter presets to its role, pipeline status, the
 * existing StudentFactory state name, fictional control number, and i18n key
 * shapes. The status mapping is the load-bearing contract the provisioning
 * Action depends on, so every case is pinned explicitly.
 */

it('backs exactly the six SPEC §13.1 presets', function (): void {
    expect(DemoPreset::cases())->toHaveCount(6);

    $values = array_map(fn (DemoPreset $preset): string => $preset->value, DemoPreset::cases());

    expect($values)->toBe([
        'sustentante_1',
        'sustentante_2',
        'sustentante_3',
        'sustentante_4',
        'personal',
        'admin',
    ]);
});

it('maps each preset to the expected user role', function (DemoPreset $preset, UserRole $role): void {
    expect($preset->role())->toBe($role);
})->with([
    'sustentante_1 → student' => [DemoPreset::Sustentante1, UserRole::Student],
    'sustentante_2 → student' => [DemoPreset::Sustentante2, UserRole::Student],
    'sustentante_3 → student' => [DemoPreset::Sustentante3, UserRole::Student],
    'sustentante_4 → student' => [DemoPreset::Sustentante4, UserRole::Student],
    'personal → assistant secretary' => [DemoPreset::Personal, UserRole::AssistantSecretary],
    'admin → admin' => [DemoPreset::Admin, UserRole::Admin],
]);

it('maps each student preset to the correct pipeline GraduationStatus', function (DemoPreset $preset, GraduationStatus $status): void {
    // Enum identity, never the value — the provisioning Action stamps this exact
    // case onto the demo Student row (CONTRACT §5/§6).
    expect($preset->status())->toBe($status);
})->with([
    'sustentante_1 → FormBPending' => [DemoPreset::Sustentante1, GraduationStatus::FormBPending],
    'sustentante_2 → FormBReview' => [DemoPreset::Sustentante2, GraduationStatus::FormBReview],
    'sustentante_3 → AnnexIiiPending' => [DemoPreset::Sustentante3, GraduationStatus::AnnexIiiPending],
    'sustentante_4 → JuryAssigned' => [DemoPreset::Sustentante4, GraduationStatus::JuryAssigned],
]);

it('returns a null pipeline status for the staff and admin presets', function (DemoPreset $preset): void {
    expect($preset->status())->toBeNull();
})->with([
    'personal' => [DemoPreset::Personal],
    'admin' => [DemoPreset::Admin],
]);

it('flags the four student presets and only those as student presets', function (): void {
    expect(DemoPreset::Sustentante1->isStudentPreset())->toBeTrue()
        ->and(DemoPreset::Sustentante2->isStudentPreset())->toBeTrue()
        ->and(DemoPreset::Sustentante3->isStudentPreset())->toBeTrue()
        ->and(DemoPreset::Sustentante4->isStudentPreset())->toBeTrue()
        ->and(DemoPreset::Personal->isStudentPreset())->toBeFalse()
        ->and(DemoPreset::Admin->isStudentPreset())->toBeFalse();
});

it('names a real StudentFactory state for the student presets that override the default', function (DemoPreset $preset, string $state): void {
    expect($preset->studentFactoryState())->toBe($state);

    // The named state must actually exist on the factory — otherwise provisioning
    // would fatal at runtime (memory rule: do not invent factory states).
    expect(method_exists(StudentFactory::class, $state))->toBeTrue();
})->with([
    'sustentante_2 → formBReview' => [DemoPreset::Sustentante2, 'formBReview'],
    'sustentante_3 → documentsStage' => [DemoPreset::Sustentante3, 'documentsStage'],
    'sustentante_4 → juryAssigned' => [DemoPreset::Sustentante4, 'juryAssigned'],
]);

it('uses the factory default state for sustentante_1 (no explicit factory state)', function (): void {
    // sustentante_1 lands on the factory default (FormBPending) — no state method.
    expect(DemoPreset::Sustentante1->studentFactoryState())->toBeNull();
});

it('returns a null factory state for the staff and admin presets', function (DemoPreset $preset): void {
    expect($preset->studentFactoryState())->toBeNull();
})->with([
    'personal' => [DemoPreset::Personal],
    'admin' => [DemoPreset::Admin],
]);

it('assigns the fictional Appendix F control numbers to the four student presets', function (DemoPreset $preset, string $controlNumber): void {
    expect($preset->controlNumber())->toBe($controlNumber);
})->with([
    'sustentante_1' => [DemoPreset::Sustentante1, '20180001'],
    'sustentante_2' => [DemoPreset::Sustentante2, '20180002'],
    'sustentante_3' => [DemoPreset::Sustentante3, '20180003'],
    'sustentante_4' => [DemoPreset::Sustentante4, '20180004'],
]);

it('returns a null control number for the staff and admin presets', function (DemoPreset $preset): void {
    expect($preset->controlNumber())->toBeNull();
})->with([
    'personal' => [DemoPreset::Personal],
    'admin' => [DemoPreset::Admin],
]);

it('derives a namespaced i18n label and description key per preset', function (DemoPreset $preset): void {
    expect($preset->labelKey())->toBe("demo.preset.{$preset->value}.title")
        ->and($preset->descriptionKey())->toBe("demo.preset.{$preset->value}.desc");
})->with([
    'sustentante_1' => [DemoPreset::Sustentante1],
    'sustentante_2' => [DemoPreset::Sustentante2],
    'sustentante_3' => [DemoPreset::Sustentante3],
    'sustentante_4' => [DemoPreset::Sustentante4],
    'personal' => [DemoPreset::Personal],
    'admin' => [DemoPreset::Admin],
]);
