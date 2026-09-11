<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/db.php';
require __DIR__ . '/lib.php';

header('Content-Type: application/json');

$databaseReachable = true;
try {
    $connection = pushDbConnect();
    pushDbQuery($connection, 'SELECT 1');
    pg_close($connection);
} catch (\Throwable $e) {
    $databaseReachable = false;
}

$response = buildHealthResponse($databaseReachable);
http_response_code($response['statusCode']);
echo json_encode($response['body']);
