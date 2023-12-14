<?php
require __DIR__ . '/vendor/autoload.php';

use Ramsey\Uuid\Uuid;

function addDeviceToken($devicetoken, $user_id = null) {
    if ($user_id === null) {
        $user_id = Uuid::uuid4()->toString();
    }
    $db_user = getenv("POSTGRES_USER");
    $db_pass = getenv("POSTGRES_PASSWORD");
    $conn_string = "host=database port=5432 dbname=pushusers user=$db_user password=$db_pass";
    $connection = pg_connect($conn_string);

    $queryResult = pg_query($connection, "SELECT * FROM usertokens WHERE devicetoken = '$devicetoken'");
    
    if (!pg_num_rows($queryResult) >= 1) {
        $result = pg_insert($connection, "usertokens", ["devicetoken" => $devicetoken, "user_id" => $user_id]);
        if ($result == true) {
            http_response_code(201);
            echo $user_id;
        } else if ($result == false) {
            http_response_code(500);
            echo "Unexpected error!";
        }
    } else {
        $row = pg_fetch_array($queryResult, 0);
        if ($row && array_key_exists("user_id", $row)) {
            echo $row["user_id"];
        } else {
            http_response_code(500);
            echo "Unexpected error!";
        }
    }

    pg_close($connection);
}

$data = json_decode(file_get_contents('php://input'), true);

if ($data) {
    if (array_key_exists("devicetoken", $data) && array_key_exists("user_id", $data)) {
        if (!is_string($data["user_id"]) || (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $data["user_id"]) !== 1)) {
            http_response_code(400);
            echo "Invalid user id.",
            exit();
        } 
        addDeviceToken($data["devicetoken"], $data["user_id"]);
    } else if (array_key_exists("devicetoken", $data) && is_string($data["devicetoken"])){
        addDeviceToken($data["devicetoken"]);
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