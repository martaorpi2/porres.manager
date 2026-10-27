<?php

namespace Tests;

use App\Models\AccountingPeriod;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            ['Cobertura de pruebas 2037', '2037-01-01', '2037-12-31'],
            ['Cobertura de pruebas 2099', '2099-01-01', '2099-12-31'],
        ] as [$name, $start, $end]) {
            AccountingPeriod::query()->updateOrCreate(
                ['name' => $name],
                ['start_date' => $start, 'end_date' => $end],
            );
        }
    }

    protected function tearDown(): void
    {
        AccountingPeriod::query()
            ->whereIn('name', ['Cobertura de pruebas 2037', 'Cobertura de pruebas 2099'])
            ->delete();

        parent::tearDown();
    }
}
