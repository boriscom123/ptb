<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Защита от запуска тестов на рабочей БД: переменные контейнера не должны перекрывать phpunit.xml.
 */
class TestEnvironmentTest extends TestCase
{
    public function test_tests_use_isolated_environment(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame('pgsql_testing', DB::getDefaultConnection());
        $this->assertStringEndsWith('_test', DB::connection()->getDatabaseName());
    }
}
