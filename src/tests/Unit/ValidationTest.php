<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ValidationTest extends TestCase
{
    public function testValidUserIdAcceptsLowercaseUuidV4(): void
    {
        $this->assertTrue(isValidUserId('a1b2c3d4-e5f6-4789-89ab-0123456789ab'));
    }

    public function testValidUserIdRejectsUppercaseUuidV4(): void
    {
        // Unlike plappaPush's equivalent check, this regex has no case-insensitive
        // flag, so an otherwise-valid uppercase UUID is currently rejected.
        $this->assertFalse(isValidUserId('A1B2C3D4-E5F6-4789-89AB-0123456789AB'));
    }

    public function testValidUserIdRejectsWrongVersionNibble(): void
    {
        // Version nibble must be 4 (UUIDv4).
        $this->assertFalse(isValidUserId('a1b2c3d4-e5f6-1789-89ab-0123456789ab'));
    }

    public function testValidUserIdRejectsWrongVariantNibble(): void
    {
        // Variant nibble must be 8, 9, a, or b.
        $this->assertFalse(isValidUserId('a1b2c3d4-e5f6-4789-19ab-0123456789ab'));
    }

    public function testValidUserIdRejectsNonUuidString(): void
    {
        $this->assertFalse(isValidUserId('not-a-uuid'));
    }

    public function testValidUserIdRejectsEmptyString(): void
    {
        $this->assertFalse(isValidUserId(''));
    }
}
