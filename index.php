<?php
/**
 * Parse payload from SendGrid's Inbound Parse and send a Slack notification
 * 
 * @author  Michael Okoko <michael@mchl.xyz>
 * @license https://opensource.org/licenses/MIT MIT
 */


require_once './vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

try {
    if (strtoupper($_SERVER['REQUEST_METHOD'] != 'POST')) {
        throw new Exception("Received non-post request on webhook handler");
    }
    file_put_contents("post.json", json_encode($_POST, JSON_PRETTY_PRINT));
    $request = json_decode(file_get_contents('php://input'), true);
    file_put_contents("payload.json", json_encode($request, JSON_PRETTY_PRINT));

    if (json_last_error() != JSON_ERROR_NONE) {
        $em = "Error while parsing payload: ".json_last_error_msg();
        throw new Exception($em);
    }
    
    $from = $_POST['from'];
    $to = $_POST['to'];

    preg_match("#<(.*?)>#", $from, $sender);
    preg_match("#<(.*?)>#", $to, $recipient);
    $senderAddr = $sender[1];
    $recipientAddr = $recipient[1];

    $message = "*You've got mail!*\n";
    $message .= "*To:* ".$recipientAddr."\n";
    $message .= "*From:* ".$senderAddr;

    notifyOnSlack($message, true);

    // send OK back to SendGrid so they stop bothering our webhook
    header("Content-type: application/json; charset=utf-8");
    echo json_encode(["message" => "OK"]);
    exit(0);
} catch (Exception $e) {
    notifyOnSlack($e->getMessage());
    header("Content-type: application/json; charset=utf-8");
    http_response_code(400);
    echo json_encode(["message" => $e->getMessage()]);
    exit(0);
}

/**
 * Processes thrown exception while processing the payload
 * 
 * @param $e \Exception
 * 
 * @return void
 */
function handleException($e) 
{
    notifyOnSlack($e->getMessage());
}

/**
 * Sends message to Slack
 * 
 * @param $message string
 * 
 * @return void
 */
function notifyOnSlack($message, $markdown = false)
{
    $slackHookUrl = $_ENV["SLACK_HOOK_URL"];
    $options = [
        "channel" => "#general",
        "allow_markdown" => $markdown,
        "username" => "bref-email-watch",
    ];
    $client = new Nexy\Slack\Client(
        \Http\Discovery\Psr18ClientDiscovery::find(),
        \Http\Discovery\Psr17FactoryDiscovery::findRequestFactory(),
        \Http\Discovery\Psr17FactoryDiscovery::findStreamFactory(),
        $slackHookUrl,
        $options
    );
    $client->send($message);
}
