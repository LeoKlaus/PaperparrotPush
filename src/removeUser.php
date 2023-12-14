<?php
require __DIR__ . '/vendor/autoload.php';

$data = json_decode(file_get_contents('php://input'), true);

if ($data) {
    if (array_key_exists("user_id", $data)) {
        removeUser($data["user_id"]);
    } else {
        http_response_code(400);
        echo "Invalid request. Please read the documentation in Paperparrot.",
        exit();
    }
} else {
    header("Location: https://paperparrot.me");
    die();
}

function removeUser($user_id)
{
    $db_user = getenv("POSTGRES_USER");
    $db_pass = getenv("POSTGRES_PASSWORD");
    $conn_string = "host=database port=5432 dbname=pushusers user=$db_user password=$db_pass";
    $connection = pg_connect($conn_string);

    $queryResult = pg_query($connection, "DELETE FROM usertokens 
    WHERE user_id = '$user_id'");
    pg_close($connection);
}
