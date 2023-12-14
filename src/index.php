<?php
require __DIR__ . '/vendor/autoload.php';

use Pushok\AuthProvider;
use Pushok\Client;
use Pushok\Notification;
use Pushok\Payload;
use Pushok\Payload\Alert;

$data = json_decode(file_get_contents('php://input'), true);

if ($data) {
    if (array_key_exists("user_id", $data) && array_key_exists("document_id", $data)) {

        $userTokens = getDeviceTokensForUser($data["user_id"]);

        if (array_key_exists("document_title", $data)) {
            sendNotification($userTokens, $data["document_id"], $data["document_title"]);
        } else {
            sendNotification($userTokens, $data["document_id"]);
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

function getDeviceTokensForUser($user_id) {
    
    $db_user = getenv("POSTGRES_USER");
    $db_pass = getenv("POSTGRES_PASSWORD");
    $conn_string = "host=database port=5432 dbname=pushusers user=$db_user password=$db_pass";
    $connection = pg_connect($conn_string);

    $queryResult = pg_query($connection, "SELECT devicetoken FROM usertokens WHERE user_id = '$user_id'");

    $tokens = [];
    if (pg_num_rows($queryResult) >= 1) {
        $resultingRows = pg_fetch_all($queryResult);
        foreach($resultingRows as $result) {
            array_push($tokens, $result["devicetoken"]);
        }
        return $tokens;
    } else {
        http_response_code(400);
        echo "User $user_id does not exist!";
    }

    pg_close($connection);
}

function removeDevice($tokenToRemove) {
    $db_user = getenv("POSTGRES_USER");
    $db_pass = getenv("POSTGRES_PASSWORD");
    $conn_string = "host=database port=5432 dbname=pushusers user=$db_user password=$db_pass";
    $connection = pg_connect($conn_string);

    $queryResult = pg_query($connection, "DELETE FROM usertokens 
    WHERE devicetoken = '$tokenToRemove'");
    pg_close($connection);
}

function sendNotification($deviceTokens, $document_id, $document_title = null)
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
        // The device token
        $response->getDeviceToken();
        // A canonical UUID that is the unique ID for the notification. E.g. 123e4567-e89b-12d3-a456-4266554400a0
        $response->getApnsId();
        // Status code. E.g. 200 (Success), 410 (The device token is no longer active for the topic.)
        $response->getStatusCode();
        // E.g. The device token is no longer active for the topic.
        $response->getReasonPhrase();
        // E.g. Unregistered
        $response->getErrorReason();
        // E.g. The device token is inactive for the specified topic.
        $response->getErrorDescription();
        $response->get410Timestamp();

        if($response->getStatusCode() == 410) {
            removeDevice($response->getDeviceToken());
            $temp = [
                "deviceToken" => $response->getDeviceToken(),
                "reasonPhrase" => $response->getReasonPhrase(),
                "errorReason" => $response->getErrorReason(),
                "errorDescription" => $response->getErrorDescription()
            ];
            array_push($json_response, $temp);
            http_response_code(400);
        } else if($response->getStatusCode() != 200) {
            $temp = [
                "deviceToken" => $response->getDeviceToken(),
                "reasonPhrase" => $response->getReasonPhrase(),
                "errorReason" => $response->getErrorReason(),
                "errorDescription" => $response->getErrorDescription()
            ];
            array_push($json_response, $temp);
            http_response_code(400);
        } else {
            $temp = [
                "deviceToken" => $response->getDeviceToken(),
                "reasonPhrase" => $response->getReasonPhrase()
            ];
            array_push($json_response, $temp);
        }
    }

    header("Content-Type: application/json");
    echo json_encode($json_response);
}
exit();
?>