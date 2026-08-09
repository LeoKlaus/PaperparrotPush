<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/db.php';
require __DIR__ . '/lib.php';
require __DIR__ . '/userRepository.php';

$data = json_decode(file_get_contents('php://input'), true);

if ($data) {
    if (array_key_exists("devicetoken", $data) && array_key_exists("user_id", $data)) {
        if (!is_string($data["user_id"]) || !isValidUserId($data["user_id"])) {
            http_response_code(400);
            echo "Invalid user id.",
            exit();
        }
        respondWithRegisteredUser($data["devicetoken"], $data["user_id"]);
    } else if (array_key_exists("devicetoken", $data) && is_string($data["devicetoken"])){
        respondWithRegisteredUser($data["devicetoken"]);
    } else {
        http_response_code(400);
        echo "Invalid request. Please read the documentation in Paperparrot.",
        exit();
    }
} else {
    http_response_code(400);
    echo "Invalid request. Please read the documentation in Paperparrot.",
    exit();
}

function respondWithRegisteredUser(string $deviceToken, ?string $userId = null): void
{
    $connection = pushDbConnect();
    try {
        $result = registerDeviceToken($connection, $deviceToken, $userId);
        if ($result['created']) {
            http_response_code(201);
        }
        echo $result['userId'];
    } finally {
        pg_close($connection);
    }
}
