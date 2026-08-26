<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/db.php';
require __DIR__ . '/lib.php';
require __DIR__ . '/userRepository.php';

use Pushok\AuthProvider;
use Pushok\Client;
use Pushok\Notification;
use Pushok\Payload;
use Pushok\Payload\Alert;

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

            $documentTitle = array_key_exists("document_title", $data) ? $data["document_title"] : null;
            sendNotification($connection, $userTokens, $data["document_id"], $documentTitle);
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

function sendNotification(\PgSql\Connection $connection, $deviceTokens, $document_id, $document_title = null)
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

    $alert = Alert::create()->setTitleLocKey("New Document consumed");
    if($document_title)
    {
        //$alert = $alert->setBody("Document " + $document_title + " was added to your server!");
        $alert = $alert->setLocKey("DOCUMENT_ADDED_FORMAT");
        $alert = $alert->setLocArgs([$document_title]);
    } else {
        $alert = $alert->setLocKey("DOCUMENT_ADDED_GENERIC");
    }

    $payload = Payload::create()->setAlert($alert);

    //set notification sound to default
    $payload->setSound('default');

    //add custom value to your notification, needs to be customized
    $payload->setCustomValue('document_id', $document_id);

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
