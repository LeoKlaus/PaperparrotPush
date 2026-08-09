<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

use Ramsey\Uuid\Uuid;

/**
 * @return array{userId: string, created: bool}
 */
function registerDeviceToken(\PgSql\Connection $connection, string $deviceToken, ?string $userId): array
{
    $existing = pushDbQuery($connection, 'SELECT user_id FROM usertokens WHERE devicetoken = $1', [$deviceToken]);
    $row = pg_fetch_assoc($existing);
    if ($row !== false) {
        return ['userId' => $row['user_id'], 'created' => false];
    }

    if ($userId === null) {
        $userId = Uuid::uuid4()->toString();
    }

    pushDbQuery(
        $connection,
        'INSERT INTO usertokens (devicetoken, user_id) VALUES ($1, $2)',
        [$deviceToken, $userId]
    );

    return ['userId' => $userId, 'created' => true];
}

function getDeviceTokensForUser(\PgSql\Connection $connection, string $userId): array
{
    $result = pushDbQuery($connection, 'SELECT devicetoken FROM usertokens WHERE user_id = $1', [$userId]);
    $tokens = [];
    while ($row = pg_fetch_assoc($result)) {
        $tokens[] = $row['devicetoken'];
    }
    return $tokens;
}

function deleteDeviceToken(\PgSql\Connection $connection, string $deviceToken): void
{
    pushDbQuery($connection, 'DELETE FROM usertokens WHERE devicetoken = $1', [$deviceToken]);
}

function deleteUserTokens(\PgSql\Connection $connection, string $userId): void
{
    pushDbQuery($connection, 'DELETE FROM usertokens WHERE user_id = $1', [$userId]);
}
