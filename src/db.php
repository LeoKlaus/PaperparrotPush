<?php
declare(strict_types=1);

function pushDbConnect(): \PgSql\Connection
{
    $host = getenv('POSTGRES_HOST') ?: 'database';
    $port = getenv('POSTGRES_PORT') ?: '5432';
    $dbname = getenv('POSTGRES_DB') ?: 'pushusers';
    $user = getenv('POSTGRES_USER');
    $pass = getenv('POSTGRES_PASSWORD');

    $connString = "host=$host port=$port dbname=$dbname user=$user password=$pass";
    $connection = pg_connect($connString);
    if ($connection === false) {
        throw new \RuntimeException('Could not connect to the database.');
    }
    return $connection;
}

function pushDbQuery(\PgSql\Connection $connection, string $query, array $params = []): \PgSql\Result
{
    $result = pg_query_params($connection, $query, $params);
    if ($result === false) {
        throw new \RuntimeException('Database query failed: ' . pg_last_error($connection));
    }
    return $result;
}
