# omnibus-uniges

Laravel 12 rebuild of the University Graduation Management System — UNIGES (Proyecto B).
Chapters 3.4–3.13 of the Biblia de Laravel.

## Single Source of Truth
**SPEC.md** is the authoritative specification. All code must conform to it.
Read SPEC.md before making any architectural decision.

## Domains
- Production: titulacion.carlosgardea.com
- Staging:    titulacion.carlosgardea.cloud
- VPS path:   /opt/omnibus/projects/uniges/

## Business Domains (DDD Lite)
- Identity      -> Users, Roles (6-level RBAC), Auth, LoginAttempts
- Academic      -> Programs, Departments, Professors, StudyPlans, GraduationTypes
- Graduation    -> Students (9-state machine), FormB, TeamMembers, StatusTransitions
- Documents     -> StudentDocuments, RequiredDocuments, AnnexDocuments
- Jury          -> JuryAssignments, JuryRoles (President, Secretary, Vocal, Substitute)
- Ceremony      -> CeremonyScheduling, DiplomaFolio, RecordBook
- Reporting     -> TerminalEfficiency, Graduates, Cohorts, JudgeCertificates
- Shared        -> ControlNumber VO, GPA VO, Email VO, Address VO, Enums

## Architectural Rules (Non-Negotiable)
1. **Anemic Controllers** — max 15 lines. Receive DTO, call Action, return response.
2. **Spatie Laravel Data ONLY** — `$request->validate()` and FormRequests are PROHIBITED.
3. **Actions** — one class = one business operation. Always transactional with `DB::transaction()`.
4. **FK restrict + SoftDeletes** — never cascade on historical content. Only `student_documents` cascade.
5. **Pest Arch Tests** — DDD boundaries enforced at CI level. Cross-domain imports fail the build.
6. **`declare(strict_types=1)`** — every PHP file, no exceptions.
7. **`final` classes** — by default in all domain code.
8. **Enums** — backed enums always, never magic strings.
9. **`readonly` properties** — wherever possible on all class properties.
10. **Constructor Property Promotion** — mandatory on all classes. No separate property declarations.
11. **Explicit return types** — on every method, no exceptions.
12. **No `mixed` type** — Larastan level 9 enforces. Use union types or generics instead.

## Critical: 9-State Graduation Workflow
```
FORM_B_PENDING (1) → FORM_B_REVIEW (2) → FORM_B_REJECTED (3) [optional loop back to 2]
                                        → ANNEXES_PENDING (4) → ANNEX_III_PENDING (5)
→ PAYMENT_PENDING (6) → JURY_ASSIGNED (7) → CEREMONY_SCHEDULED (8) → GRADUATED (9)
```
Transitions are strict — never allow skipping states.
Use `GraduationStatus` enum with `canTransitionTo()` method.
Every transition dispatches `StudentStatusChanged` event.

## Key Packages
- spatie/laravel-data v4                    -> DTOs + validation + TypeScript generation
- spatie/laravel-typescript-transformer     -> TS type generation
- laravel/reverb                            -> WebSockets (real-time graduation status)
- laravel/horizon                           -> Queue monitoring
- phpoffice/phpword ^1.2                    -> Word document generation (Annexes, Certificates)
- barryvdh/laravel-dompdf                   -> PDF generation (diplomas, constancias)
- maatwebsite/laravel-excel                 -> Excel reports + bulk user import
- mews/purifier                             -> Server-side HTML sanitization
- barryvdh/laravel-ide-helper (dev)         -> IDE autocompletion for models/facades

## Database
PostgreSQL 18 via global_data_private Docker network.
DB name: uniges

## Legacy Reference
Schema and business rules: ~/coding/omnibus-legacy/uniges/
Key docs: RESUMEN_PROYECTO.md + MASTER-BLUEPRINT-UNIGES.md
Note: blueprints say Laravel 11 + PostgreSQL 16 — we use Laravel 12 + PostgreSQL 18.
SPEC.md supersedes MASTER-BLUEPRINT when conflicts exist.

## Artisan Workflow
```bash
php artisan migrate:fresh --seed   # dev reset
php artisan typescript:transform   # regenerate TS types
composer test                      # Pest suite (includes arch tests)
composer analyse                   # Larastan level 9
composer format                    # Laravel Pint
```

## Deploy
```bash
ssh vps "cd /opt/omnibus/projects/uniges && git pull && composer install --no-dev --optimize-autoloader && php artisan migrate --force && php artisan optimize"
```

## Demo Mode Philosophy (applies to ALL OMNIBUS projects)
Every deployed project must be explorable without friction by recruiters.

Standard behavior across all projects:
- Guest/visitor mode: full feature access without registration
- Ephemeral data: session data expires after 30 min of inactivity or on session end
- Session isolation: each visitor operates in their own data sandbox
- Fixed demo dataset: seeded baseline that is never modified (read-only reference data)
- Periodic reset: a scheduled command restores demo data every 15 minutes

Implementation for this project:
- DemoSessionMiddleware -> creates isolated session context with TTL
- Scheduled command: php artisan demo:cleanup (runs every 15 min)
- Demo data tagged with demo_session_id, never touches baseline seed
- 6 demo presets: 4 students at different pipeline stages + staff + admin
- Rate limit: 10 demo logins per IP per hour
