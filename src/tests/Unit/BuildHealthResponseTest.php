<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class BuildHealthResponseTest extends TestCase
{
    public function testHealthyDatabaseReturns200(): void
    {
        $response = buildHealthResponse(true);

        $this->assertSame(200, $response['statusCode']);
        $this->assertSame(['ok' => true], $response['body']);
    }

    public function testUnreachableDatabaseReturns503(): void
    {
        $response = buildHealthResponse(false);

        $this->assertSame(503, $response['statusCode']);
        $this->assertSame(['ok' => false, 'error' => 'Database unavailable.'], $response['body']);
    }
}
