<?php

declare(strict_types=1);

namespace App\Domain\Identity\Enums;

use App\Domain\Graduation\Enums\GraduationStatus;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The six demo-login presets (SPEC §13.1, AUTH-05).
 *
 * Each preset maps a public showcase persona to a real role and — for the four
 * student personas — a real point in the 9-state graduation pipeline plus the
 * exact existing StudentFactory state method that seeds it. The control numbers
 * are the fictional Appendix F demo identifiers (8 digits, valid under the
 * students.control_number CHECK `^[0-9]{8,12}$`).
 *
 * Importing GraduationStatus (the Graduation domain) is allowed: only imports
 * INTO App\Domain\Identity are forbidden by the arch rules, never the reverse.
 */
#[TypeScript]
enum DemoPreset: string
{
    case Sustentante1 = 'sustentante_1';
    case Sustentante2 = 'sustentante_2';
    case Sustentante3 = 'sustentante_3';
    case Sustentante4 = 'sustentante_4';
    case Personal = 'personal';
    case Admin = 'admin';

    /** The role the minted demo user is given. */
    public function role(): UserRole
    {
        return match ($this) {
            self::Sustentante1, self::Sustentante2,
            self::Sustentante3, self::Sustentante4 => UserRole::Student,
            self::Personal => UserRole::AssistantSecretary,
            self::Admin => UserRole::Admin,
        };
    }

    /**
     * The graduation status the demo student should land on, or null for the
     * staff/admin presets (which mint no student).
     */
    public function status(): ?GraduationStatus
    {
        return match ($this) {
            self::Sustentante1 => GraduationStatus::FormBPending,
            self::Sustentante2 => GraduationStatus::FormBReview,
            self::Sustentante3 => GraduationStatus::AnnexIiiPending,
            self::Sustentante4 => GraduationStatus::JuryAssigned,
            self::Personal, self::Admin => null,
        };
    }

    /**
     * The exact existing StudentFactory state method that seeds this preset's
     * stage, or null when the factory default (FormBPending) is correct, or null
     * for non-student presets. Verified against StudentFactory in recon.
     */
    public function studentFactoryState(): ?string
    {
        return match ($this) {
            self::Sustentante1 => null,
            self::Sustentante2 => 'formBReview',
            self::Sustentante3 => 'documentsStage',
            self::Sustentante4 => 'juryAssigned',
            self::Personal, self::Admin => null,
        };
    }

    /** The fictional Appendix F demo control number, or null for non-students. */
    public function controlNumber(): ?string
    {
        return match ($this) {
            self::Sustentante1 => '20180001',
            self::Sustentante2 => '20180002',
            self::Sustentante3 => '20180003',
            self::Sustentante4 => '20180004',
            self::Personal, self::Admin => null,
        };
    }

    /** True when this preset provisions a student (vs. staff/admin). */
    public function isStudentPreset(): bool
    {
        return $this->role() === UserRole::Student;
    }

    /** A fixed, fictional, deterministic display name (NO faker PII). */
    public function displayName(): string
    {
        return match ($this) {
            self::Sustentante1 => 'Estudiante Demo 1',
            self::Sustentante2 => 'Estudiante Demo 2',
            self::Sustentante3 => 'Estudiante Demo 3',
            self::Sustentante4 => 'Estudiante Demo 4',
            self::Personal => 'Personal Demo',
            self::Admin => 'Administrador Demo',
        };
    }

    /** i18n key for the chooser-card title (frontend locales). */
    public function labelKey(): string
    {
        return 'demo.preset.'.$this->value.'.title';
    }

    /** i18n key for the chooser-card description (frontend locales). */
    public function descriptionKey(): string
    {
        return 'demo.preset.'.$this->value.'.desc';
    }
}
