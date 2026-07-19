<?php

namespace Tests;

use Database\Seeders\TestBaselineSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Seeded once per PHPUnit process, immediately after RefreshDatabase performs its
     * single `migrate:fresh`. Every test then starts from the same reference taxonomy and
     * governed demonstration catalogue, inside its own transaction that is rolled back
     * afterwards, so nothing a test changes reaches the next one.
     *
     * Tests needing demo accounts or demo assessments still seed those explicitly.
     *
     * @var class-string<Seeder>
     */
    protected $seeder = TestBaselineSeeder::class;
}
