<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Academic\Models\Professor;
use App\Domain\Graduation\Models\Student;
use App\Domain\Graduation\Models\StudentDocument;
use App\Domain\Identity\Data\DemoLoginData;
use App\Domain\Identity\Enums\DemoPreset;
use App\Domain\Identity\ValueObjects\DemoSessionResult;
use App\Domain\Jury\Models\JuryAssignment;
use App\Models\User;
use App\Support\DemoContext;
use App\Support\DemoScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Provision one ephemeral demo session (SPEC §13.1, AUTH-05).
 *
 * A single business operation, wholly inside one DB::transaction:
 *
 *   1. Mint a fresh UUIDv7 session tag (chronologically sortable, Law §VO).
 *   2. Create a demo User carrying the preset's role and the tag, with a
 *      deterministic, obviously-fake email and a fixed fictional display name
 *      (NO faker PII — the showcase stays deterministic and privacy-safe).
 *   3. For the four student presets: create a demo Student over the shared,
 *      read-only seeded baseline (demoBaseline) at the preset's pipeline stage,
 *      stamped with the preset control number and the session tag — then seed
 *      and tag the child rows that stage implies (pending documents for the
 *      document stage; a jury for the jury-assigned stage) so the demo screen has
 *      content AND every child row is isolated + purgeable by the same tag.
 *   4. Return a DemoSessionResult VO. This Action never logs the user in or
 *      writes the session — it stays free of Illuminate\Http, exactly as
 *      AuthenticateUserAction returns a User and lets the HTTP layer do the rest.
 *
 * Each call mints a distinct UUIDv7, so concurrent demo visitors get disjoint,
 * mutually invisible sandboxes (enforced by the symmetric DemoScope).
 */
final class ProvisionDemoSessionAction
{
    public function __construct(
        private readonly DemoContext $demoContext,
    ) {}

    public function handle(DemoLoginData $data): DemoSessionResult
    {
        $preset = $data->preset;

        $result = DB::transaction(function () use ($preset): DemoSessionResult {
            $demoSessionId = (string) Str::uuid7();

            /** @var User $user */
            $user = User::factory()
                ->demo($demoSessionId)
                ->state(['role' => $preset->role(), 'name' => $preset->displayName()])
                ->create();

            if ($preset->isStudentPreset()) {
                $this->provisionStudent($preset, $user, $demoSessionId);
            }

            return new DemoSessionResult($user, $demoSessionId, $preset);
        });

        // Activate the freshly minted sandbox for the remainder of this request:
        // every subsequent DemoScope'd read now sees ONLY this session's tagged
        // rows (the visitor is already "inside" their sandbox after provisioning).
        // The DemoSessionMiddleware re-establishes the same tag on later requests.
        $this->demoContext->set($result->demoSessionId);

        return $result;
    }

    /**
     * Create the demo student for a student preset plus the child rows its
     * pipeline stage implies, all carrying the session tag.
     */
    private function provisionStudent(DemoPreset $preset, User $user, string $demoSessionId): void
    {
        $factory = Student::factory()->demoBaseline()->demo($demoSessionId);

        $state = $preset->studentFactoryState();
        if ($state !== null) {
            $factory = $factory->{$state}();
        }

        /** @var Student $student */
        $student = $factory->create([
            'user_id' => $user->id,
            'control_number' => $preset->controlNumber(),
            'demo_session_id' => $demoSessionId,
            'status' => $preset->status(),
        ]);

        // Any child rows a state may already have seeded inherit the same tag
        // (defensive: today's states seed none, but keeps the invariant exact).
        $student->documents()
            ->withoutGlobalScope(DemoScope::class)
            ->whereNull('demo_session_id')
            ->update(['demo_session_id' => $demoSessionId]);

        $this->seedStageChildren($preset, $student, $demoSessionId);
    }

    /**
     * Seed the demo-stage content (documents / jury) for the richer presets so
     * the showcase has something to show, each row tagged for isolation + cleanup.
     * Both reference the shared read-only baseline rather than minting new catalogs.
     */
    private function seedStageChildren(DemoPreset $preset, Student $student, string $demoSessionId): void
    {
        if ($preset === DemoPreset::Sustentante3) {
            // Document stage: a pending StudentDocument per required document of
            // the student's graduation type, mirroring BeginDocumentStageAction.
            foreach ($student->graduationType->requiredDocuments as $requiredDocument) {
                StudentDocument::factory()
                    ->pending()
                    ->create([
                        'student_id' => $student->id,
                        'required_document_id' => $requiredDocument->id,
                        'demo_session_id' => $demoSessionId,
                    ]);
            }

            return;
        }

        if ($preset === DemoPreset::Sustentante4) {
            // Jury-assigned stage: seat a tagged jury over EXISTING baseline
            // professors (distinct seeded rows). Built directly (not via the
            // factory, whose definition eagerly mints four NEW professors) so the
            // shared professor catalog is never polluted per demo login.
            $professors = Professor::query()
                ->inRandomOrder()
                ->take(4)
                ->get()
                ->all();

            if (count($professors) < 4) {
                // No (full) baseline seeded (isolated test) — let the factory
                // mint a self-contained jury for the demo student.
                JuryAssignment::factory()->create([
                    'student_id' => $student->id,
                    'demo_session_id' => $demoSessionId,
                ]);

                return;
            }

            [$president, $secretary, $vocal, $substitute] = $professors;

            JuryAssignment::query()->create([
                'student_id' => $student->id,
                'demo_session_id' => $demoSessionId,
                'president_professor_id' => $president->id,
                'secretary_professor_id' => $secretary->id,
                'vocal_professor_id' => $vocal->id,
                'substitute_professor_id' => $substitute->id,
            ]);
        }
    }
}
