<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ShouldRemoveDeviceTokenTest extends TestCase
{
    public function testRemovesOnUnregistered410(): void
    {
        $this->assertTrue(shouldRemoveDeviceToken(410, 'Unregistered'));
    }

    public function testRemovesOn410RegardlessOfErrorReason(): void
    {
        $this->assertTrue(shouldRemoveDeviceToken(410, null));
    }

    public function testRemovesOnBadDeviceToken400(): void
    {
        // The regression this guards against: a malformed token, or one issued for the wrong
        // APNs environment, comes back as 400/BadDeviceToken and will fail identically forever —
        // it must be treated the same as a 410 and removed.
        $this->assertTrue(shouldRemoveDeviceToken(400, 'BadDeviceToken'));
    }

    public function testKeepsTokenOnSuccess(): void
    {
        $this->assertFalse(shouldRemoveDeviceToken(200, null));
    }

    public function testKeepsTokenOnOtherBadRequestReasons(): void
    {
        // Other 400 reasons (bad payload, bad topic, etc.) are about this specific request, not
        // the token itself, so the token should be kept.
        $this->assertFalse(shouldRemoveDeviceToken(400, 'BadMessageId'));
        $this->assertFalse(shouldRemoveDeviceToken(400, 'PayloadTooLarge'));
        $this->assertFalse(shouldRemoveDeviceToken(400, null));
    }

    public function testKeepsTokenOnServerOrAuthErrors(): void
    {
        $this->assertFalse(shouldRemoveDeviceToken(403, 'BadCertificate'));
        $this->assertFalse(shouldRemoveDeviceToken(500, 'InternalServerError'));
        $this->assertFalse(shouldRemoveDeviceToken(503, 'ServiceUnavailable'));
    }
}
