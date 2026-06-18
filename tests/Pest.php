<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class)->in('Feature');

/*
 * Unit tests cover pure Domain logic (Value Objects, Enums, the state
 * machine) in isolation — no framework bootstrap, no database.
 *
 * Feature tests boot the app and run against PostgreSQL 18 (the prod
 * engine) via RefreshDatabase — never SQLite (testing-sdd §2).
 */
