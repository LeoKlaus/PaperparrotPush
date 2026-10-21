<?php
declare(strict_types=1);

function isValidUserId(string $userId): bool
{
    return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $userId);
}

function normalizeInstanceId(mixed $value): ?string
{
    return is_string($value) && $value !== '' ? $value : null;
}

/**
 * Builds the APNs payload for one notification — pulled out of index.php so it's testable without
 * an APNs client or network call. The alert text is localized client-side (loc keys) and never
 * contains anything about the document itself: the document is identified only via the
 * document_id custom value, and instance_id is relayed only when present.
 */
function buildNotificationPayload(mixed $documentId, ?string $instanceId): \Pushok\Payload
{
    $alert = \Pushok\Payload\Alert::create()
        ->setTitleLocKey("New Document consumed")
        ->setLocKey("DOCUMENT_ADDED_GENERIC");

    $payload = \Pushok\Payload::create()->setAlert($alert)->setSound('default');
    $payload->setCustomValue('document_id', $documentId);

    if ($instanceId !== null) {
        $payload->setCustomValue('instance_id', $instanceId);
    }

    return $payload;
}

/**
 * Shapes health.php's response: pulled out of the endpoint so the status-code/body mapping is
 * testable without a real database connection. $databaseReachable is the one thing the endpoint
 * actually had to find out via I/O; everything downstream of that is pure.
 *
 * @return array{statusCode: int, body: array}
 */
function buildHealthResponse(bool $databaseReachable): array
{
    if ($databaseReachable) {
        return ['statusCode' => 200, 'body' => ['ok' => true]];
    }

    return ['statusCode' => 503, 'body' => ['ok' => false, 'error' => 'Database unavailable.']];
}

/**
 * Whether an APNs response means this device token will never succeed again and should be
 * removed. 410/Unregistered is the token going away normally (app uninstalled, etc.). 400/
 * BadDeviceToken means the token itself is malformed or was issued for a different APNs
 * environment (e.g. a dev token sent against the prod endpoint)
 */
function shouldRemoveDeviceToken(int $statusCode, ?string $errorReason): bool
{
    if ($statusCode === 410) {
        return true;
    }

    if ($statusCode === 400 && $errorReason === 'BadDeviceToken') {
        return true;
    }

    return false;
}
