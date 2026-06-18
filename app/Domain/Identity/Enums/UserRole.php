<?php

declare(strict_types=1);

namespace App\Domain\Identity\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** 6-level RBAC roles (SPEC §3.1, ROLE-01). */
#[TypeScript]
enum UserRole: string
{
    case Student = 'student';
    case Admin = 'admin';
    case SuperAdmin = 'super_admin';
    case Secretary = 'secretary';
    case AssistantSecretary = 'assistant_secretary';
    case SchoolServices = 'school_services';

    public function labelKey(): string
    {
        return 'role.'.$this->value;
    }

    public function isStaff(): bool
    {
        return $this !== self::Student;
    }

    public function isPrivileged(): bool
    {
        return in_array($this, [self::Admin, self::SuperAdmin], strict: true);
    }
}
