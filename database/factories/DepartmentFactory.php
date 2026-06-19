<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Academic\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        /** @var string $area */
        $area = fake()->unique()->randomElement([
            'Ingeniería en Sistemas Computacionales',
            'Ingeniería Industrial',
            'Ingeniería Electrónica',
            'Ciencias Económico-Administrativas',
            'Ingeniería Civil',
            'Ingeniería Mecánica',
            'Ingeniería Química',
            'Ciencias Básicas',
        ]);

        return [
            'code' => 'DEP-'.Str::upper(fake()->unique()->bothify('??##')),
            'name' => 'Departamento de '.$area,
        ];
    }
}
