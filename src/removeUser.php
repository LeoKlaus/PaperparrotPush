<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/db.php';
require __DIR__ . '/userRepository.php';

$data = json_decode(file_get_contents('php://input'), true);

if ($data) {
    if (array_key_exists("user_id", $data)) {
        $connection = pushDbConnect();
        try {
            deleteUserTokens($connection, $data["user_id"]);
        } finally {
            pg_close($connection);
        }
    } else {
        http_response_code(400);
        echo "Invalid request. Please read the documentation in Paperparrot.",
        exit();
    }
} else {
    header("Location: https://paperparrot.me");
    die();
}
