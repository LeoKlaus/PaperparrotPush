<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class BuildNotificationPayloadTest extends TestCase
{
    private function decode(\Pushok\Payload $payload): array
    {
        return json_decode(json_encode($payload), true);
    }

    public function testAlertIsAlwaysTheGenericLocalizedMessage(): void
    {
        $data = $this->decode(buildNotificationPayload('123', null));

        $this->assertSame('New Document consumed', $data['aps']['alert']['title-loc-key']);
        $this->assertSame('DOCUMENT_ADDED_GENERIC', $data['aps']['alert']['loc-key']);
    }

    public function testAlertCarriesNoDocumentContent(): void
    {
        // Nothing about the document but its ID may travel through the relay: no loc-args, no
        // plain title/body text.
        $data = $this->decode(buildNotificationPayload('123', 'abs-1'));

        $this->assertSame(['title-loc-key', 'loc-key'], array_keys($data['aps']['alert']));
    }

    public function testAlwaysCarriesDocumentIdAndDefaultSound(): void
    {
        $data = $this->decode(buildNotificationPayload('123', null));

        $this->assertSame('123', $data['document_id']);
        $this->assertSame('default', $data['aps']['sound']);
    }

    public function testRelaysInstanceIdWhenPresent(): void
    {
        $data = $this->decode(buildNotificationPayload('123', 'abs-1'));

        $this->assertSame('abs-1', $data['instance_id']);
        $this->assertSame('123', $data['document_id']);
    }

    public function testOmitsInstanceIdWhenAbsent(): void
    {
        $data = $this->decode(buildNotificationPayload('123', null));

        $this->assertArrayNotHasKey('instance_id', $data);
    }
}
