<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Academic\Models\Department;
use App\Domain\Academic\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Program>
 */
class ProgramFactory extends Factory
{
    protected $model = Program::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'PRG-'.Str::upper(fake()->unique()->bothify('??##')),
            'name' => fake()->unique()->randomElement([
                'Ingeniería en Sistemas Computacionales',
                'Ingeniería Industrial',
                'Ingeniería en Gestión Empresarial',
                'Ingeniería Electrónica',
                'Ingeniería Civil',
                'Ingeniería Mecatrónica',
                'Licenciatura en Administración',
                'Ingeniería Química',
            ]),
            'department_id' => Department::factory(),
        ];
    }
}
