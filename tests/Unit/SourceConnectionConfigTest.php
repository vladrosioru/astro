<?php

namespace Tests\Unit;

use Tests\TestCase;

class SourceConnectionConfigTest extends TestCase
{
    public function test_the_source_connection_is_defined_and_reads_prod_env(): void
    {
        $conn = config('database.connections.source');

        $this->assertIsArray($conn);
        $this->assertSame('mysql', $conn['driver']);
        // database is null until PROD_DB_DATABASE is set (dev only).
        $this->assertArrayHasKey('database', $conn);
    }
}
