<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class UserRepositoryTest extends TestCase
{
    private \PgSql\Connection $connection;

    protected function setUp(): void
    {
        $this->connection = pushDbConnect();
        pushDbQuery($this->connection, 'TRUNCATE usertokens');
    }

    protected function tearDown(): void
    {
        pg_close($this->connection);
    }

    public function testRegisterDeviceTokenGeneratesNewUserIdWhenNoneProvided(): void
    {
        $deviceToken = str_repeat('a', 64);

        $result = registerDeviceToken($this->connection, $deviceToken, null);

        $this->assertTrue($result['created']);
        $this->assertTrue(isValidUserId($result['userId']));
        $this->assertSame($result['userId'], $this->storedUserIdFor($deviceToken));
    }

    public function testRegisterDeviceTokenGeneratesDifferentUserIdsForDifferentTokens(): void
    {
        $first = registerDeviceToken($this->connection, str_repeat('a', 64), null);
        $second = registerDeviceToken($this->connection, str_repeat('b', 64), null);

        $this->assertNotSame($first['userId'], $second['userId']);
    }

    public function testRegisterDeviceTokenHonorsProvidedUserIdForNewToken(): void
    {
        $deviceToken = str_repeat('c', 64);
        $providedUserId = 'a1b2c3d4-e5f6-4789-89ab-0123456789ab';

        $result = registerDeviceToken($this->connection, $deviceToken, $providedUserId);

        $this->assertTrue($result['created']);
        $this->assertSame($providedUserId, $result['userId']);
        $this->assertSame($providedUserId, $this->storedUserIdFor($deviceToken));
    }

    public function testRegisteringSameTokenTwiceReturnsExistingUserIdWithoutReassigning(): void
    {
        // Unlike plappaPush, re-registering an existing device token does NOT
        // reassign it to a new user_id — the original owner is kept, and the
        // userId argument on the second call is ignored.
        $deviceToken = str_repeat('d', 64);
        $originalUserId = 'a1b2c3d4-e5f6-4789-89ab-0123456789ab';
        $otherUserId = 'ffffffff-ffff-4fff-8fff-ffffffffffff';

        $first = registerDeviceToken($this->connection, $deviceToken, $originalUserId);
        $second = registerDeviceToken($this->connection, $deviceToken, $otherUserId);

        $this->assertTrue($first['created']);
        $this->assertFalse($second['created']);
        $this->assertSame($originalUserId, $second['userId']);
        $this->assertSame($originalUserId, $this->storedUserIdFor($deviceToken));
        $this->assertSame(1, $this->rowCountForToken($deviceToken));
    }

    public function testGetDeviceTokensForUserReturnsAllTokensForThatUser(): void
    {
        $userId = 'a1b2c3d4-e5f6-4789-89ab-0123456789ab';
        registerDeviceToken($this->connection, str_repeat('a', 64), $userId);
        registerDeviceToken($this->connection, str_repeat('b', 64), $userId);
        registerDeviceToken($this->connection, str_repeat('c', 64), 'ffffffff-ffff-4fff-8fff-ffffffffffff');

        $tokens = getDeviceTokensForUser($this->connection, $userId);

        sort($tokens);
        $this->assertSame([str_repeat('a', 64), str_repeat('b', 64)], $tokens);
    }

    public function testGetDeviceTokensForUserReturnsEmptyArrayForUnknownUser(): void
    {
        $tokens = getDeviceTokensForUser($this->connection, 'a1b2c3d4-e5f6-4789-89ab-0123456789ab');

        $this->assertSame([], $tokens);
    }

    public function testDeleteDeviceTokenRemovesOnlyThatToken(): void
    {
        $keep = str_repeat('e', 64);
        $remove = str_repeat('f', 64);
        registerDeviceToken($this->connection, $keep, null);
        registerDeviceToken($this->connection, $remove, null);

        deleteDeviceToken($this->connection, $remove);

        $this->assertSame(0, $this->rowCountForToken($remove));
        $this->assertSame(1, $this->rowCountForToken($keep));
    }

    public function testDeleteUserTokensRemovesAllDevicesForThatUserOnly(): void
    {
        $userId = 'a1b2c3d4-e5f6-4789-89ab-0123456789ab';
        $otherUserId = 'ffffffff-ffff-4fff-8fff-ffffffffffff';
        registerDeviceToken($this->connection, str_repeat('a', 64), $userId);
        registerDeviceToken($this->connection, str_repeat('b', 64), $userId);
        registerDeviceToken($this->connection, str_repeat('c', 64), $otherUserId);

        deleteUserTokens($this->connection, $userId);

        $this->assertSame([], getDeviceTokensForUser($this->connection, $userId));
        $this->assertSame([str_repeat('c', 64)], getDeviceTokensForUser($this->connection, $otherUserId));
    }

    private function storedUserIdFor(string $deviceToken): ?string
    {
        $result = pushDbQuery($this->connection, 'SELECT user_id FROM usertokens WHERE devicetoken = $1', [$deviceToken]);
        $row = pg_fetch_assoc($result);
        return $row['user_id'] ?? null;
    }

    private function rowCountForToken(string $deviceToken): int
    {
        $result = pushDbQuery($this->connection, 'SELECT COUNT(*) AS count FROM usertokens WHERE devicetoken = $1', [$deviceToken]);
        $row = pg_fetch_assoc($result);
        return (int) $row['count'];
    }
}
