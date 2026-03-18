# omnibus-uniges

Laravel 12 rebuild of T-Soft (Proyecto B).
Chapter 3.4–3.13 of the Biblia de Laravel.

## Domains
- Production: titulacion.carlosgardea.com
- Staging:    titulacion.carlosgardea.cloud
- VPS path:   /opt/omnibus/projects/uniges/

## Business Domains (DDD Lite)
- Identity      → Users, Roles, Auth (6 user types)
- Academic      → Careers, Departments, StudyPlans, GraduationTypes
- Graduation    → Graduates, GraduationStatus (9-state machine), Documents
- Committees    → Professors, SynodAssignments, Roles
- Reporting     → EfficiencyReports, GraduateReports
- Shared        → Email VO, ControlNumber VO

## Key Packages
- spatie/laravel-data v4                    → DTOs
- spatie/laravel-typescript-transformer     → TS generation
- laravel/reverb                            → WebSockets (real-time status updates)
- laravel/horizon                           → Queue monitoring
- barryvdh/laravel-dompdf or similar        → PDF/Word document generation
- maatwebsite/excel                         → Excel bulk import

## Database
PostgreSQL 18 via global_data_private Docker network.
DB name: uniges

## Legacy reference
Schema and business rules: ~/coding/omnibus-legacy/tsoft/
Blueprints: ANALISIS-LEGACY-MIGRACION-UNIGES.md + MASTER-BLUEPRINT-UNIGES.md
Note: blueprints say Laravel 11 + PostgreSQL 16 — ignore, we use Laravel 12 + PostgreSQL 18

## Critical: 9-state graduation workflow
PENDING → DOCUMENTS_SUBMITTED → FORMAT_B_REVIEW → FORMAT_B_APPROVED
→ SYNOD_ASSIGNED → CEREMONY_SCHEDULED → CEREMONY_COMPLETED
→ DOCUMENTS_VALIDATED → GRADUATED

Transitions are strict — never allow skipping states.
Use GraduationStatus enum with canTransitionTo() method.

## Artisan workflow
```bash
php artisan migrate:fresh --seed
php artisan typescript:transform
composer test
composer analyse
```
