<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Academic\Models\Department;
use App\Domain\Academic\Models\GraduationType;
use App\Domain\Academic\Models\Professor;
use App\Domain\Academic\Models\Program;
use App\Domain\Academic\Models\RequiredDocument;
use App\Domain\Academic\Models\StudyPlan;
use Illuminate\Database\Seeder;

/**
 * The fixed, read-only baseline catalogs for demo mode (SPEC §13.4, Appendix F).
 *
 * This is the shared stage every demo session performs on: departments, programs,
 * study plans, professors, graduation types and the required-document slots that
 * hang off them. NONE of these rows carry a `demo_session_id` (they are real,
 * untagged catalogs the symmetric DemoScope leaves visible to demo and non-demo
 * alike) and `demo:cleanup` therefore never touches them.
 *
 * Demo STUDENTS are NOT seeded here — they are minted per session by
 * ProvisionDemoSessionAction (tagged + ephemeral). This seeder only lays the
 * baseline those students reference via StudentFactory::demoBaseline().
 *
 * All data is fictional (Appendix F). Idempotent: keyed on the unique `code`
 * columns via firstOrCreate, so re-running never duplicates or collides.
 */
final class DemoBaselineSeeder extends Seeder
{
    public function run(): void
    {
        $departments = $this->seedDepartments();
        $programs = $this->seedPrograms($departments);
        $this->seedStudyPlans($programs);
        $this->seedProfessors();
        $graduationTypes = $this->seedGraduationTypes();
        $documents = $this->seedRequiredDocuments();
        $this->attachRequiredDocuments($graduationTypes, $documents);
    }

    /**
     * @return array<string, Department>
     */
    private function seedDepartments(): array
    {
        $rows = [
            'DEP-SIST' => 'Departamento de Sistemas y Computación',
            'DEP-IND' => 'Departamento de Ingeniería Industrial',
            'DEP-ELEC' => 'Departamento de Ingeniería Electrónica',
            'DEP-ECON' => 'Departamento de Ciencias Económico-Administrativas',
        ];

        $departments = [];
        foreach ($rows as $code => $name) {
            $departments[$code] = Department::firstOrCreate(['code' => $code], ['name' => $name]);
        }

        return $departments;
    }

    /**
     * @param  array<string, Department>  $departments
     * @return array<string, Program>
     */
    private function seedPrograms(array $departments): array
    {
        // SPEC Appendix F program codes: ISC, II, IE, MCC, LA.
        $rows = [
            'ISC' => ['Ingeniería en Sistemas Computacionales', 'DEP-SIST'],
            'II' => ['Ingeniería Industrial', 'DEP-IND'],
            'IE' => ['Ingeniería Electrónica', 'DEP-ELEC'],
            'MCC' => ['Maestría en Ciencias de la Computación', 'DEP-SIST'],
            'LA' => ['Licenciatura en Administración', 'DEP-ECON'],
        ];

        $programs = [];
        foreach ($rows as $code => [$name, $departmentCode]) {
            $programs[$code] = Program::firstOrCreate(
                ['code' => $code],
                ['name' => $name, 'department_id' => $departments[$departmentCode]->id],
            );
        }

        return $programs;
    }

    /**
     * One fictional study plan per program (consistent program_id so
     * demoBaseline() always finds a plan matching the chosen program).
     *
     * @param  array<string, Program>  $programs
     */
    private function seedStudyPlans(array $programs): void
    {
        foreach ($programs as $programCode => $program) {
            StudyPlan::firstOrCreate(
                ['code' => 'SP-'.$programCode.'-2020'],
                ['name' => 'Plan de Estudios '.$programCode.' 2020', 'program_id' => $program->id],
            );
        }
    }

    private function seedProfessors(): void
    {
        // Ten fixed fictional professors — enough to seat a 4-member jury with
        // distinct members and to populate advisor pickers. No real names.
        $rows = [
            ['Adriana', 'Ramírez', 'Soto'],
            ['Bruno', 'Cortés', 'Vega'],
            ['Carolina', 'Fuentes', 'Lara'],
            ['Daniel', 'Mendoza', 'Ríos'],
            ['Elena', 'Navarro', 'Cano'],
            ['Fernando', 'Salazar', 'Peña'],
            ['Gabriela', 'Ibarra', 'Mora'],
            ['Héctor', 'Pacheco', 'Luna'],
            ['Irene', 'Quintero', 'Solís'],
            ['Javier', 'Robles', 'Téllez'],
        ];

        foreach ($rows as $index => [$first, $last, $mother]) {
            $email = sprintf('profesor%02d@uniges.demo', $index + 1);
            Professor::firstOrCreate(
                ['email' => $email],
                ['first_name' => $first, 'last_name' => $last, 'mother_last_name' => $mother],
            );
        }
    }

    /**
     * @return array<string, GraduationType>
     */
    private function seedGraduationTypes(): array
    {
        // Seven fictional graduation modalities (SPEC §6.3.9).
        $rows = [
            'GT-TESIS' => ['Tesis', true],
            'GT-TESINA' => ['Tesina', true],
            'GT-PROM' => ['Promedio General', false],
            'GT-EGEL' => ['Examen General de Egreso (EGEL)', false],
            'GT-MEMORIA' => ['Memoria de Experiencia Profesional', true],
            'GT-PROY' => ['Proyecto de Investigación', true],
            'GT-CURSO' => ['Curso Especial de Titulación', false],
        ];

        $types = [];
        foreach ($rows as $code => [$name, $requiresAdvisor]) {
            $types[$code] = GraduationType::firstOrCreate(
                ['code' => $code],
                ['name' => $name, 'requires_advisor' => $requiresAdvisor],
            );
        }

        return $types;
    }

    /**
     * @return array<int, RequiredDocument>
     */
    private function seedRequiredDocuments(): array
    {
        $rows = [
            'Acta de Nacimiento',
            'Certificado de Estudios',
            'Comprobante de Pago',
            'Carta de Liberación',
            'Constancia de No Adeudo',
            'Fotografías Tamaño Título',
            'Anexo III Firmado',
        ];

        $documents = [];
        foreach ($rows as $name) {
            $documents[] = RequiredDocument::firstOrCreate(
                ['name' => $name],
                [
                    'description' => null,
                    'allowed_mimes' => 'application/pdf,image/jpeg,image/png',
                    'max_size_kb' => 10240,
                ],
            );
        }

        return $documents;
    }

    /**
     * Attach every required document to every graduation type (a simple, complete
     * baseline so any demo student in the document stage gets a full slot list).
     * syncWithoutDetaching keeps this idempotent.
     *
     * @param  array<string, GraduationType>  $graduationTypes
     * @param  array<int, RequiredDocument>  $documents
     */
    private function attachRequiredDocuments(array $graduationTypes, array $documents): void
    {
        $documentIds = array_map(static fn (RequiredDocument $d): int => $d->id, $documents);

        foreach ($graduationTypes as $graduationType) {
            $graduationType->requiredDocuments()->syncWithoutDetaching($documentIds);
        }
    }
}
