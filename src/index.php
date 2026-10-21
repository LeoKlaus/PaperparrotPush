<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/db.php';
require __DIR__ . '/lib.php';
require __DIR__ . '/userRepository.php';

use Pushok\AuthProvider;
use Pushok\Client;
use Pushok\Notification;

$data = json_decode(file_get_contents('php://input'), true);

if ($data) {
    if (array_key_exists("user_id", $data) && array_key_exists("document_id", $data)) {

        $connection = pushDbConnect();
        try {
            $userTokens = getDeviceTokensForUser($connection, $data["user_id"]);

            if (!$userTokens) {
                http_response_code(400);
                echo "User {$data['user_id']} does not exist!";
                exit();
            }

            $instanceId = normalizeInstanceId($data["instance_id"] ?? null);
            sendNotification($connection, $userTokens, $data["document_id"], $instanceId);
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

function sendNotification(\PgSql\Connection $connection, $deviceTokens, $document_id, ?string $instance_id = null)
{

    $isProductionEnv = strtolower(getenv('IS_PRODUCTION')) === "true";

    $options = [
        'key_id' => getenv("KEY_ID"), // The Key ID obtained from Apple developer account
        'team_id' => getenv("TEAM_ID"), // The Team ID obtained from Apple developer account
        'app_bundle_id' => getenv("APP_BUNDLE_ID"), // The bundle ID for app obtained from Apple developer account
        'private_key_path' => getenv("KEYFILE_PATH"), // Path to private key
        'private_key_secret' => null // Private key secret
    ];

    $authProvider = AuthProvider\Token::create($options);

    $payload = buildNotificationPayload($document_id, $instance_id);

    $notifications = [];
    foreach ($deviceTokens as $deviceToken) {
        $notifications[] = new Notification($payload, $deviceToken);
    }

    $client = new Client($authProvider, $isProductionEnv);
    $client->addNotifications($notifications);

    $responses = $client->push(); // returns an array of ApnsResponseInterface (one Response per Notification)

    $json_response = array();

    foreach ($responses as $response) {
        $statusCode = $response->getStatusCode();
        $errorReason = $response->getErrorReason();

        if (shouldRemoveDeviceToken($statusCode, $errorReason)) {
            deleteDeviceToken($connection, $response->getDeviceToken());
        }

        if ($statusCode == 200) {
            $temp = [
                "deviceToken" => $response->getDeviceToken(),
                "reasonPhrase" => $response->getReasonPhrase()
            ];
            array_push($json_response, $temp);
        } else {
            $temp = [
                "deviceToken" => $response->getDeviceToken(),
                "reasonPhrase" => $response->getReasonPhrase(),
                "errorReason" => $errorReason,
                "errorDescription" => $response->getErrorDescription()
            ];
            array_push($json_response, $temp);
        }
    }

    header("Content-Type: application/json");
    echo json_encode($json_response);
}
