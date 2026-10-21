<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class NormalizeInstanceIdTest extends TestCase
{
    public function testKeepsNonEmptyString(): void
    {
        $this->assertSame('abs-1', normalizeInstanceId('abs-1'));
    }

    public function testMissingValueMeansNoInstanceId(): void
    {
        $this->assertNull(normalizeInstanceId(null));
    }

    public function testEmptyStringMeansNoInstanceId(): void
    {
        $this->assertNull(normalizeInstanceId(''));
    }

    public function testNonStringValuesMeanNoInstanceId(): void
    {
        $this->assertNull(normalizeInstanceId(123));
        $this->assertNull(normalizeInstanceId(true));
        $this->assertNull(normalizeInstanceId(['abs-1']));
    }
}
