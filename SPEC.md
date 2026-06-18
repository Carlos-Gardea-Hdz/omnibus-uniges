# SPEC.md — UNIGES (Gestión de Titulación Universitaria)

**Version:** 1.0.0
**Created:** 2026-03-24
**Status:** Active
**Stack:** Laravel 12 + PostgreSQL 18 + React 19 + Inertia.js 2.0 + Tailwind CSS v4 (Oxide) + Laravel Reverb
**Repository:** omnibus-uniges
**Domain:** titulacion.carlosgardea.com

> **Precedence:** This document is the Single Source of Truth for UNIGES.
> It supersedes MASTER-BLUEPRINT-UNIGES.md (in omnibus-legacy/uniges/) when conflicts exist.
> Code must conform to this spec — not the other way around.

---

## Changelog

| Version | Date | Description |
|---------|------|-------------|
| 1.0.0 | 2026-03-24 | Initial SPEC — consolidates MASTER-BLUEPRINT + RESUMEN_PROYECTO + Biblia de Laravel chapters 2.x + 3.4–3.13 |

---

## Table of Contents

1. [Project Identity](#1-project-identity)
2. [Vision & Business Objectives](#2-vision--business-objectives)
3. [Functional Requirements](#3-functional-requirements)
4. [Non-Functional Requirements](#4-non-functional-requirements)
5. [System Architecture](#5-system-architecture)
6. [Data Model](#6-data-model)
7. [Route Design](#7-route-design)
8. [Frontend Architecture](#8-frontend-architecture)
9. [Integration Points](#9-integration-points)
10. [Security Protocol](#10-security-protocol)
11. [Testing Strategy](#11-testing-strategy)
12. [Migration Strategy (ETL)](#12-migration-strategy-etl)
13. [Demo Mode Specification](#13-demo-mode-specification)
14. [Deployment Architecture](#14-deployment-architecture)
15. [Phased Delivery Plan](#15-phased-delivery-plan)
16. [Appendices](#16-appendices)

---

## 1. Project Identity

### 1.1 Naming

| Attribute | Value |
|-----------|-------|
| **Commercial Name** | Gestión de Titulación Universitaria |
| **Short Name** | UNIGES |
| **Internal Code** | `uniges` |
| **Portfolio ID** | `gestion-titulacion` |
| **Portfolio Letter** | B |
| **Production Domain** | `titulacion.carlosgardea.com` |
| **Staging Domain** | `titulacion.carlosgardea.cloud` |
| **Legacy Whitelabel Domain** | `uniges-legacy.carlosgardea.com` |
| **GitHub Repository** | `omnibus-uniges` |

### 1.2 Portfolio Position

Version order (non-negotiable narrative arc):

1. **Legacy** — Original client system (available: true, external domain)
2. **Whitelabel** — Neutral branding at `uniges-legacy.carlosgardea.com` (available: false until deployed)
3. **Laravel** — Full Laravel 12 rebuild at `titulacion.carlosgardea.com` (available: false until deployed)

### 1.3 Portfolio Description

**ES:** "Sistema de gestión de titulación universitaria con máquina de estados de 9 pasos, WebSockets en tiempo real con Reverb, generación de documentos oficiales, Audit Trail y analytics avanzados. Reconstrucción Laravel 12 de un sistema en producción real."

**EN:** "University graduation management system with 9-step state machine, real-time WebSockets via Reverb, official document generation, Audit Trail and advanced analytics. Laravel 12 rebuild of a real production system."

### 1.4 Branding

#### Color Palette (Institutional Green)

| Role | Hex | Usage |
|------|-----|-------|
| **Primary** | `#69966B` | Buttons, links, accents |
| **Primary Light** | `#CFFFD0` | Headers, soft backgrounds |
| **Primary Dark** | `#4C734C` | Hover/active states |
| **Header Alt** | `#6F9E71` | Alternative headers |
| **Secondary** | `#5A705A` | Secondary UI elements |
| **Danger** | `#DC2626` | Alerts, destructive buttons |
| **Success** | `#16A34A` | Success states |
| **Warning** | `#D97706` | Warning states |
| **Neutral Dark** | `#1F2937` | Primary text (light mode) |
| **Light BG** | `#F9FAFB` | Backgrounds (light mode) |
| **Dark BG** | `#0F172A` | Background (dark mode) |
| **Dark Surface** | `#1E293B` | Cards/containers (dark mode) |
| **Dark Border** | `#334155` | Borders (dark mode) |
| **Dark Text** | `#F1F5F9` | Primary text (dark mode) |

#### Typography

| Use | Font | Fallback |
|-----|------|----------|
| Headings | Inter | system-ui, sans-serif |
| Body | Inter | system-ui, sans-serif |
| Official Documents | Times New Roman | Georgia, serif |
| Monospace | JetBrains Mono | monospace |

### 1.5 Tailwind v4 Theme (CSS-First)

**File:** `resources/css/app.css`

```css
@import "tailwindcss";

@source "../js/**/*.tsx";

@theme {
  /* Institutional Greens */
  --color-primary: #69966B;
  --color-primary-light: #CFFFD0;
  --color-primary-dark: #4C734C;
  --color-primary-header: #6F9E71;
  --color-secondary: #5A705A;

  /* Status Colors */
  --color-danger: #DC2626;
  --color-success: #16A34A;
  --color-warning: #D97706;

  /* Typography */
  --font-sans: "Inter", system-ui, sans-serif;
  --font-serif: "Times New Roman", Georgia, serif;
  --font-mono: "JetBrains Mono", monospace;

  /* Custom spacing */
  --spacing-18: 4.5rem;

  /* Animations */
  --animate-fade-in: fade-in 0.3s ease-in;
  --animate-pulse-status: pulse-status 2s ease-in-out infinite;
}

@keyframes fade-in {
  from { opacity: 0; transform: translateY(-4px); }
  to { opacity: 1; transform: translateY(0); }
}

@keyframes pulse-status {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.7; }
}

@layer utilities {
  .text-gradient-primary {
    @apply bg-gradient-to-r from-primary to-primary-dark bg-clip-text text-transparent;
  }
}
```

**IMPORTANT:** No `tailwind.config.js` file. Tailwind v4 uses the Oxide engine with CSS-first configuration exclusively.

### 1.6 Assets

| Asset | Format | Dimensions | Path |
|-------|--------|------------|------|
| Logo Principal | SVG | Vectorial | `resources/images/logo.svg` |
| Logo White | SVG | Vectorial | `resources/images/logo-white.svg` |
| Logo Fallback | PNG | 400x100px | `resources/images/logo.png` |
| Favicon | ICO+PNG | 32x32, 16x16 | `public/favicon.ico` |
| App Icon | PNG | 180x180, 512x512 | `public/apple-touch-icon.png` |
| Letterhead Header | PNG | Variable | `storage/app/letterheads/header.png` |
| Letterhead Footer | PNG | Variable | `storage/app/letterheads/footer.png` |
| Authorized Signature | JPG | Variable | `storage/app/letterheads/signature.jpg` |

**CRITICAL:** Letterhead images are used by PhpWord for Annex I & II generation and by DomPDF for judge certificates. They must be uploaded via the admin Settings panel (not hardcoded paths).

---

## 2. Vision & Business Objectives

### 2.1 Problem Statement

The legacy system is a vanilla PHP application (~9,220 LOC, 205 files, 22 tables) with:
- No framework, no ORM, no routing layer
- Zero test coverage
- Critical security vulnerabilities (no CSRF, PII in plaintext, hardcoded institutional domains, permanent document tokens, passwords emailed in cleartext)
- Procedural architecture with 600+ LOC functions
- Hardcoded timezone across 60+ files
- No audit trail (no record of who changed what)

### 2.2 Solution

A greenfield Laravel 12 rebuild demonstrating enterprise-grade architecture:
- 9-state graduation workflow powered by a state machine pattern
- Real-time progress updates via Laravel Reverb (WebSockets)
- Official document generation (Annexes I/II in Word, certificates in PDF, reports in Excel)
- Full audit trail with diff viewer UI
- Advanced analytics with bottleneck analysis and alert system
- 6-role RBAC with strict authorization gates
- Dark/light mode, bilingual UI (ES/EN)
- Comprehensive test coverage with Pest PHP

### 2.3 Target Audience

| Audience | What they evaluate |
|----------|--------------------|
| **Recruiters** | State machine complexity, real-time architecture, code quality |
| **Developers** | DDD implementation, Reverb integration, document generation patterns |
| **Potential clients** | Working demo, complete graduation pipeline, professional UX |

### 2.4 Success Metrics

| Metric | Target |
|--------|--------|
| Lighthouse Performance | >= 90 |
| Lighthouse Accessibility | >= 90 |
| TTFB (cached) | < 200ms |
| WebSocket event delivery | < 300ms |
| Pest coverage on Actions | >= 80% |
| Pest coverage overall | >= 70% |
| Vitest coverage on Components | >= 60% |
| Larastan level | 9 (max) |
| OWASP Top 10 findings (critical/high) | 0 |

---

## 3. Functional Requirements

### 3.1 Identity Domain

| ID | User Story | Acceptance Criteria | Business Rules |
|----|------------|---------------------|----------------|
| AUTH-01 | Login with email + password | Throttled: 5 attempts → progressive block | Block duration: `60s × block_count` (max 15 min). Same error for not-found + wrong-password. `session_regenerate_id()` on success |
| AUTH-02 | Admin register individual user | Email validation, allowed domains, generated password | Allowed domains configurable (gmail, hotmail, outlook, yahoo + institutional). Send activation token link — NEVER send password in plaintext |
| AUTH-03 | Admin import users from Excel | File processing from School Services | Max 100 emails/day. Log duplicates. Validate: names (letters/spaces), email (format + domain), GPA (70-100). Period auto-detected: Jan-Jun / Aug-Dec |
| AUTH-04 | Password recovery | Email token with expiration | Token expires in 60 minutes |
| AUTH-05 | Demo login with role selection | 30-min TTL, IP rate limit (10/hour), session flag `is_demo=true` | 6 presets: 4 students at pipeline stages + staff + admin |
| AUTH-06 | Logout | Session destroy, cookie clear, redirect to login | Demo sessions also cleared |
| ROLE-01 | 6-role RBAC | Differentiated permissions per role | student, admin, super_admin, secretary, assistant_secretary, school_services |

### 3.2 Academic Domain

| ID | User Story | Acceptance Criteria | Business Rules |
|----|------------|---------------------|----------------|
| CAT-01 | CRUD programs | Name, department FK, program chief FK (→professor), program type enum | Cannot delete with enrolled students (HTTP 422) |
| CAT-02 | CRUD departments | Name, department chief FK (→professor), email | Cannot delete with programs attached (HTTP 422) |
| CAT-03 | CRUD graduation types | Name, description, N:M with required documents, N:M with study plans | Types 7,8,10,11,12 require advisor + project |
| CAT-04 | CRUD study plans | Name, year | Cannot delete with active students |
| CAT-05 | CRUD professors | Name, professional ID (cédula), academic degree | Cannot delete if assigned as advisor or jury member |

### 3.3 Graduation Domain — 9-State Machine

**This is the heart of the system.** Every student progresses through 9 sequential states. The state machine enforces strict transition rules with preconditions checked at both application and domain level.

#### State Diagram

```
                        ┌──────────────────────┐
                        │  1. FORM_B_PENDING    │
                        │  Student registers     │
                        │  Initial state         │
                        └──────────┬─────────────┘
                                   │ [Submit Form B]
                                   │ GPA 70-100, data complete
                                   ▼
                        ┌──────────────────────┐
               ┌────────│  2. FORM_B_REVIEW     │────────┐
               │        │  Coordinator reviews   │        │
               │        └──────────────────────┘        │
               │ [Reject]                        [Approve]│
               ▼                                         ▼
    ┌──────────────────────┐              ┌──────────────────────┐
    │  3. FORM_B_REJECTED   │              │  4. ANNEXES_PENDING   │
    │  Observations added   │              │  Annexes I/II generated│
    │  Student corrects     │──[Resubmit]──│  Sent to School Svcs  │
    └──────────────────────┘  → back to 2  └──────────┬─────────────┘
                                                      │ [Annexes Sent]
                                                      ▼
                                           ┌──────────────────────┐
                                           │  5. ANNEX_III_PENDING │
                                           │  Student uploads docs │
                                           │  Per graduation type  │
                                           └──────────┬─────────────┘
                                                      │ [ALL docs approved]
                                                      ▼
                                           ┌──────────────────────┐
                                           │  6. PAYMENT_PENDING   │
                                           │  Student pays exam fee│
                                           │  Jury can be assigned │
                                           └──────────┬─────────────┘
                                                      │ [Assign Jury]
                                                      │ formB ✓, annexIII ✓, payment ✓
                                                      ▼
                                           ┌──────────────────────┐
                                           │  7. JURY_ASSIGNED     │
                                           │  4 professors assigned│
                                           │  Roles: P/S/V/Sub    │
                                           └──────────┬─────────────┘
                                                      │ [Schedule Ceremony]
                                                      ▼
                                           ┌──────────────────────┐
                                           │  8. CEREMONY_SCHEDULED│
                                           │  Date/time/location   │
                                           │  set                  │
                                           └──────────┬─────────────┘
                                                      │ [Complete Graduation]
                                                      ▼
                                           ┌──────────────────────┐
                                           │  9. GRADUATED         │
                                           │  Diploma folio issued │
                                           │  Book + sheet assigned│
                                           │  ★ TERMINAL STATE ★   │
                                           └──────────────────────┘
```

#### State Transition Matrix

| From → To | Action | Actor | Preconditions |
|-----------|--------|-------|---------------|
| 1 → 2 | Submit Form B | Student | Complete data, GPA 70-100, control number unique |
| 2 → 3 | Reject Form B | Admin/Secretary | Observations required |
| 2 → 4 | Approve Form B | Admin/Secretary | Review complete |
| 3 → 2 | Resubmit Form B | Student | Corrections made, previous observations cleared |
| 4 → 5 | Annexes Sent | System | Annexes I/II generated and emailed to School Services |
| 5 → 6 | Documents Complete | System | **ALL** required documents for student's graduation type in "approved" status |
| 6 → 7 | Assign Jury | Admin/Secretary | formB approved ✓, annexIII complete ✓, payment verified ✓ |
| 7 → 8 | Schedule Ceremony | Admin/Secretary | Jury assigned, valid future date, weekday, business hours |
| 8 → 9 | Graduate | Admin/Secretary | Ceremony date has passed |

**Emergency transitions:**
| From → To | Action | Actor | Preconditions |
|-----------|--------|-------|---------------|
| Any (except 9) → CANCELLED | Cancel Process | Admin/SuperAdmin | Reason required. Soft-deletes student record. Stores cancellation metadata. |

#### User Stories

| ID | User Story | Acceptance Criteria | Business Rules |
|----|------------|---------------------|----------------|
| FORMB-01 | Student completes Form B | Form with personal + academic data | GPA 70-100 (DECIMAL 5,2), unique control number (8-12 digits) |
| FORMB-02 | Student submits Form B for review | State 1→2 | **CRITICAL:** Graduation types requiring advisor + project: 7, 8, 10, 11, 12 |
| FORMB-03 | Admin approves/rejects Form B | States 2→4 (approve) or 2→3 (reject) | Observations saved on rejection, student notified |
| FORMB-04 | Rejected student resubmits Form B | State 3→2, corrections made | Previous observations cleared |
| GRAD-01 | System generates Annexes I/II | Word document with letterheads | On approval (2→4), auto-generate and email to School Services |
| GRAD-02 | System marks documents complete | State 5→6 | **CRITICAL:** ALL required documents for graduation type must be "approved" |
| GRAD-03 | Admin marks as graduated | State 8→9, diploma folio generated | Folio format: `{YEAR}-{PROGRAM_CODE}-{SEQUENTIAL}`. Assign record book + sheet. Terminal state. |

**Conditional Form B Status Logic:**

```
IF student.status_value <= 3 (FORM_B_PENDING, FORM_B_REVIEW, FORM_B_REJECTED):
    → Change status to FORM_B_REVIEW (2)
    → Set form_b_submitted_at = now
    This covers: initial submission AND resubmission after rejection

ELSE IF student.status_value > 3:
    → Update data fields ONLY
    → Do NOT change status or form_b flags
    This covers: student updating profile data after Format B was already approved
```

**Team Projects:** Max 2 additional members (normalized `team_members` table). Each member has control_number, full_name, program_id, and position (1 or 2).

### 3.4 Documents Domain

| ID | User Story | Acceptance Criteria | Business Rules |
|----|------------|---------------------|----------------|
| DOC-01 | Student uploads required documents | MIME type validation, UUID token per file | Period-organized: `{PERIOD}/students/{control_number}/{type}/` |
| DOC-02 | Admin approves/rejects individual document | Status per document (pending/uploaded/approved/rejected) | Rejection reason required on reject |
| DOC-03 | System marks all documents complete | All required for graduation type = "approved" → state 5→6 | **CRITICAL:** One unapproved document blocks the entire transition |
| DOC-04 | Student views document status | Per-document status visible on student dashboard | Real-time update via Reverb when status changes |
| ANNEX-01 | System generates Annexes I/II | PhpWord .docx with letterheads (header, footer, signature) | Complex JOIN: student + user + program + department + professor + project + jury + study_plan + graduation_type |
| ANNEX-02 | System emails annexes to School Services | Email with attachment | Respects daily limit (configurable, default 100/day). Suppressed in demo mode |

**Document tokens:** Every uploaded document gets a UUID token. Access is via **signed temporary URLs** (not permanent tokens like legacy). Expiration configurable (default 24 hours).

### 3.5 Jury Domain

| ID | User Story | Acceptance Criteria | Business Rules |
|----|------------|---------------------|----------------|
| JURY-01 | Admin assigns jury to project | Select 4 professors with roles (President, Secretary, Vocal, Substitute) | **CRITICAL:** All 4 must be DIFFERENT professors — DB CHECK constraint |
| JURY-02 | System validates jury preconditions | State must be 6, formB approved, annexIII complete, payment verified | See State Machine transition 6→7 |
| JURY-03 | System notifies all parties | Email to 4 professors + student after assignment | State changes to 7 (JURY_ASSIGNED) |

**Jury Assignment Preconditions (ALL must be true):**

```
student.status = PAYMENT_PENDING (6)
AND student.form_b_approved = true
AND student.annex_iii_completed = true
AND student.payment_verified = true
```

### 3.6 Ceremony Domain

| ID | User Story | Acceptance Criteria | Business Rules |
|----|------------|---------------------|----------------|
| CERE-01 | Admin assigns ceremony date | Future date, weekday (Mon-Fri), business hours (8AM-6PM) | Only if status = 7 (JURY_ASSIGNED). Multi-assign: same date to multiple students in one transaction |
| CERE-02 | Admin marks as graduated | State 8→9, assigns diploma folio + record book + sheet | Folio: `{YEAR}-{PROGRAM_CODE}-{SEQUENTIAL}` (unique per program per year). Terminal state — no further transitions |

### 3.7 Reporting Domain

| ID | User Story | Acceptance Criteria | Business Rules |
|----|------------|---------------------|----------------|
| REPORT-01 | Generate terminal efficiency report | Excel export by academic period | Graduated / Total ratio |
| REPORT-02 | Generate graduates report | Excel export with date range filter | All graduated students with full data |
| REPORT-03 | Generate cohort report | Excel export by generational cohort | Enrollment date grouping |
| REPORT-04 | Generate custom report | Flexible filters, grouping, aggregations | ReportBuilder service with COUNT, SUM, AVG, MIN, MAX |
| CERT-01 | Generate judge certificates | Word document (.docx) per professor | By date range. 4 certificates per graduation ceremony |

### 3.8 Analytics Domain

| ID | User Story | Acceptance Criteria | Business Rules |
|----|------------|---------------------|----------------|
| ANALYTICS-01 | Terminal efficiency dashboard | Percentage + trend chart | Graduated / Total enrolled |
| ANALYTICS-02 | Status distribution | Pie chart showing students per state | Color-coded per GraduationStatus.color() |
| ANALYTICS-03 | Bottleneck analysis | Table showing where students get stuck longest | Average time spent per state |
| ANALYTICS-04 | Monthly graduates chart | Line chart, last 12 months | Grouped by `graduated_at` month |
| ANALYTICS-05 | Alert system | Early warning for at-risk students | 3 alert types: no payment after 30 days, stuck 60+ days, low terminal efficiency per program |

---

## 4. Non-Functional Requirements

### 4.1 Performance

| Metric | Target |
|--------|--------|
| TTFB (cached pages) | < 200ms |
| P95 response time | < 500ms |
| WebSocket event delivery | < 300ms (from DB commit to UI update) |
| Document generation (PhpWord) | < 5s |
| Excel bulk import (100 rows) | < 10s |
| Landing page LCP | < 2.5s |
| Landing page CLS | < 0.1 |

### 4.2 Security

- OWASP Top 10 compliance (zero critical/high findings)
- CSRF on all forms (Laravel built-in)
- Rate limiting: 60 req/min authenticated, 20 req/min unauthenticated
- Login brute-force: 5 attempts → progressive block (`60s × block_count`, max 15 min)
- Demo login: max 10 per IP per hour
- Security headers: `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Strict-Transport-Security`
- HTTPS-only (Traefik handles TLS termination)
- Document access: signed temporary URLs only (no permanent tokens)
- File upload: strict MIME validation, max size 10MB, no executable types

### 4.3 Accessibility

- WCAG 2.1 Level AA minimum
- Semantic HTML5 elements
- ARIA labels on all interactive elements
- Full keyboard navigation support
- Color contrast ratio >= 4.5:1

### 4.4 Internationalization

- Bilingual UI: Spanish (default) / English
- Language switcher persistent in session (`locale` shared prop)
- User-facing strings via React i18n context/hook
- Server-side validation messages via Laravel `lang/` files (es + en)
- Official documents generated in Spanish only (regulatory requirement)
- All code, comments, variables, functions, classes: English
- All documentation: English

### 4.5 Browser Support

- Last 2 versions: Chrome, Firefox, Safari, Edge
- Mobile responsive (Tailwind default breakpoints + 3xl at 120rem)
- Dark/light mode via class strategy on `<html>` element

---

## 5. System Architecture

### 5.1 DDD Lite Domain Map

```
app/Domain/
  Identity/          User, UserRole enum, LoginAttempt, auth actions, policies
  Academic/          Program, Department, Professor, GraduationType, StudyPlan
  Graduation/        Student (aggregate root), GraduationStatus enum, StateMachine, TeamMember
  Documents/         RequiredDocument, StudentDocument, AnnexDocument
  Jury/              JuryAssignment, JuryRole enum
  Ceremony/          Ceremony actions, diploma folio generation
  Reporting/         Report generation actions + services (Excel, Word, PDF)
  Shared/            Value Objects (ControlNumber, Email, GPA, Address), Gender enum
```

### 5.2 Architectural Rules (Non-Negotiable)

#### Rule 1: Anemic Controllers
Controllers receive a DTO, call an Action, return a response. **Maximum 15 lines.** No business logic, no validation, no database queries.

**Forbidden inside controllers:**
- Database queries (`DB::`, `Model::where(...)`, raw SQL)
- Mail / notification dispatch
- Direct model manipulation (`$model->save()`, `$model->update()`)
- Manual validation (`$request->validate()`, `Validator::make()`)
- Business logic conditionals (if/else that decides domain behavior)
- File system operations (Storage::, File::)
- Cache reads/writes
- Event dispatching (belongs in Actions)

```php
final class ApproveFormBController
{
    public function __invoke(
        Student $student,
        ApproveFormBData $data,
        ApproveFormBAction $action,
    ): RedirectResponse {
        $action->handle($student, $data);
        return redirect()->route('admin.students.show', $student)
            ->with('success', __('Form B approved.'));
    }
}
```

#### Rule 2: Spatie Laravel Data for ALL Validation (MANDATORY)

**`$request->validate()` and traditional `FormRequest` classes are STRICTLY PROHIBITED.**

All input validation, transformation, and type safety is handled exclusively through `Spatie\LaravelData\Data` DTOs:

```php
#[TypeScript]
final class SubmitFormBData extends Data
{
    public function __construct(
        #[Required, Max(12), Regex('/^\d{8,12}$/')]
        public string $control_number,

        #[Required]
        public string $program_id,

        #[Required, Between(70, 100)]
        public float $gpa,

        #[Required]
        public string $graduation_type_id,

        #[Nullable]
        public ?string $advisor_id,

        #[Nullable, Max(300)]
        public ?string $thesis_title,
    ) {}

    public static function rules(ValidationContext $context): array
    {
        return [
            'advisor_id' => [
                Rule::requiredIf(fn () => in_array(
                    $context->payload['graduation_type_id'],
                    GraduationType::REQUIRES_ADVISOR_IDS
                )),
            ],
        ];
    }
}
```

DTOs are injected directly into controllers via Laravel's DI. Validation happens automatically before the controller method executes.

**Spatie v4 Breaking Change:** Use `collect()` not `collection()` for DTO collections. The `collection()` method was removed in v4.

```php
// CORRECT (v4)
$students = SubmitFormBData::collect($items);

// WRONG — throws in v4
$students = SubmitFormBData::collection($items);
```

**`#[TypeScript]` attribute is MANDATORY** on all DTOs exposed to the frontend. Run `php artisan typescript:transform` to regenerate `resources/js/Types/generated.d.ts`.

**Nested DTO composition** — use typed properties for complex structures:

```php
#[TypeScript]
final class SubmitFormBData extends Data
{
    public function __construct(
        #[Required, Max(12)]
        public string $control_number,

        #[Required]
        public AddressData $address,

        /** @var Collection<int, TeamMemberData> */
        #[DataCollectionOf(TeamMemberData::class)]
        public Collection $team_members,
    ) {}
}
```

**Custom `rules()` for dynamic/conditional validation:**

```php
public static function rules(ValidationContext $context): array
{
    return [
        'advisor_id' => [
            Rule::requiredIf(fn () => in_array(
                $context->payload['graduation_type_id'],
                GraduationType::REQUIRES_ADVISOR_IDS
            )),
        ],
    ];
}
```

**Automatic type casting** — Spatie Data auto-casts these types in constructor properties:

| PHP Type | Auto-Cast From |
|----------|---------------|
| `CarbonImmutable` | Date/datetime strings |
| `BackedEnum` | String/int backing values |
| `UploadedFile` | Multipart file upload |
| Nested `Data` | Array/object payload |

#### Rule 3: Actions as Units of Work

One Action = one business operation. Always transactional (`DB::transaction()`) when touching multiple tables. Type-safe: `DTO in -> Entity out`.

**Naming conventions:**

| Operation | Class Name Pattern | Example |
|-----------|-------------------|---------|
| Create | `Create{Entity}Action` | `CreateProgramAction` |
| Update | `Update{Entity}Action` | `UpdateStudentAction` |
| Delete | `Delete{Entity}Action` | `DeleteProfessorAction` |
| Domain verb | `{Verb}{Entity}Action` | `ApproveFormBAction` |
| State transition | `{Verb}Action` | `AssignJuryAction` |
| Bulk | `{Verb}{Entities}Action` | `ImportUsersFromExcelAction` |

**Dependency Injection rules:**
- Inject dependencies via **constructor only** (never method injection except for `handle()` parameters)
- **No facades** inside Actions — except `DB::transaction()` and `Event::dispatch()`
- Other Actions can be injected as constructor dependencies (within same domain)
- Cross-domain communication is via **Events only** — never import an Action from another domain

**Cross-domain rule:** Models CAN be referenced across domains (Eloquent relationships require it). Actions CANNOT — they are domain-private. If Domain A needs to trigger behavior in Domain B, Domain A dispatches an Event and Domain B listens.

```php
final class ApproveFormBAction
{
    public function __construct(
        private readonly WordDocumentGenerator $generator,
    ) {}

    public function handle(Student $student, ApproveFormBData $data): Student
    {
        return DB::transaction(function () use ($student, $data): Student {
            $student->update([
                'status' => GraduationStatus::ANNEXES_PENDING,
                'form_b_approved' => true,
                'form_b_approved_at' => now(),
            ]);

            FormBApproved::dispatch($student);
            return $student->fresh();
        });
    }
}
```

#### Rule 4: Domain Events for Side Effects

Side effects (cache invalidation, email notifications, WebSocket broadcasting, document generation) are handled via domain events, never inline in Actions.

```php
// In Listener:
class SendAnnexesOnFormBApproval
{
    public function handle(FormBApproved $event): void
    {
        GenerateAnnexesJob::dispatch($event->student);
    }
}
```

### 5.3 Component Diagram

```
┌──────────────────────────────────────────────────────────────────────┐
│                          FRONTEND                                     │
│  React 19 + TypeScript 5.9 + Tailwind v4 (Oxide)                    │
│  Pages/ Components/ Layouts/                                          │
│  Laravel Echo (WebSocket client)                                      │
└────────────────────────────┬─────────────────────────────────────────┘
                             │ Inertia.js 2.0
┌────────────────────────────┴─────────────────────────────────────────┐
│                        LARAVEL 12                                     │
│  ┌──────────┐  ┌──────────────┐  ┌──────────────┐                   │
│  │Controllers│→ │   Actions    │→ │   Models     │                   │
│  │ (anemic) │  │(transactional)│  │ (Eloquent)   │                   │
│  └──────────┘  └──────┬───────┘  └──────┬───────┘                   │
│       ↑               │                  │                            │
│    DTOs (Spatie)    Events            StateMachine                    │
│                       │                  │                            │
│                       ↓                  ↓                            │
│         ┌─────────────────────┐  ┌────────────────┐                  │
│         │ Listeners / Jobs    │  │  Broadcasting  │                  │
│         │ (email, docs, audit)│  │  (Reverb)      │                  │
│         └─────────────────────┘  └───────┬────────┘                  │
└──────────────────────────────────────────┼───────────────────────────┘
                                           │
      ┌────────────┬──────────────────────┼──────────────────┐
      │            │                      │                  │
      ▼            ▼                      ▼                  ▼
┌────────────┐ ┌─────────┐    ┌────────────────┐   ┌───────────┐
│ PostgreSQL │ │ Valkey   │    │ Laravel Reverb  │   │  Storage  │
│     18     │ │  8.0     │    │  (WebSocket)    │   │ (S3/local)│
│            │ │cache/queue│   │ real-time status│   │ docs/imgs │
│ ENUMs+JSONB│ │ sessions │    └────────────────┘   └───────────┘
└────────────┘ └─────────┘
```

### 5.4 Directory Structure

```
app/
├── Domain/
│   ├── Identity/
│   │   ├── Models/User.php, LoginAttempt.php
│   │   ├── Enums/UserRole.php
│   │   ├── Data/LoginData.php, CreateUserData.php, ImportUsersData.php
│   │   ├── Actions/AuthenticateUserAction.php, CreateUserAction.php, ImportUsersFromExcelAction.php
│   │   ├── Policies/UserPolicy.php
│   │   ├── Exceptions/InvalidCredentialsException.php
│   │   └── Events/UserLoggedIn.php
│   │
│   ├── Academic/
│   │   ├── Models/Program.php, Department.php, Professor.php, GraduationType.php, StudyPlan.php
│   │   ├── Data/CreateProgramData.php, CreateDepartmentData.php, CreateProfessorData.php
│   │   ├── Actions/CreateProgramAction.php, CreateDepartmentAction.php, CreateProfessorAction.php
│   │   ├── Enums/ProgramType.php
│   │   └── Rules/CanDeleteProgramRule.php
│   │
│   ├── Graduation/
│   │   ├── Models/Student.php, TeamMember.php
│   │   ├── Enums/GraduationStatus.php, FormBStatus.php, Gender.php
│   │   ├── Data/SubmitFormBData.php, ReviewFormBData.php, AddressData.php, TeamMemberData.php
│   │   ├── Actions/SubmitFormBAction.php, ApproveFormBAction.php, RejectFormBAction.php
│   │   ├── Actions/MarkAsGraduatedAction.php, CancelProcessAction.php
│   │   ├── StateMachine/GraduationStateMachine.php
│   │   ├── ValueObjects/ControlNumber.php, GPA.php, Address.php
│   │   ├── Rules/RequiresProjectAndAdvisor.php, ValidControlNumber.php
│   │   ├── Events/StudentStatusChanged.php, FormBApproved.php, FormBRejected.php
│   │   ├── Events/StudentGraduated.php, ProcessCancelled.php
│   │   └── Exceptions/InvalidStatusTransitionException.php
│   │
│   ├── Documents/
│   │   ├── Models/RequiredDocument.php, StudentDocument.php, AnnexDocument.php
│   │   ├── Enums/DocumentStatus.php
│   │   ├── Data/UploadDocumentData.php, ReviewDocumentData.php
│   │   ├── Actions/UploadDocumentAction.php, ApproveDocumentAction.php, RejectDocumentAction.php
│   │   ├── Actions/GenerateAnnexesAction.php
│   │   ├── Events/DocumentsValidated.php, AnnexesGenerated.php
│   │   └── Services/DocumentCompletionChecker.php
│   │
│   ├── Jury/
│   │   ├── Models/JuryAssignment.php
│   │   ├── Enums/JuryRole.php
│   │   ├── Data/AssignJuryData.php
│   │   ├── Actions/AssignJuryAction.php
│   │   ├── Rules/UniqueProfessorsInJury.php, CanAssignJury.php
│   │   └── Events/JuryAssigned.php
│   │
│   ├── Ceremony/
│   │   ├── Data/ScheduleCeremonyData.php
│   │   ├── Actions/ScheduleCeremonyAction.php
│   │   └── Events/CeremonyScheduled.php
│   │
│   ├── Reporting/
│   │   ├── Actions/GenerateEfficiencyReportAction.php, GenerateGraduatesReportAction.php
│   │   ├── Actions/GenerateCohortReportAction.php, GenerateJuryCertificateAction.php
│   │   └── Services/ExcelReportGenerator.php, WordDocumentGenerator.php, PdfGenerator.php
│   │
│   └── Shared/
│       ├── ValueObjects/Email.php
│       ├── Services/EmailService.php, PasswordGenerator.php
│       └── Traits/HasUlid.php
│
├── Infrastructure/
│   ├── Broadcasting/GraduationStepCompleted.php
│   ├── Documents/WordDocumentGenerator.php, PdfDocumentGenerator.php
│   ├── Storage/DocumentStorageService.php
│   └── Notifications/StatusChangedNotification.php, DocumentsApprovedNotification.php, JuryAssignedNotification.php
│
├── Http/
│   ├── Controllers/
│   │   ├── LandingController.php
│   │   ├── Auth/LoginController.php, DemoLoginController.php, ForgotPasswordController.php
│   │   ├── Student/DashboardController.php, FormBController.php, DocumentController.php
│   │   ├── Admin/DashboardController.php, StudentController.php
│   │   ├── Admin/ReviewController.php, JuryController.php, CeremonyController.php
│   │   ├── Admin/ProgramController.php, DepartmentController.php, ProfessorController.php
│   │   ├── Admin/GraduationTypeController.php, StudyPlanController.php
│   │   ├── Admin/ReportController.php, AnalyticsController.php
│   │   ├── Admin/UserController.php, ImportController.php, SettingsController.php
│   │   ├── Admin/AuditTrailController.php
│   │   └── Api/NotificationsController.php
│   │
│   └── Middleware/
│       ├── EnsureRoleMiddleware.php
│       ├── ThrottleLoginAttemptsMiddleware.php
│       ├── DemoSessionMiddleware.php
│       └── SecurityHeadersMiddleware.php
│
resources/
├── js/
│   ├── app.tsx
│   ├── echo.ts (Laravel Echo + Reverb config)
│   ├── Components/
│   │   ├── GraduationProgress.tsx (real-time status tracker)
│   │   ├── FormBWizard.tsx
│   │   ├── DocumentUploader.tsx
│   │   ├── JuryAssignment.tsx
│   │   ├── NotificationsDropdown.tsx
│   │   ├── AuditTimeline.tsx
│   │   ├── AnalyticsCharts.tsx
│   │   ├── DataTable.tsx (with advanced filters)
│   │   └── DarkModeToggle.tsx, LanguageSwitcher.tsx
│   ├── Pages/
│   ├── Layouts/ (AuthenticatedLayout, StudentLayout, GuestLayout)
│   ├── Types/ (auto-generated from DTOs)
│   ├── Contexts/ (ThemeContext, LocaleContext)
│   ├── Hooks/
│   └── tests/
├── css/
│   └── app.css (Tailwind v4 theme)
└── locales/
    ├── es.json
    └── en.json
```

### 5.5 Value Object & Type Safety Standards

#### ControlNumber Value Object

```php
final readonly class ControlNumber
{
    public function __construct(
        public string $value,
    ) {
        if (! preg_match('/^\d{8,12}$/', $this->value)) {
            throw new InvalidArgumentException("Control number must be 8-12 digits: {$this->value}");
        }
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
```

#### GPA Value Object

```php
final readonly class GPA
{
    public function __construct(
        public float $value,
    ) {
        if ($this->value < 70.0 || $this->value > 100.0) {
            throw new InvalidArgumentException("GPA must be between 70 and 100: {$this->value}");
        }
    }

    public function isHonors(): bool
    {
        return $this->value >= 95.0;
    }

    public function format(): string
    {
        return number_format($this->value, 2);
    }
}
```

#### Email Value Object

```php
final readonly class Email
{
    public function __construct(
        public string $value,
    ) {
        $normalized = mb_strtolower(trim($this->value));
        if (! filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Invalid email: {$this->value}");
        }
    }

    public function domain(): string
    {
        return explode('@', $this->value)[1];
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
```

#### Address Value Object (Embedded — NOT a separate model)

```php
final readonly class Address
{
    public function __construct(
        public string $street,
        public string $neighborhood,
        public ?string $ext_number,
        public ?string $int_number,
        public int $postal_code,
    ) {
        if ($this->postal_code < 10000 || $this->postal_code > 99999) {
            throw new InvalidArgumentException("Postal code must be 5 digits: {$this->postal_code}");
        }
    }
}
```

#### Type Safety Rules (PHP)

| Rule | Enforcement |
|------|-------------|
| `declare(strict_types=1)` on every PHP file | Pest arch test |
| Explicit return types on **every** method | Larastan level 9 |
| `readonly` properties wherever possible | Code review + convention |
| Constructor Property Promotion always | Code review + convention |
| No `mixed` type — ever | Larastan level 9 rejects `mixed` |
| Collection generics: `Collection<int, Student>` | PHPDoc + Larastan |
| `final` classes by default in domain code | Pest arch test |
| ULIDs (UUIDv7) for all primary keys | Migration convention |

### 5.6 Service vs Action vs Job

| Concept | Purpose | State Mutation? | Execution | Example |
|---------|---------|-----------------|-----------|---------|
| **Service** | Stateless logic, calculations, external API wrappers | No (read-only / pure) | Synchronous | `DocumentCompletionChecker`, `ExcelReportGenerator` |
| **Action** | Single business operation that changes state | Yes (create/update/delete) | Synchronous | `ApproveFormBAction`, `AssignJuryAction` |
| **Job** | Deferred/async background work | May mutate | Asynchronous (Valkey queue) | `GenerateAnnexesJob`, `SendStatusNotificationJob` |

**When to use which:**
- If it **reads without writing** → Service
- If it **writes as a direct user action** → Action (wrapped in `DB::transaction()`)
- If it **can be deferred** (email, document generation, report export) → Job
- If in doubt between Action and Job → start as Action; extract to Job only when latency becomes a concern

**Infrastructure layer** (`app/Infrastructure/`): External service integrations (Reverb broadcasting, document generators, storage) live here, not inside domain code. Domain Actions depend on Infrastructure services via constructor injection.

### 5.7 State Machine Architecture

**File:** `app/Domain/Graduation/StateMachine/GraduationStateMachine.php`

The state machine is the single authority for all graduation status transitions. No code outside the state machine may directly modify `student.status`.

```php
final class GraduationStateMachine
{
    public function __construct(
        private readonly Student $student,
    ) {}

    public function canTransitionTo(GraduationStatus $newStatus): bool
    {
        if (! $this->student->status->canTransitionTo($newStatus)) {
            return false;
        }

        return match ($newStatus) {
            GraduationStatus::FORM_B_REVIEW => $this->canSubmitFormB(),
            GraduationStatus::ANNEXES_PENDING => $this->canApproveFormB(),
            GraduationStatus::PAYMENT_PENDING => $this->canCompleteDocuments(),
            GraduationStatus::JURY_ASSIGNED => $this->canAssignJury(),
            GraduationStatus::CEREMONY_SCHEDULED => $this->canScheduleCeremony(),
            GraduationStatus::GRADUATED => $this->canGraduate(),
            default => true,
        };
    }

    public function transitionTo(GraduationStatus $newStatus): void
    {
        if (! $this->canTransitionTo($newStatus)) {
            throw InvalidStatusTransitionException::from(
                $this->student->status,
                $newStatus,
            );
        }

        $oldStatus = $this->student->status;
        $this->student->status = $newStatus;
        $this->student->save();

        StudentStatusChanged::dispatch($this->student, $oldStatus, $newStatus);
    }
}
```

**Key principle:** Every status change dispatches `StudentStatusChanged`. Listeners handle side effects (email, WebSocket broadcast, audit log entry).

**workflow_metadata JSONB:** Each step stores its own metadata in a flexible JSON column:

```php
$student->update([
    'workflow_metadata' => array_merge($student->workflow_metadata ?? [], [
        'payment' => [
            'reference' => 'TIT-20250120-ABC123',
            'paid_at' => now()->toIso8601String(),
            'amount_cents' => 250_000,
        ],
    ]),
]);
```

---

## 6. Data Model

### 6.1 Foreign Key Philosophy: Restrict + SoftDeletes

**Based on "La Biblia de Laravel" Junior Trap #6: Never cascade on historical content.**

A student's graduation record spanning 5 months of work must NEVER be lost because a user account was deleted. The rule:

| Scenario | FK Strategy | Reason |
|----------|-------------|--------|
| Historical data (students, jury, documents) | `onDelete('restrict')` | Protect audit trail and graduation records |
| True child entities (team_members → student, student_documents → student) | `onDelete('cascade')` | Meaningless without parent |
| Optional references (student → advisor) | `onDelete('set null')` | Nullable FK |
| Catalog references (student → program) | `onDelete('restrict')` | Protect referential integrity |

All parent entities (`users`, `students`, `professors`, `programs`, `departments`, `graduation_types`, `study_plans`, `student_documents`) use `SoftDeletes`. Deletion must be handled at the **application layer** (inside Actions) which soft-deletes within a transaction.

### 6.2 Entity-Relationship Diagram

```
                     ┌──────────────────┐
                     │   departments    │
                     │──────────────────│
                     │ id (ULID PK)     │
                     │ name             │
              ┌──────│ chief_prof_id FK │
              │      │ email            │
              │      └────────┬─────────┘
              │               │ 1
              │               │ N (restrict)
              │      ┌────────┴─────────┐
              │      │    programs      │
              │      │──────────────────│
              │      │ id (ULID PK)     │
              │  ┌───│ department_id FK │
              │  │   │ chief_prof_id FK │───┐
              │  │   │ name, type       │   │
              │  │   └────────┬─────────┘   │
              │  │            │ 1            │
              │  │            │              │
              │  │            │ N (restrict) │
              │  │   ┌────────┴──────────┐  │
              │  │   │    professors     │◄─┘
              │  │   │──────────────────│
              └──┼──►│ id (ULID PK)     │
                 │   │ name             │
                 │   │ professional_id  │
                 │   │ academic_degree  │
                 │   └────────┬─────────┘
                 │            │ advisor_id (set null)
                 │            │ + jury FKs (restrict)
                 │            │
    ┌────────────┴────────────┴──────────────────┐
    │              users                          │
    │─────────────────────────────────────────────│
    │ id (ULID PK)                                │
    │ role (ENUM: 6 roles)                        │
    │ first_name, last_name, email, password      │
    │ is_super (singleton rule)                   │
    └─────────────────┬───────────────────────────┘
                      │ 1:1
                      │
    ┌─────────────────┴───────────────────────────┐
    │              students (AGGREGATE ROOT)        │
    │──────────────────────────────────────────────│
    │ id (ULID PK)                                  │
    │ user_id (FK → users, RESTRICT, UNIQUE)        │
    │ control_number (UNIQUE, 8-12 digits)          │
    │ program_id (FK → programs, RESTRICT)          │
    │ graduation_type_id (FK → grad_types, RESTRICT)│
    │ study_plan_id (FK → study_plans, RESTRICT)    │
    │ advisor_id (FK → professors, SET NULL)         │
    │ first_name, last_name, mother_last_name        │
    │ gender (ENUM), gpa (DECIMAL 5,2)               │
    │ status (ENUM: 9 states) ← STATE MACHINE        │
    │ workflow_metadata (JSONB + GIN index)           │
    │ form_b_* flags, annex_iii_completed             │
    │ payment_*, ceremony_*, diploma_folio            │
    │ record_book, record_sheet                       │
    │ address_* (embedded VO)                         │
    │ is_team_project                                 │
    └──┬──────────────┬──────────────┬────────────────┘
       │              │              │
       │ 1:N          │ 1:N          │ 0..1
       │ (cascade)    │ (cascade)    │
  ┌────┴─────┐  ┌─────┴──────┐  ┌───┴──────────────┐
  │team_     │  │student_    │  │jury_assignments  │
  │members   │  │documents   │  │──────────────────│
  │──────────│  │────────────│  │ id (ULID PK)     │
  │ id (ULID)│  │ id (ULID)  │  │ student_id FK    │
  │ student_ │  │ student_id │  │ president FK     │
  │  id FK   │  │ req_doc_id │  │ secretary FK     │
  │ program_ │  │ file_path  │  │ vocal FK         │
  │  id FK   │  │ file_token │  │ substitute FK    │
  │ control  │  │ status     │  │ CHECK: all diff  │
  │ full_name│  │ reviewed_by│  └──────────────────┘
  │ position │  │ rejection_ │
  │ (1 or 2) │  │  reason    │
  └──────────┘  └────────────┘

  ┌──────────────────┐   ┌──────────────────┐
  │ annex_documents  │   │status_transitions│
  │──────────────────│   │──────────────────│
  │ id (ULID PK)     │   │ id (ULID PK)     │
  │ student_id FK    │   │ student_id FK    │
  │ file_path        │   │ from_status      │
  │ file_token       │   │ to_status        │
  │ generated_at     │   │ performed_by FK  │
  └──────────────────┘   │ metadata JSONB   │
                         │ transitioned_at  │
  ┌──────────────────┐   └──────────────────┘
  │  audit_events    │
  │──────────────────│   ┌──────────────────┐
  │ id (UUID PK)     │   │  notifications   │
  │ event            │   │──────────────────│
  │ auditable_type   │   │ id (UUID PK)     │
  │ auditable_id     │   │ type             │
  │ user_id FK       │   │ notifiable_type  │
  │ old_values JSONB │   │ notifiable_id    │
  │ new_values JSONB │   │ data JSONB       │
  │ ip_address       │   │ read_at          │
  │ user_agent       │   └──────────────────┘
  └──────────────────┘

  CATALOGS & CONFIG:
  ┌──────────────────┐  ┌──────────────────┐  ┌──────────────────┐
  │graduation_types  │  │  study_plans     │  │required_documents│
  │──────────────────│  │──────────────────│  │──────────────────│
  │ id (ULID PK)     │  │ id (ULID PK)     │  │ id (ULID PK)     │
  │ name             │  │ name             │  │ name             │
  │ description      │  │ year             │  │ description      │
  └──────┬───────────┘  └──────┬───────────┘  │ allowed_mimes    │
         │ N:M                 │ N:M           └──────┬───────────┘
         └────────┬────────────┘                      │ N:M
    graduation_type_study_plan              graduation_type_required_document

  SINGLETONS:
  ┌──────────────────┐  ┌──────────────────┐  ┌──────────────────┐
  │ system_settings  │  │   letterheads    │  │  email_quotas    │
  │ (PK=1 always)    │  │ (PK=1 always)    │  │──────────────────│
  │ institution_name │  │ header_path      │  │ id (ULID PK)     │
  │ email, phone     │  │ footer_path      │  │ date             │
  │ exam_cost_cents  │  │ signature_path   │  │ count            │
  └──────────────────┘  └──────────────────┘  └──────────────────┘
```

### 6.3 Table Specifications

#### 6.3.1 `users`

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | `CHAR(26)` ULID | PRIMARY KEY | |
| `first_name` | `VARCHAR(100)` | NOT NULL | |
| `last_name` | `VARCHAR(100)` | NOT NULL | |
| `email` | `VARCHAR(255)` | NOT NULL, UNIQUE | Login credential |
| `password` | `VARCHAR(255)` | NOT NULL | bcrypt |
| `role` | `user_role` ENUM | NOT NULL, DEFAULT 'student' | 6 roles |
| `is_super` | `BOOLEAN` | DEFAULT false | Singleton rule: only one true |
| `email_verified_at` | `TIMESTAMP` | NULLABLE | |
| `remember_token` | `VARCHAR(100)` | NULLABLE | |
| `last_login_at` | `TIMESTAMP` | NULLABLE | |
| `last_login_ip` | `VARCHAR(45)` | NULLABLE | IPv4/IPv6 |
| `created_at` | `TIMESTAMP` | | |
| `updated_at` | `TIMESTAMP` | | |
| `deleted_at` | `TIMESTAMP` | NULLABLE | SoftDeletes |

**Indexes:** `email` (unique), `role`, `[role, is_super]`

#### 6.3.2 `login_attempts`

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | `CHAR(26)` ULID | PRIMARY KEY | |
| `email` | `VARCHAR(255)` | NOT NULL, UNIQUE | |
| `failed_attempts` | `INTEGER` | DEFAULT 0 | |
| `times_blocked` | `INTEGER` | DEFAULT 0 | |
| `updated_at` | `TIMESTAMP` | | Auto-updated on each attempt |

**Indexes:** `email` (unique)

#### 6.3.3 `departments`

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | `CHAR(26)` ULID | PRIMARY KEY | |
| `name` | `VARCHAR(150)` | NOT NULL | |
| `chief_professor_id` | `CHAR(26)` | FK → professors(id) `SET NULL`, NULLABLE | |
| `email` | `VARCHAR(255)` | NULLABLE | Department contact email |
| `created_at` | `TIMESTAMP` | | |
| `updated_at` | `TIMESTAMP` | | |
| `deleted_at` | `TIMESTAMP` | NULLABLE | SoftDeletes |

**Indexes:** `chief_professor_id`

#### 6.3.4 `programs`

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | `CHAR(26)` ULID | PRIMARY KEY | |
| `department_id` | `CHAR(26)` | FK → departments(id) `RESTRICT` | |
| `chief_professor_id` | `CHAR(26)` | FK → professors(id) `SET NULL`, NULLABLE | Program coordinator |
| `name` | `VARCHAR(150)` | NOT NULL | |
| `code` | `VARCHAR(10)` | NOT NULL, UNIQUE | Used in diploma folio |
| `program_type` | `program_type` ENUM | NOT NULL | bachelors, masters, doctorate |
| `created_at` | `TIMESTAMP` | | |
| `updated_at` | `TIMESTAMP` | | |
| `deleted_at` | `TIMESTAMP` | NULLABLE | SoftDeletes |

**Indexes:** `department_id`, `code` (unique), `program_type`

#### 6.3.5 `professors`

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | `CHAR(26)` ULID | PRIMARY KEY | |
| `name` | `VARCHAR(200)` | NOT NULL | Full name |
| `professional_id` | `VARCHAR(20)` | NULLABLE | Cédula profesional |
| `academic_degree` | `VARCHAR(100)` | NULLABLE | e.g., "M.C.", "Dr.", "Ing." |
| `email` | `VARCHAR(255)` | NULLABLE | |
| `created_at` | `TIMESTAMP` | | |
| `updated_at` | `TIMESTAMP` | | |
| `deleted_at` | `TIMESTAMP` | NULLABLE | SoftDeletes |

**Indexes:** `name`, `professional_id`

#### 6.3.6 `graduation_types`

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | `CHAR(26)` ULID | PRIMARY KEY | |
| `legacy_id` | `INTEGER` | NULLABLE, UNIQUE | Maps to legacy IDs 3-17 |
| `name` | `VARCHAR(150)` | NOT NULL | |
| `description` | `TEXT` | NULLABLE | |
| `requires_advisor` | `BOOLEAN` | DEFAULT false | Types 7,8,10,11,12 = true |
| `requires_project` | `BOOLEAN` | DEFAULT false | Same subset |
| `created_at` | `TIMESTAMP` | | |
| `updated_at` | `TIMESTAMP` | | |
| `deleted_at` | `TIMESTAMP` | NULLABLE | SoftDeletes |

**Indexes:** `legacy_id` (unique)

#### 6.3.7 `study_plans`

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | `CHAR(26)` ULID | PRIMARY KEY | |
| `name` | `VARCHAR(100)` | NOT NULL | |
| `year` | `INTEGER` | NOT NULL | e.g., 2004, 2006, 2010 |
| `created_at` | `TIMESTAMP` | | |
| `updated_at` | `TIMESTAMP` | | |
| `deleted_at` | `TIMESTAMP` | NULLABLE | SoftDeletes |

**Indexes:** `year`

#### 6.3.8 `graduation_type_study_plan` (pivot)

| Column | Type | Constraints |
|--------|------|-------------|
| `graduation_type_id` | `CHAR(26)` | FK → graduation_types(id) `CASCADE` |
| `study_plan_id` | `CHAR(26)` | FK → study_plans(id) `CASCADE` |

**Indexes:** `[graduation_type_id, study_plan_id]` (unique composite PK)

#### 6.3.9 `required_documents`

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | `CHAR(26)` ULID | PRIMARY KEY | |
| `name` | `VARCHAR(150)` | NOT NULL | |
| `description` | `TEXT` | NULLABLE | |
| `allowed_mimes` | `VARCHAR(255)` | NOT NULL | e.g., "application/pdf,image/jpeg,image/png" |
| `max_size_kb` | `INTEGER` | NOT NULL, DEFAULT 10240 | Default 10MB |
| `created_at` | `TIMESTAMP` | | |
| `updated_at` | `TIMESTAMP` | | |

**Indexes:** none (small catalog table)

#### 6.3.10 `graduation_type_required_document` (pivot)

| Column | Type | Constraints |
|--------|------|-------------|
| `graduation_type_id` | `CHAR(26)` | FK → graduation_types(id) `CASCADE` |
| `required_document_id` | `CHAR(26)` | FK → required_documents(id) `CASCADE` |

**Indexes:** `[graduation_type_id, required_document_id]` (unique composite PK)

#### 6.3.11 `students` (Aggregate Root — CRITICAL)

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | `CHAR(26)` ULID | PRIMARY KEY | |
| `user_id` | `CHAR(26)` | FK → users(id) **`RESTRICT`**, UNIQUE | 1:1 |
| `control_number` | `VARCHAR(12)` | NOT NULL, UNIQUE | CHECK: `^\d{8,12}$` |
| `program_id` | `CHAR(26)` | FK → programs(id) `RESTRICT` | |
| `graduation_type_id` | `CHAR(26)` | FK → graduation_types(id) `RESTRICT` | |
| `study_plan_id` | `CHAR(26)` | FK → study_plans(id) `RESTRICT` | |
| `advisor_id` | `CHAR(26)` | FK → professors(id) `SET NULL`, NULLABLE | Required for types 7,8,10,11,12 |
| `first_name` | `VARCHAR(100)` | NOT NULL | For document generation |
| `last_name` | `VARCHAR(100)` | NOT NULL | Apellido paterno |
| `mother_last_name` | `VARCHAR(100)` | NULLABLE | Apellido materno |
| `gender` | `gender` ENUM | NOT NULL | male, female, other |
| `age` | `INTEGER` | NULLABLE | |
| `phone` | `VARCHAR(15)` | NULLABLE | |
| `mobile` | `VARCHAR(15)` | NULLABLE | |
| `status` | `graduation_status` ENUM | NOT NULL, DEFAULT 'form_b_pending' | **9-STATE MACHINE** |
| `workflow_metadata` | `JSONB` | NULLABLE | Flexible per-step data |
| `gpa` | `DECIMAL(5,2)` | NOT NULL | CHECK: 70.00–100.00 |
| `enrollment_date` | `DATE` | NOT NULL | |
| `graduation_date` | `DATE` | NULLABLE | |
| `thesis_title` | `VARCHAR(300)` | NULLABLE | |
| `thesis_abstract` | `TEXT` | NULLABLE | |
| `form_b_submitted_at` | `TIMESTAMP` | NULLABLE | |
| `form_b_approved` | `BOOLEAN` | DEFAULT false | |
| `form_b_observations` | `TEXT` | NULLABLE | Rejection notes |
| `annex_iii_completed` | `BOOLEAN` | DEFAULT false | |
| `documents_completed_at` | `TIMESTAMP` | NULLABLE | |
| `payment_reference` | `VARCHAR(50)` | NULLABLE | |
| `payment_verified` | `BOOLEAN` | DEFAULT false | |
| `paid_at` | `TIMESTAMP` | NULLABLE | |
| `ceremony_date` | `TIMESTAMP` | NULLABLE | |
| `ceremony_location` | `VARCHAR(200)` | NULLABLE | |
| `diploma_folio` | `VARCHAR(50)` | NULLABLE | Format: `{YEAR}-{CODE}-{SEQ}` |
| `record_book` | `VARCHAR(20)` | NULLABLE | Libro de registro |
| `record_sheet` | `VARCHAR(20)` | NULLABLE | Foja de registro |
| `address_street` | `VARCHAR(125)` | NULLABLE | |
| `address_neighborhood` | `VARCHAR(100)` | NULLABLE | |
| `address_ext_number` | `VARCHAR(11)` | NULLABLE | |
| `address_int_number` | `VARCHAR(11)` | NULLABLE | |
| `address_postal_code` | `INTEGER` | NULLABLE | CHECK: 10000–99999 |
| `is_team_project` | `BOOLEAN` | DEFAULT false | |
| `created_at` | `TIMESTAMP` | | |
| `updated_at` | `TIMESTAMP` | | |
| `deleted_at` | `TIMESTAMP` | NULLABLE | SoftDeletes |

**Indexes:** `user_id` (unique), `control_number` (unique), `program_id`, `status`, `[program_id, status]`, `[status, ceremony_date]`, `enrollment_date`, `graduation_date`, `workflow_metadata` (GIN)

**CHECK constraints:**
```sql
ALTER TABLE students ADD CONSTRAINT check_control_number_format CHECK (control_number ~ '^\d{8,12}$');
ALTER TABLE students ADD CONSTRAINT check_gpa_range CHECK (gpa >= 70 AND gpa <= 100);
ALTER TABLE students ADD CONSTRAINT check_postal_code CHECK (address_postal_code IS NULL OR (address_postal_code >= 10000 AND address_postal_code <= 99999));
```

#### 6.3.12 `team_members`

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | `CHAR(26)` ULID | PRIMARY KEY | |
| `student_id` | `CHAR(26)` | FK → students(id) `CASCADE` | True child |
| `program_id` | `CHAR(26)` | FK → programs(id) `RESTRICT` | |
| `control_number` | `VARCHAR(12)` | NOT NULL | |
| `full_name` | `VARCHAR(200)` | NOT NULL | |
| `position` | `SMALLINT` | NOT NULL | 1 or 2 |
| `created_at` | `TIMESTAMP` | | |
| `updated_at` | `TIMESTAMP` | | |

**Indexes:** `student_id`, `[student_id, position]` (unique), `control_number`

#### 6.3.13 `student_documents`

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | `CHAR(26)` ULID | PRIMARY KEY | |
| `student_id` | `CHAR(26)` | FK → students(id) `CASCADE` | True child |
| `required_document_id` | `CHAR(26)` | FK → required_documents(id) `RESTRICT` | |
| `reviewed_by` | `CHAR(26)` | FK → users(id) `SET NULL`, NULLABLE | |
| `file_path` | `VARCHAR(255)` | NOT NULL | |
| `file_token` | `VARCHAR(255)` | NOT NULL, UNIQUE | UUID for signed URLs |
| `original_filename` | `VARCHAR(255)` | NOT NULL | |
| `mime_type` | `VARCHAR(100)` | NOT NULL | |
| `file_size` | `BIGINT UNSIGNED` | NOT NULL | Bytes |
| `status` | `document_status` ENUM | NOT NULL, DEFAULT 'uploaded' | pending, uploaded, approved, rejected |
| `rejection_reason` | `TEXT` | NULLABLE | |
| `uploaded_at` | `TIMESTAMP` | NOT NULL | |
| `reviewed_at` | `TIMESTAMP` | NULLABLE | |
| `created_at` | `TIMESTAMP` | | |
| `updated_at` | `TIMESTAMP` | | |
| `deleted_at` | `TIMESTAMP` | NULLABLE | SoftDeletes |

**Indexes:** `student_id`, `status`, `[student_id, required_document_id]` (unique), `[student_id, status]`

#### 6.3.14 `annex_documents`

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | `CHAR(26)` ULID | PRIMARY KEY | |
| `student_id` | `CHAR(26)` | FK → students(id) `CASCADE` | |
| `file_path` | `VARCHAR(255)` | NOT NULL | |
| `file_token` | `VARCHAR(255)` | NOT NULL, UNIQUE | |
| `generated_at` | `TIMESTAMP` | NOT NULL | |
| `created_at` | `TIMESTAMP` | | |
| `updated_at` | `TIMESTAMP` | | |

**Indexes:** `student_id`

#### 6.3.15 `jury_assignments`

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | `CHAR(26)` ULID | PRIMARY KEY | |
| `student_id` | `CHAR(26)` | FK → students(id) **`RESTRICT`**, UNIQUE | 1:1, historical data |
| `president_professor_id` | `CHAR(26)` | FK → professors(id) `RESTRICT` | |
| `secretary_professor_id` | `CHAR(26)` | FK → professors(id) `RESTRICT` | |
| `vocal_professor_id` | `CHAR(26)` | FK → professors(id) `RESTRICT` | |
| `substitute_professor_id` | `CHAR(26)` | FK → professors(id) `SET NULL`, NULLABLE | Optional |
| `created_at` | `TIMESTAMP` | | |
| `updated_at` | `TIMESTAMP` | | |

**Indexes:** `student_id` (unique)

**CHECK constraint (all professors must be different):**
```sql
ALTER TABLE jury_assignments ADD CONSTRAINT unique_professors_in_jury CHECK (
    president_professor_id != secretary_professor_id
    AND president_professor_id != vocal_professor_id
    AND president_professor_id != COALESCE(substitute_professor_id, '00000000000000000000000000')
    AND secretary_professor_id != vocal_professor_id
    AND secretary_professor_id != COALESCE(substitute_professor_id, '00000000000000000000000000')
    AND vocal_professor_id != COALESCE(substitute_professor_id, '00000000000000000000000000')
);
```

#### 6.3.16 `system_settings` (singleton)

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | `TINYINT` | PRIMARY KEY, DEFAULT 1 | Always 1 |
| `institution_name` | `VARCHAR(200)` | NOT NULL | |
| `contact_email` | `VARCHAR(255)` | NOT NULL | |
| `contact_phone` | `VARCHAR(15)` | NOT NULL | |
| `exam_cost_cents` | `BIGINT` | NOT NULL | Integer cents |
| `daily_email_limit` | `INTEGER` | NOT NULL, DEFAULT 100 | |
| `updated_at` | `TIMESTAMP` | | |

**CHECK constraint:** `id = 1`

#### 6.3.17 `letterheads` (singleton)

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | `TINYINT` | PRIMARY KEY, DEFAULT 1 | Always 1 |
| `header_path` | `VARCHAR(255)` | NOT NULL | |
| `footer_path` | `VARCHAR(255)` | NOT NULL | |
| `signature_path` | `VARCHAR(255)` | NOT NULL | |
| `updated_at` | `TIMESTAMP` | | |

**CHECK constraint:** `id = 1`

#### 6.3.18 `email_quotas`

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | `CHAR(26)` ULID | PRIMARY KEY | |
| `date` | `DATE` | NOT NULL, UNIQUE | One row per day |
| `count` | `INTEGER` | NOT NULL, DEFAULT 0 | |
| `created_at` | `TIMESTAMP` | | |
| `updated_at` | `TIMESTAMP` | | |

**Indexes:** `date` (unique)

#### 6.3.19 `status_transitions` (Audit Trail for State Machine)

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | `CHAR(26)` ULID | PRIMARY KEY | |
| `student_id` | `CHAR(26)` | FK → students(id) `CASCADE` | |
| `from_status` | `graduation_status` ENUM | NOT NULL | |
| `to_status` | `graduation_status` ENUM | NOT NULL | |
| `performed_by` | `CHAR(26)` | FK → users(id) `SET NULL`, NULLABLE | System transitions have NULL |
| `metadata` | `JSONB` | NULLABLE | Step-specific data (observations, jury IDs, etc.) |
| `transitioned_at` | `TIMESTAMP` | NOT NULL | |

**Indexes:** `student_id`, `[student_id, transitioned_at]`, `from_status`, `to_status`

#### 6.3.20 `audit_events`

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | `UUID` | PRIMARY KEY | Standard UUID |
| `event` | `VARCHAR(100)` | NOT NULL | e.g., "updated", "deleted", "created" |
| `auditable_type` | `VARCHAR(255)` | NOT NULL | Eloquent morph type |
| `auditable_id` | `CHAR(26)` | NOT NULL | Entity ULID |
| `user_id` | `CHAR(26)` | FK → users(id) `SET NULL`, NULLABLE | |
| `old_values` | `JSONB` | NULLABLE | Before state |
| `new_values` | `JSONB` | NULLABLE | After state |
| `ip_address` | `VARCHAR(45)` | NULLABLE | |
| `user_agent` | `VARCHAR(255)` | NULLABLE | |
| `created_at` | `TIMESTAMP` | | |

**Indexes:** `[auditable_type, auditable_id]`, `user_id`, `event`, `created_at`, `old_values` (GIN), `new_values` (GIN)

#### 6.3.21 `notifications`

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | `UUID` | PRIMARY KEY | Standard UUID |
| `type` | `VARCHAR(255)` | NOT NULL | Notification class name |
| `notifiable_type` | `VARCHAR(255)` | NOT NULL | Eloquent morph type |
| `notifiable_id` | `CHAR(26)` | NOT NULL | |
| `data` | `JSONB` | NOT NULL | Notification payload |
| `read_at` | `TIMESTAMP` | NULLABLE | |
| `created_at` | `TIMESTAMP` | | |
| `updated_at` | `TIMESTAMP` | | |

**Indexes:** `[notifiable_type, notifiable_id]`, `read_at`

### 6.4 PostgreSQL ENUM Types

```sql
CREATE TYPE graduation_status AS ENUM (
    'form_b_pending', 'form_b_review', 'form_b_rejected',
    'annexes_pending', 'annex_iii_pending', 'payment_pending',
    'jury_assigned', 'ceremony_scheduled', 'graduated'
);

CREATE TYPE user_role AS ENUM (
    'student', 'admin', 'super_admin',
    'secretary', 'assistant_secretary', 'school_services'
);

CREATE TYPE jury_role AS ENUM (
    'president', 'secretary', 'vocal', 'substitute'
);

CREATE TYPE gender AS ENUM ('male', 'female', 'other');

CREATE TYPE program_type AS ENUM ('bachelors', 'masters', 'doctorate');

CREATE TYPE form_b_status AS ENUM ('pending', 'submitted', 'approved', 'rejected');

CREATE TYPE document_status AS ENUM ('pending', 'uploaded', 'approved', 'rejected');
```

### 6.5 FK Constraint Summary

| Table | Column | References | onDelete |
|-------|--------|------------|----------|
| departments | chief_professor_id | professors(id) | SET NULL |
| programs | department_id | departments(id) | **RESTRICT** |
| programs | chief_professor_id | professors(id) | SET NULL |
| students | user_id | users(id) | **RESTRICT** |
| students | program_id | programs(id) | **RESTRICT** |
| students | graduation_type_id | graduation_types(id) | **RESTRICT** |
| students | study_plan_id | study_plans(id) | **RESTRICT** |
| students | advisor_id | professors(id) | SET NULL |
| team_members | student_id | students(id) | **CASCADE** |
| team_members | program_id | programs(id) | **RESTRICT** |
| student_documents | student_id | students(id) | **CASCADE** |
| student_documents | required_document_id | required_documents(id) | **RESTRICT** |
| student_documents | reviewed_by | users(id) | SET NULL |
| annex_documents | student_id | students(id) | **CASCADE** |
| jury_assignments | student_id | students(id) | **RESTRICT** |
| jury_assignments | president_professor_id | professors(id) | **RESTRICT** |
| jury_assignments | secretary_professor_id | professors(id) | **RESTRICT** |
| jury_assignments | vocal_professor_id | professors(id) | **RESTRICT** |
| jury_assignments | substitute_professor_id | professors(id) | SET NULL |
| status_transitions | student_id | students(id) | **CASCADE** |
| status_transitions | performed_by | users(id) | SET NULL |
| audit_events | user_id | users(id) | SET NULL |

**CASCADE used only for true child entities:** `team_members`, `student_documents`, `annex_documents`, `status_transitions` (audit log follows student). All principal entities use RESTRICT.

---

## 7. Route Design

### 7.1 Public Routes (no auth required)

| Method | URI | Controller | Description |
|--------|-----|------------|-------------|
| GET | `/` | `LandingController@index` | Landing page |
| GET | `/login` | `Auth\LoginController@create` | Login page |
| POST | `/login` | `Auth\LoginController@store` | Authenticate |
| POST | `/demo-login` | `Auth\DemoLoginController@store` | Demo session |
| POST | `/logout` | `Auth\LoginController@destroy` | Destroy session |
| GET | `/forgot-password` | `Auth\ForgotPasswordController@create` | Reset form |
| POST | `/forgot-password` | `Auth\ForgotPasswordController@store` | Send reset email |
| GET | `/reset-password/{token}` | `Auth\ResetPasswordController@create` | New password form |
| POST | `/reset-password` | `Auth\ResetPasswordController@store` | Update password |

### 7.2 Student Routes (role: student)

| Method | URI | Controller | Description |
|--------|-----|------------|-------------|
| GET | `/student/dashboard` | `Student\DashboardController@index` | Pipeline status overview |
| GET | `/student/form-b` | `Student\FormBController@create` | Form B page |
| POST | `/student/form-b` | `Student\FormBController@store` | Submit Form B |
| PUT | `/student/form-b` | `Student\FormBController@update` | Update Form B data |
| GET | `/student/documents` | `Student\DocumentController@index` | Document upload page |
| POST | `/student/documents` | `Student\DocumentController@store` | Upload document |
| GET | `/student/status` | `Student\StatusController@index` | Real-time status tracker |

### 7.3 Admin Routes — Admin/SuperAdmin/Secretary

| Method | URI | Controller | Description |
|--------|-----|------------|-------------|
| GET | `/admin/dashboard` | `Admin\DashboardController@index` | Admin dashboard with stats + charts |
| GET | `/admin/students` | `Admin\StudentController@index` | Student list with advanced filters |
| GET | `/admin/students/{student}` | `Admin\StudentController@show` | Student detail |
| POST | `/admin/students/{student}/approve-form-b` | `Admin\ReviewController@approve` | Approve Form B |
| POST | `/admin/students/{student}/reject-form-b` | `Admin\ReviewController@reject` | Reject Form B |
| POST | `/admin/students/{student}/approve-document/{doc}` | `Admin\ReviewController@approveDocument` | Approve document |
| POST | `/admin/students/{student}/reject-document/{doc}` | `Admin\ReviewController@rejectDocument` | Reject document |
| GET | `/admin/students/{student}/jury` | `Admin\JuryController@create` | Jury assignment form |
| POST | `/admin/students/{student}/jury` | `Admin\JuryController@store` | Assign jury |
| POST | `/admin/ceremony` | `Admin\CeremonyController@store` | Schedule ceremony (multi-assign) |
| POST | `/admin/students/{student}/graduate` | `Admin\CeremonyController@graduate` | Mark as graduated |
| POST | `/admin/students/{student}/cancel` | `Admin\StudentController@cancel` | Cancel process |

### 7.4 Admin — Catalogs

| Method | URI | Controller | Description |
|--------|-----|------------|-------------|
| Resource | `/admin/programs` | `Admin\ProgramController` | CRUD programs |
| Resource | `/admin/departments` | `Admin\DepartmentController` | CRUD departments |
| Resource | `/admin/professors` | `Admin\ProfessorController` | CRUD professors |
| Resource | `/admin/graduation-types` | `Admin\GraduationTypeController` | CRUD graduation types |
| Resource | `/admin/study-plans` | `Admin\StudyPlanController` | CRUD study plans |

### 7.5 Admin — Reports & Analytics

| Method | URI | Controller | Description |
|--------|-----|------------|-------------|
| GET | `/admin/reports/efficiency` | `Admin\ReportController@efficiency` | Terminal efficiency report |
| GET | `/admin/reports/graduates` | `Admin\ReportController@graduates` | Graduates report |
| GET | `/admin/reports/cohorts` | `Admin\ReportController@cohorts` | Cohort report |
| GET | `/admin/reports/certificates` | `Admin\ReportController@certificates` | Judge certificates |
| POST | `/admin/reports/generate` | `Admin\ReportController@generate` | Generate + download |
| GET | `/admin/analytics` | `Admin\AnalyticsController@index` | Analytics dashboard |

### 7.6 Admin — Tools (SuperAdmin only for some)

| Method | URI | Controller | Description |
|--------|-----|------------|-------------|
| Resource | `/admin/users` | `Admin\UserController` | User management (super_admin) |
| POST | `/admin/import` | `Admin\ImportController@store` | Excel bulk import (super_admin) |
| GET | `/admin/settings` | `Admin\SettingsController@edit` | System settings |
| PUT | `/admin/settings` | `Admin\SettingsController@update` | Update settings |
| GET | `/admin/audit-trail` | `Admin\AuditTrailController@index` | Audit log viewer |

### 7.7 API Routes

| Method | URI | Controller | Description |
|--------|-----|------------|-------------|
| GET | `/api/notifications` | `Api\NotificationsController@index` | Fetch notifications |
| POST | `/api/notifications/{id}/read` | `Api\NotificationsController@markAsRead` | Mark as read |
| POST | `/api/notifications/read-all` | `Api\NotificationsController@markAllAsRead` | Mark all read |

**Validation rule:** All POST/PUT endpoints receive a Spatie LaravelData DTO. No `$request->validate()`.

---

## 8. Frontend Architecture

### 8.1 Page Tree

```
Pages/
├── Landing.tsx
├── Auth/
│   ├── Login.tsx
│   ├── ForgotPassword.tsx
│   └── ResetPassword.tsx
├── Student/
│   ├── Dashboard.tsx (pipeline tracker with real-time updates)
│   ├── FormB.tsx (multi-step wizard)
│   ├── Documents.tsx (upload + status per document)
│   └── Status.tsx (GraduationProgress component)
├── Admin/
│   ├── Dashboard.tsx (stats cards + charts + filters + student table)
│   ├── Students/
│   │   ├── Index.tsx (DataTable with advanced filters)
│   │   └── Show.tsx (student detail + actions panel)
│   ├── Review.tsx (Form B review + document review)
│   ├── Jury.tsx (professor assignment form)
│   ├── Ceremony.tsx (date picker + multi-student selection)
│   ├── Catalogs/
│   │   ├── Programs.tsx, Departments.tsx, Professors.tsx
│   │   ├── GraduationTypes.tsx, StudyPlans.tsx
│   ├── Reports.tsx (report type selection + filters + download)
│   ├── Analytics.tsx (7 chart types)
│   ├── AuditTrail.tsx (timeline + diff viewer)
│   ├── Users.tsx, Import.tsx, Settings.tsx
└── Errors/ (404, 403, 500)
```

### 8.2 Layouts

| Layout | Usage |
|--------|-------|
| `GuestLayout` | Login, forgot password, landing |
| `StudentLayout` | Student dashboard, form B, documents |
| `AuthenticatedLayout` | All admin pages |

### 8.3 Key Components

| Component | Purpose | Real-time? |
|-----------|---------|------------|
| `GraduationProgress` | 9-step visual progress bar | Yes (Echo) |
| `FormBWizard` | Multi-step form with validation | No |
| `DocumentUploader` | Drag-drop upload with progress | No |
| `JuryAssignment` | 4-professor selector with role assignment | No |
| `CeremonyScheduler` | Date picker + multi-student selection | No |
| `DataTable` | Sortable, filterable table with pagination | No |
| `AdvancedFilters` | Collapsible filter panel (status, program, date, GPA) | No |
| `NotificationsDropdown` | Bell icon + badge + dropdown list | Yes (Echo) |
| `AuditTimeline` | Vertical timeline with diff modal | No |
| `AnalyticsCharts` | 7 chart types via Chart.js | No |
| `DarkModeToggle` | Theme switcher | No |
| `LanguageSwitcher` | ES/EN toggle | No |

### 8.4 Real-Time Architecture (Laravel Echo + Reverb)

```typescript
// resources/js/echo.ts
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;
window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT,
    wssPort: import.meta.env.VITE_REVERB_PORT,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});
```

**Private channel:** `student.{student_id}` — authorized via `routes/channels.php`

**Events broadcast:**
- `graduation.step.completed` — progress bar update + notification
- `document.status.changed` — document approval/rejection
- `notification.received` — new notification in dropdown

---

## 9. Integration Points

### 9.1 Laravel Reverb (WebSockets)

**Purpose:** Real-time graduation status updates. When an admin approves Form B, the student sees the status change instantly without page refresh.

**Event:** `GraduationStepCompleted implements ShouldBroadcast`

```php
public function broadcastOn(): array
{
    return [new PrivateChannel("student.{$this->student->id}")];
}

public function broadcastWith(): array
{
    return [
        'completed_step' => $this->completedStep->value,
        'next_step' => $this->nextStep?->value,
        'progress' => $this->calculateProgress(),
        'message' => $this->completedStep->label(),
    ];
}
```

**Latency budget:** Activity completion (100ms) + Broadcast (150ms) + WebSocket delivery (20ms) = ~270ms total.

### 9.2 Document Generation

| Document | Technology | Trigger | Output |
|----------|-----------|---------|--------|
| Annexes I & II | PhpWord 1.2+ | Form B approved (2→4) | `.docx` with letterheads |
| Judge Certificates | PhpWord 1.2+ | Admin generates batch | `.docx` per professor |
| Constancias | DomPDF 3.0+ | On demand | `.pdf` |
| Diplomas | DomPDF 3.0+ / Spatie Browsershot | On graduation | `.pdf` |
| Efficiency Report | Laravel Excel 3.1+ | Admin generates | `.xlsx` |
| Graduates Report | Laravel Excel 3.1+ | Admin generates | `.xlsx` |
| Cohort Report | Laravel Excel 3.1+ | Admin generates | `.xlsx` |

**Infrastructure service:** `app/Infrastructure/Documents/WordDocumentGenerator.php` handles PhpWord operations (letterhead injection, table population, file save). Domain Actions call this service, never PhpWord directly.

### 9.3 File Storage

```
storage/app/
├── documents/{student_ulid}/
│   ├── form_b/{document_type}_{timestamp}.pdf
│   ├── annexes/annex_i_ii_{timestamp}.docx
│   └── uploads/{required_doc_id}_{token}.{ext}
├── letterheads/
│   ├── header.png
│   ├── footer.png
│   └── signature.jpg
├── reports/{YYYY}/{MM}/
│   ├── efficiency_terminal_{date}.xlsx
│   ├── graduates_{date}.xlsx
│   └── cohort_{date}.xlsx
├── certificates/{YYYY}/
│   └── sinodalia_{professor_id}_{date}.docx
└── imports/{YYYY}/{semester}/
    └── school_services_{timestamp}.xlsx
```

### 9.4 Email (SendGrid via Laravel Mail)

- Daily limit: configurable via `system_settings.daily_email_limit` (default 100)
- Tracked in `email_quotas` table (one row per day)
- **Suppressed in demo mode** (`DemoSessionMiddleware` sets `is_demo` flag)
- Email types: welcome (with activation link, NEVER plaintext password), Form B rejection, annex delivery, jury notification, ceremony notification, graduation congratulations

### 9.5 Valkey 8.0

| Use | Driver | Key Pattern |
|-----|--------|-------------|
| Cache | `valkey` | `programs:all`, `graduation_types:all`, `study_plans:all` |
| Queue | `valkey` | Default queue: `default`, Document generation: `documents`, Email: `mail` |
| Sessions | `valkey` | Laravel session driver |

### 9.6 Docker Services

| Service | Image | Purpose |
|---------|-------|---------|
| `app` | PHP 8.5 + Laravel 12 | Application server |
| `postgres` | PostgreSQL 18 | Primary database |
| `valkey` | Valkey 8.0 | Cache + Queue + Sessions |
| `reverb` | (app container) | WebSocket server (`php artisan reverb:start`) |
| `scheduler` | (app container) | `php artisan schedule:run` |
| `queue-worker` | (app container) | `php artisan queue:work --queue=default,documents,mail` |

---

## 10. Security Protocol

### 10.1 RBAC Matrix

| Permission | Student | Admin | SuperAdmin | Secretary | Asst.Secretary | SchoolServices |
|------------|---------|-------|------------|-----------|----------------|----------------|
| View student dashboard | **Yes** | — | — | — | — | — |
| View admin dashboard | — | **Yes** | **Yes** | **Yes** | **Yes** | **Yes** |
| Complete Form B | **Yes** | — | — | — | — | — |
| Upload documents | **Yes** | — | — | — | — | — |
| Review Form B | — | **Yes** | **Yes** | — | — | — |
| Approve/reject documents | — | **Yes** | **Yes** | **Yes** | **Yes** | — |
| Assign jury | — | **Yes** | **Yes** | **Yes** | — | — |
| Schedule ceremony | — | **Yes** | **Yes** | **Yes** | — | — |
| Mark as graduated | — | **Yes** | **Yes** | **Yes** | — | — |
| Cancel process | — | **Yes** | **Yes** | — | — | — |
| Register users | — | — | **Yes** | — | — | — |
| Import Excel | — | — | **Yes** | — | — | — |
| Manage catalogs | — | **Yes** | **Yes** | — | — | — |
| Generate reports | — | **Yes** | **Yes** | **Yes** | **Yes** | **Yes** |
| Define super user | — | — | **Yes** | — | — | — |
| View audit trail | — | **Yes** | **Yes** | — | — | — |
| View analytics | — | **Yes** | **Yes** | **Yes** | — | — |
| Manage settings | — | — | **Yes** | — | — | — |

### 10.2 Middleware Stack

| Middleware | Purpose |
|-----------|---------|
| `EnsureRoleMiddleware` | Gates routes by role via `UserRole` enum |
| `ThrottleLoginAttemptsMiddleware` | Progressive block: 5 attempts → `60s × block_count` (max 15 min) |
| `DemoSessionMiddleware` | TTL check, block destructive ops, suppress email |
| `SecurityHeadersMiddleware` | X-Content-Type-Options, X-Frame-Options, Referrer-Policy, HSTS |

### 10.3 Login Throttling Algorithm

```
1. Check login_attempts for email
2. If failed_attempts >= 5:
   a. Calculate cooldown = 60 × times_blocked (max 900 seconds = 15 min)
   b. If (now - updated_at) < cooldown → reject with "Try again in N seconds"
3. On failed login:
   a. INCREMENT failed_attempts
   b. If failed_attempts >= 5: INCREMENT times_blocked
4. On successful login:
   a. RESET failed_attempts = 0 (times_blocked stays for progressive escalation)
   b. Regenerate session ID
```

### 10.4 Document Access Security

- **Legacy problem:** UUID tokens with no expiration (permanent access)
- **Solution:** Laravel signed temporary URLs via Storage
- Default expiration: 24 hours (configurable)
- URLs re-generated on each request (no caching of signed URLs)

### 10.5 Super User Singleton Rule

Only ONE `is_super = true` user exists at any time. Implemented as `AssignSuperUserAction`:

```
1. DB::transaction(function() {
2.     User::where('is_super', true)->update(['is_super' => false]);
3.     $newSuper->update(['is_super' => true]);
4. });
```

---

## 11. Testing Strategy

### 11.1 Backend: Pest PHP

**Coverage targets:**

| Scope | Target |
|-------|--------|
| `app/Domain/*/Actions/` | >= 80% |
| `app/Domain/Graduation/StateMachine/` | 100% |
| `app/Domain/*/Enums/` | 100% |
| `app/Http/Controllers/` | >= 70% |
| Overall | >= 70% |

**Test types:**
- **Unit tests:** Actions, Value Objects, State Machine transitions, Enum methods
- **Feature tests:** Controller endpoints, auth flows, demo mode, document upload
- **Database tests:** Migration correctness, FK constraints, CHECK constraints, seed integrity

### 11.2 Pest Architecture Tests (DDD Boundary Enforcement)

**File:** `tests/Architecture/DomainBoundaryTest.php`

Architecture tests run in CI and **FAIL the build** if any domain imports from another domain directly:

```php
test('Identity domain only uses Shared')
    ->expect('App\Domain\Identity')
    ->toOnlyUse([
        'App\Domain\Shared',
        'Illuminate',
        'Spatie\LaravelData',
    ]);

test('Academic domain only uses Shared')
    ->expect('App\Domain\Academic')
    ->toOnlyUse([
        'App\Domain\Shared',
        'Illuminate',
        'Spatie\LaravelData',
    ]);

test('Graduation domain only uses Shared')
    ->expect('App\Domain\Graduation')
    ->toOnlyUse([
        'App\Domain\Shared',
        'Illuminate',
        'Spatie\LaravelData',
    ]);

test('Documents domain only uses Shared')
    ->expect('App\Domain\Documents')
    ->toOnlyUse([
        'App\Domain\Shared',
        'Illuminate',
        'Spatie\LaravelData',
    ]);

test('Jury domain only uses Shared')
    ->expect('App\Domain\Jury')
    ->toOnlyUse([
        'App\Domain\Shared',
        'Illuminate',
        'Spatie\LaravelData',
    ]);

test('Ceremony domain only uses Shared')
    ->expect('App\Domain\Ceremony')
    ->toOnlyUse([
        'App\Domain\Shared',
        'Illuminate',
        'Spatie\LaravelData',
    ]);

test('Reporting domain only uses Shared')
    ->expect('App\Domain\Reporting')
    ->toOnlyUse([
        'App\Domain\Shared',
        'Illuminate',
        'Spatie\LaravelData',
        'PhpOffice',
        'Maatwebsite\Excel',
    ]);

test('controllers are anemic — no Eloquent imports')
    ->expect('App\Http\Controllers')
    ->not->toUse([
        'Illuminate\Support\Facades\DB',
        'Illuminate\Http\Request',
    ]);

test('DTOs are the only validation mechanism')
    ->expect('App\Http')
    ->not->toUse('Illuminate\Foundation\Http\FormRequest');

test('all domain classes are final')
    ->expect('App\Domain')
    ->classes()
    ->toBeFinal();

test('strict types everywhere')
    ->expect('App')
    ->toUseStrictTypes();
```

**Cross-domain communication is via Events only**, never direct imports.

### 11.3 Frontend: Vitest + React Testing Library

**Coverage targets:**

| Scope | Target |
|-------|--------|
| `resources/js/Components/` | >= 60% |
| `resources/js/Utils/` | >= 80% |

**Test config:** Vitest shares Vite config. Environment: `jsdom`. Setup file cleans up DOM after each test.

### 11.4 Key Test Scenarios

| # | Scenario | Type | Domain |
|---|----------|------|--------|
| 1 | All 8 valid state transitions succeed with correct preconditions | Unit | Graduation |
| 2 | All invalid state transitions throw InvalidStatusTransitionException | Unit | Graduation |
| 3 | Form B rejection → resubmission cycle completes (1→2→3→2→4) | Feature | Graduation |
| 4 | Jury assignment blocked when formB not approved | Unit | Jury |
| 5 | Jury assignment blocked when payment not verified | Unit | Jury |
| 6 | All 4 jury professors must be different (CHECK constraint) | Database | Jury |
| 7 | Document completion requires ALL required docs for graduation type approved | Unit | Documents |
| 8 | Team member max 2 constraint enforced | Database | Graduation |
| 9 | Super user singleton: assigning new unsets previous | Unit | Identity |
| 10 | Demo session expires after 30 minutes | Feature | Identity |
| 11 | Login throttle: 5 failures → progressive block | Feature | Identity |
| 12 | Diploma folio sequential generation per program per year | Unit | Ceremony |
| 13 | Cancel process: soft-deletes student, stores metadata | Feature | Graduation |
| 14 | Cancel blocked if student already graduated | Unit | Graduation |
| 15 | Control number format validation (8-12 digits) | Unit | Graduation |
| 16 | GPA range validation (70-100) | Unit | Graduation |
| 17 | Graduation types 7,8,10,11,12 require advisor + project | Feature | Graduation |

### 11.5 Action Test Template (Arrange-Act-Assert)

```php
it('approves Form B and transitions to ANNEXES_PENDING', function (): void {
    // Arrange
    $student = Student::factory()
        ->withStatus(GraduationStatus::FORM_B_REVIEW)
        ->create(['form_b_submitted_at' => now()->subDay()]);
    $admin = User::factory()->admin()->create();

    $data = ApproveFormBData::from([]);
    actingAs($admin);

    // Act
    $result = app(ApproveFormBAction::class)->handle($student, $data);

    // Assert
    expect($result)
        ->status->toBe(GraduationStatus::ANNEXES_PENDING)
        ->form_b_approved->toBeTrue();

    $this->assertDatabaseHas('status_transitions', [
        'student_id' => $student->id,
        'from_status' => 'form_b_review',
        'to_status' => 'annexes_pending',
    ]);
});
```

### 11.6 CI Pipeline

```bash
# Run in order — any failure stops the pipeline
composer test                       # Pest suite (includes arch tests)
composer analyse                    # Larastan level 9
composer format -- --test           # Pint (check only)
npm run test:run                    # Vitest (single run)
npm run typecheck                   # tsc --noEmit
php artisan typescript:transform    # Verify no TS drift
```

### 11.7 Static Analysis Configuration

#### PHPStan / Larastan (`phpstan.neon`)

```neon
includes:
    - vendor/larastan/larastan/extension.neon

parameters:
    paths:
        - app/
    level: 9
    checkMissingIterableValueType: true
    checkGenericClassInNonGenericObjectType: true
    reportUnmatchedIgnoredErrors: true
```

**Level 9 enforces:** No `mixed` types, explicit return types, typed collections (`Collection<int, Student>`), strict comparison operators.

#### Laravel Pint (`pint.json`)

```json
{
    "preset": "laravel",
    "rules": {
        "declare_strict_types": true,
        "final_class": true,
        "void_return": true
    }
}
```

### 11.8 Dev Dependencies

| Package | Purpose |
|---------|---------|
| `pestphp/pest` | Test framework |
| `pestphp/pest-plugin-arch` | Architecture tests |
| `larastan/larastan` | PHPStan for Laravel (level 9) |
| `laravel/pint` | Code formatter |
| `barryvdh/laravel-ide-helper` | IDE autocompletion for models, facades, meta |
| `spatie/laravel-typescript-transformer` | DTO → TypeScript type generation |

Run `php artisan ide-helper:models --nowrite` after migrations to generate `_ide_helper_models.php`.

---

## 12. Migration Strategy (ETL)

### 12.1 Legacy Connection

**File:** `config/database.php`

```php
'mysql_legacy' => [
    'driver' => 'mysql',
    'host' => env('LEGACY_DB_HOST', '127.0.0.1'),
    'port' => env('LEGACY_DB_PORT', '3306'),
    'database' => env('LEGACY_DB_DATABASE', 'uniges_legacy'),
    'username' => env('LEGACY_DB_USERNAME', 'root'),
    'password' => env('LEGACY_DB_PASSWORD', ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
    'strict' => false,
    'timezone' => '-06:00',
],
```

### 12.2 Status Mapper

```php
final class StatusMapper
{
    private const STATUS_MAP = [
        1 => GraduationStatus::FORM_B_PENDING,
        2 => GraduationStatus::FORM_B_REVIEW,
        3 => GraduationStatus::FORM_B_REJECTED,
        4 => GraduationStatus::ANNEXES_PENDING,
        5 => GraduationStatus::ANNEX_III_PENDING,
        6 => GraduationStatus::PAYMENT_PENDING,
        7 => GraduationStatus::JURY_ASSIGNED,
        8 => GraduationStatus::CEREMONY_SCHEDULED,
        9 => GraduationStatus::GRADUATED,
    ];

    public static function toNew(int $legacyStatus): GraduationStatus
    {
        return self::STATUS_MAP[$legacyStatus]
            ?? throw new InvalidArgumentException("Unknown legacy status: {$legacyStatus}");
    }
}
```

**Note:** Legacy states 3 ("Aprobado") and 4 ("Rechazado") were re-semanticized. SPEC maps legacy INT 3 → FORM_B_REJECTED and legacy INT 4 → ANNEXES_PENDING. The ETL command must verify each student's actual state and data to determine correct modern status.

### 12.3 Migration Order

```
1. departments       (no FK deps)
2. programs          (→ departments)
3. professors        (no FK deps)
4. graduation_types  (no FK deps)
5. study_plans       (no FK deps)
6. required_documents (no FK deps)
7. graduation_type_study_plan (→ grad_types, study_plans)
8. graduation_type_required_document (→ grad_types, req_docs)
9. users             (no FK deps)
10. students          (→ users, programs, grad_types, study_plans, professors)
11. team_members      (→ students, programs)
12. student_documents (→ students, req_docs)
13. annex_documents   (→ students)
14. jury_assignments  (→ students, professors ×4)
15. system_settings   (singleton)
16. letterheads       (singleton)
```

### 12.4 Field Transformation Table

| Legacy Field | Transformation | Modern Field |
|---|---|---|
| `Id_* (INT AUTO_INCREMENT)` | Generate ULID | `id (CHAR 26)` |
| `Num_Control VARCHAR(12)` | Direct + CHECK constraint | `control_number` |
| `FK_Estatus_Egresado (INT 1-9)` | `StatusMapper::toNew()` | `status (ENUM)` |
| `Formato_B_Aprobado (0/1/2)` | 0→pending, 1→approved, 2→submitted | `form_b_approved (BOOL)` |
| `Promedio (DOUBLE)` | Cast to DECIMAL(5,2) | `gpa` |
| `Fk_Sexo_Egresado (1/2/3)` | 1→male, 2→female, 3→other | `gender (ENUM)` |
| `Fk_Carrera_Egresado (INT)` | Lookup in idMap | `program_id` |
| `Fk_Asesor_Interno (INT)` | Lookup in idMap | `advisor_id` |
| `NumeroControl_Equipo_* (denorm)` | Normalize to `team_members` | `team_members` table |
| `Fk_Direccion → table` | Embed as columns | `address_*` |
| `TINYINT booleans` | Cast | `BOOLEAN` |
| `DATETIME (no tz)` | Convert to TIMESTAMPTZ | `TIMESTAMP` |
| Spanish column names | Rename | English equivalents |

### 12.5 ETL Command

```bash
php artisan migrate:legacy-students \
    --dry-run           # Preview without persisting
    --chunk=50          # Records per batch
    --status=9          # Only migrate graduated students (for testing)
```

---

## 13. Demo Mode Specification

### 13.1 Demo Presets

| Preset | User | Pipeline Stage | Status |
|--------|------|----------------|--------|
| `sustentante_1` | Demo Student 1 | Just registered | FORM_B_PENDING (1) |
| `sustentante_2` | Demo Student 2 | Form B under review | FORM_B_REVIEW (2) |
| `sustentante_3` | Demo Student 3 | Documents stage | ANNEX_III_PENDING (5) |
| `sustentante_4` | Demo Student 4 | Jury assigned | JURY_ASSIGNED (7) |
| `personal` | Demo Staff | Administrative access | assistant_secretary role |
| `admin` | Demo Admin | Full coordinator access | admin role |

### 13.2 Session Flags

```php
$_SESSION = [
    'user_id' => $demoUser->id,
    'user_role' => $demoUser->role->value,
    'is_demo' => true,
    'demo_expires_at' => now()->addMinutes(30)->timestamp,
];
```

### 13.3 DemoSessionMiddleware

- Check TTL: if `now() > demo_expires_at` → logout + redirect to login
- Block destructive operations in demo mode:
  - Cannot actually delete records
  - Cannot modify system settings
  - Cannot import Excel files
  - Cannot change super user
- Suppress email: `EmailService` checks `is_demo` flag before sending
- Tag all created data with `demo_session_id` for cleanup

### 13.4 Cleanup

```bash
php artisan demo:cleanup    # Runs every 15 minutes via scheduler
```

Purges all records tagged with `demo_session_id`. Fixed demo dataset (baseline) is never modified.

### 13.5 Rate Limiting

10 demo logins per IP per hour. Enforced via Laravel rate limiter (not file-based like legacy).

---

## 14. Deployment Architecture

### 14.1 Docker Compose

```yaml
services:
  app:
    build: .
    ports: ["8000:8000"]
    depends_on: [postgres, valkey]
    labels:
      - "traefik.enable=true"
      - "traefik.http.routers.uniges.rule=Host(`titulacion.carlosgardea.com`)"
      - "traefik.http.routers.uniges.tls.certresolver=letsencrypt"

  postgres:
    image: postgres:18
    environment:
      POSTGRES_DB: uniges
      POSTGRES_USER: uniges
    volumes: ["pgdata:/var/lib/postgresql/data"]
    networks: [global_data_private]

  valkey:
    image: valkey/valkey:8.0
    volumes: ["valkeydata:/data"]

  reverb:
    build: .
    command: php artisan reverb:start --host=0.0.0.0
    depends_on: [app]

  scheduler:
    build: .
    command: php artisan schedule:work
    depends_on: [app]

  queue-worker:
    build: .
    command: php artisan queue:work --queue=default,documents,mail --tries=3
    depends_on: [app, valkey]
```

### 14.2 VPS Deployment

| Attribute | Value |
|-----------|-------|
| **VPS Path** | `/opt/omnibus/projects/uniges/` |
| **Domain** | `titulacion.carlosgardea.com` |
| **TLS** | Traefik + Let's Encrypt |
| **Network** | `global_data_private` (shared PostgreSQL) |

### 14.3 Deploy Command

```bash
ssh vps "cd /opt/omnibus/projects/uniges && git pull && composer install --no-dev --optimize-autoloader && php artisan migrate --force && php artisan optimize && php artisan reverb:restart"
```

---

## 15. Phased Delivery Plan

### Phase 1: Foundation + Auth + Form B (Identity + Graduation core)

- Laravel 12 scaffold with DDD Lite directory structure
- PostgreSQL 18 ENUMs + migrations (users, students, programs, departments, professors)
- Authentication (login, demo login, throttling)
- 6-role RBAC middleware
- Form B submission + review cycle (states 1-4)
- GraduationStateMachine with basic transitions
- Pest arch tests for DDD boundaries
- Student dashboard (static, no real-time yet)

### Phase 2: Documents + Jury + Ceremony (complete pipeline)

- Document upload + approval workflow (states 4-6)
- Annex I/II generation (PhpWord + letterheads)
- Jury assignment with CHECK constraints (state 6→7)
- Ceremony scheduling + multi-assign (state 7→8)
- Mark as graduated with diploma folio (state 8→9)
- Complete state machine (all 9 states functional)
- Cancel process (emergency edge case)
- Email notifications (SendGrid)

### Phase 3: Reports + Analytics + Audit Trail + Notifications

- Terminal efficiency report (Excel)
- Graduates report (Excel)
- Cohort report (Excel)
- Judge certificates (Word)
- Advanced analytics dashboard (7 chart types)
- Alert system (at-risk students)
- Audit trail with diff viewer
- Notification center (DB + Mail + Broadcast)

### Phase 4: ETL + Real-Time + Demo + Deploy

- Laravel Reverb integration (WebSocket)
- GraduationProgress real-time component
- Notification dropdown with live updates
- ETL migration command (legacy MySQL → PostgreSQL)
- Demo mode with 6 presets + cleanup
- Docker Compose production config
- Traefik labels + deploy to VPS
- Excel bulk import from School Services

---

## 16. Appendices

### Appendix A: Enum Definitions

#### GraduationStatus

```php
enum GraduationStatus: string
{
    case FORM_B_PENDING = 'form_b_pending';
    case FORM_B_REVIEW = 'form_b_review';
    case FORM_B_REJECTED = 'form_b_rejected';
    case ANNEXES_PENDING = 'annexes_pending';
    case ANNEX_III_PENDING = 'annex_iii_pending';
    case PAYMENT_PENDING = 'payment_pending';
    case JURY_ASSIGNED = 'jury_assigned';
    case CEREMONY_SCHEDULED = 'ceremony_scheduled';
    case GRADUATED = 'graduated';

    public function label(): string { /* ES label */ }
    public function color(): string { /* Tailwind color class */ }
    public function stepNumber(): int { /* 1-9 */ }
    public function isTerminal(): bool { return $this === self::GRADUATED; }
    public function requiresStudentAction(): bool { /* states 1,3,5 */ }
    public function requiresAdminAction(): bool { /* states 2,4,6,7,8 */ }

    public function canTransitionTo(self $new): bool
    {
        return match ($this) {
            self::FORM_B_PENDING => $new === self::FORM_B_REVIEW,
            self::FORM_B_REVIEW => in_array($new, [self::FORM_B_REJECTED, self::ANNEXES_PENDING]),
            self::FORM_B_REJECTED => $new === self::FORM_B_REVIEW,
            self::ANNEXES_PENDING => $new === self::ANNEX_III_PENDING,
            self::ANNEX_III_PENDING => $new === self::PAYMENT_PENDING,
            self::PAYMENT_PENDING => $new === self::JURY_ASSIGNED,
            self::JURY_ASSIGNED => $new === self::CEREMONY_SCHEDULED,
            self::CEREMONY_SCHEDULED => $new === self::GRADUATED,
            self::GRADUATED => false,
        };
    }
}
```

#### UserRole

```php
enum UserRole: string
{
    case STUDENT = 'student';
    case ADMIN = 'admin';
    case SUPER_ADMIN = 'super_admin';
    case SECRETARY = 'secretary';
    case ASSISTANT_SECRETARY = 'assistant_secretary';
    case SCHOOL_SERVICES = 'school_services';

    public function label(): string { /* ... */ }
    public function color(): string { /* ... */ }
    public function isAdministrative(): bool { return $this !== self::STUDENT; }
    public function canReviewFormB(): bool { return in_array($this, [self::ADMIN, self::SUPER_ADMIN]); }
    public function canAssignJury(): bool { return in_array($this, [self::ADMIN, self::SUPER_ADMIN, self::SECRETARY]); }
}
```

#### JuryRole

```php
enum JuryRole: string
{
    case PRESIDENT = 'president';
    case SECRETARY = 'secretary';
    case VOCAL = 'vocal';
    case SUBSTITUTE = 'substitute';

    public function label(): string { /* ... */ }
}
```

#### Other Enums: `Gender`, `ProgramType`, `FormBStatus`, `DocumentStatus`

### Appendix B: Graduation Types Catalog

| Legacy ID | Name | Study Plan | Requires Advisor | Requires Project |
|-----------|------|------------|-----------------|-----------------|
| 3 | Professional Thesis | 2004-prior | Yes | Yes |
| 4 | Textbook or Prototype | 2004-prior | Yes | Yes |
| 5 | Research Project | 2004-prior | Yes | Yes |
| 6 | Equipment Design | 2004-prior | Yes | Yes |
| 7 | Special Course | 2004-prior | Yes | Yes |
| 8 | Global Exam | 2004-prior | Yes | Yes |
| 9 | Experience Memoir | 2004-prior | Yes | Yes |
| 10 | Waiver by Average (≥90) | 2004-prior | Yes | Yes |
| 11 | Waiver by Postgrad (≥80) | 2004-prior | Yes | Yes |
| 12 | EGEL (CENEVAL) | 2006-forward | Yes | Yes |
| 13 | Technical Residency Report | 2010-forward | Yes | Yes |
| 14 | Research Project | 2010-forward | Yes | Yes |
| 15 | Thesis | 2010-forward | Yes | Yes |
| 16 | Thesis Minor | 2010-forward | Yes | Yes |
| 17 | Internship Report | 2010-forward | Yes | Yes |

### Appendix C: Required Documents Catalog

| ID | Document | Common Graduation Types |
|----|----------|------------------------|
| 1 | Valid official Mexican ID | All |
| 2 | Annex III | 12-17 |
| 3 | Judge Assignment | All |
| 4 | Final work in PDF | 3-9, 13-17 |
| 5 | PowerPoint Presentation | 3-9, 13-17 |
| 6 | Residency Accreditation Letter | 13-17 |
| 7 | Residency Release Certificate | 13-17 |
| 8 | EGEL Grades Transcript | 12 |
| 9 | CENEVAL Certificate | 12 |
| 10 | Release Letter (equivalent to Annex III) | 3-11 |
| 11 | Grades Transcript (average ≥ 90) | 10 |
| 12 | Master Grades Transcript (average ≥ 80) | 11 |
| 13 | University Letter (Academic Progress ≥ 40%) | 11 |

### Appendix D: Validation Rules

| Rule | Pattern / Range | Usage |
|------|----------------|-------|
| `ControlNumber` | `/^\d{8,12}$/` | Student registration |
| `InstitutionalEmail` | Configurable domain whitelist + Levenshtein typo detection | User registration |
| `ValidGPA` | 70.00 – 100.00 | Form B submission |
| `RequiresProjectAndAdvisor` | Types 7,8,10,11,12 require both | Form B conditional |
| `CeremonyDate` | Future, weekday (Mon-Fri), business hours (8AM-6PM) | Ceremony scheduling |
| `UniqueJuryProfessors` | All 4 professor IDs must be distinct | Jury assignment |

### Appendix E: `.env.example`

```env
APP_NAME="Gestión de Titulación Universitaria"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://titulacion.carlosgardea.com
APP_TIMEZONE=America/Denver

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=uniges
DB_USERNAME=uniges
DB_PASSWORD=

LEGACY_DB_HOST=
LEGACY_DB_PORT=3306
LEGACY_DB_DATABASE=uniges_legacy
LEGACY_DB_USERNAME=
LEGACY_DB_PASSWORD=

VALKEY_HOST=valkey
VALKEY_PORT=6379

SESSION_DRIVER=valkey
CACHE_STORE=valkey
QUEUE_CONNECTION=valkey

REVERB_APP_ID=uniges
REVERB_APP_KEY=
REVERB_APP_SECRET=
REVERB_HOST=0.0.0.0
REVERB_PORT=8080
REVERB_SCHEME=https

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${APP_URL}"
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https

MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=titulacion@carlosgardea.com
MAIL_FROM_NAME="${APP_NAME}"

DAILY_EMAIL_LIMIT=100

BRAND_NAME="Gestión de Titulación Universitaria"
BRAND_PRIMARY_COLOR="#69966B"
```

### Appendix F: Seed Data Manifest

**Programs (fictional):**
- Ingeniería en Sistemas Computacionales (ISC) — Bachelors
- Ingeniería Industrial (II) — Bachelors
- Ingeniería Electrónica (IE) — Bachelors
- Maestría en Ciencias Computacionales (MCC) — Masters
- Licenciatura en Administración (LA) — Bachelors

**Departments (fictional):**
- Departamento de Sistemas y Computación
- Departamento de Ingeniería Industrial
- Departamento de Ingeniería Eléctrica y Electrónica
- Departamento de Ciencias Económico-Administrativas

**Professors (fictional, 10 minimum):**
- Dr. Alejandro Mendoza Ríos, M.C. Patricia Flores Guzmán, Ing. Roberto Torres Herrera, etc.

**Demo Students (4, at different pipeline stages):**
- Control: 20180001 (FORM_B_PENDING) — just registered
- Control: 20180002 (FORM_B_REVIEW) — submitted, awaiting review
- Control: 20180003 (ANNEX_III_PENDING) — uploading documents
- Control: 20180004 (JURY_ASSIGNED) — jury assigned, awaiting ceremony

**Categories:** 7 default graduation types seeded from Appendix B (IDs 12-17 for 2010+ plans + EGEL).

### Appendix G: Glossary (Legacy → Modern)

| Legacy Term (Spanish) | Legacy Table | Modern Term (English) | Modern Table |
|---|---|---|---|
| Egresado / Sustentante | `egresado` | Student | `students` |
| Carrera | `carrera` | Program | `programs` |
| Departamento | `departamento` | Department | `departments` |
| Profesor / Sinodal | `profesor` | Professor | `professors` |
| Proyecto | `proyecto` | Thesis Project | (embedded in `students`) |
| Producto de Titulación | `producto_titulacion` | Graduation Type | `graduation_types` |
| Plan de Estudio | `planes_estudio` | Study Plan | `study_plans` |
| Documentos Pendientes | `documentos_pendientes` | Required Document | `required_documents` |
| Egresados Documentos | `egresados_documentos` | Student Document | `student_documents` |
| Anexo I y II | `anexo_i_ii` | Annex Document | `annex_documents` |
| Asignación Sinodales | `asignacion_sinodales` | Jury Assignment | `jury_assignments` |
| Rol Sinodal | `rol_sinodal` | Jury Role | `JuryRole` enum |
| Estatus | `estatus` | Graduation Status | `GraduationStatus` enum |
| Dirección | `direccion` | Address | Embedded in `students` (VO) |
| Género | `genero` | Gender | `Gender` enum |
| Libro | `libro` | Record Book | `record_book` column on `students` |
| Formato Foja | `formato_foja` | Record Sheet | `record_sheet` column on `students` |
| Variables Globales | `variables_globales` | System Settings | `system_settings` |
| Membretes | `membretes_documentos` | Letterheads | `letterheads` |
| Correos Enviados | `correos_enviados` | Email Quotas | `email_quotas` |
| Constancias Sinodalía | `constancias_sinodalia` | Judge Certificates | Generated on demand (no table) |
| Reporte Eficiencia | `reporte_eficiencia_terminal` | Efficiency Report | Generated on demand (no table) |
| Reporte Titulados | `reporte_titulados` | Graduates Report | Generated on demand (no table) |
| Cohortes Generacionales | `cohortes_generacionales` | Cohort Report | Generated on demand (no table) |

---

### Proposed SKILL.md Files

The following skill files should be created in `~/coding/omnibus-docs/` to document advanced patterns used in this project:

1. **`SKILL-STATE-MACHINES.md`** — GraduationStateMachine pattern: enum with `canTransitionTo()`, precondition checks per state, `InvalidStatusTransitionException`, event dispatch per transition, `workflow_metadata` JSONB usage, testing state machines with Pest.

2. **`SKILL-REVERB.md`** — Laravel Reverb setup: `ShouldBroadcast` events, private channel authorization, Laravel Echo configuration for React, `GraduationStepCompleted` event pattern, latency budget calculation, frontend subscription/cleanup lifecycle.

3. **`SKILL-AUDIT-TRAIL.md`** — Audit trail implementation: `audit_events` table with JSONB diff storage, `AuditLogger` service with automatic old/new value calculation, `Auditable` trait for Eloquent models, timeline UI component, filter by user/event/entity/date, permission-gated access.

4. **`SKILL-DOCUMENT-GENERATION.md`** — Document generation stack: PhpWord for `.docx` with letterheads (header/footer/signature images), DomPDF for PDF certificates, Laravel Excel for reports with filters/grouping, signed temporary URLs for document access, `Infrastructure/Documents/` service layer pattern.
