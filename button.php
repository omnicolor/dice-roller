<?php
/**
 * The user clicked a button on a Slack message.
 */

namespace RollBot;

use Commlink\Character;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use GuzzleHttp\Client as Guzzle;
use MongoDB\Client as Mongo;
use Predis\Client as Predis;

require 'vendor/autoload.php';
$config = require 'config.php';

header('Content-Type: application/json');

function fixName(string $name): string
{
    return strtolower(str_replace(' ', '_', $name));
}

$mongo = new Mongo(
    sprintf(
        'mongodb://%s:%s@%s',
        $config['mongo']['user'],
        urlencode($config['mongo']['pass']),
        $config['mongo']['host']
    )
);
$redis = new Predis();
$response = new Response();
$payload = json_decode($_POST['payload']);
$action = $payload->actions[0]->name;
$type = $payload->actions[0]->value;
$userId = $payload->user->id;
$teamId = $payload->team->id;
$channelId = $payload->channel->id;
$originalMessage = $payload->original_message;
$args = [];

try {
    $user = new User($mongo, $userId, $teamId, $channelId);
} catch (\Exception $e) {
    // We couldn't find the user... This shouldn't happen since they clicked
    // a button...
    $url = sprintf(
        '%ssettings?%s',
        $config['web'],
        http_build_query([
            'user_id' => $userId,
            'team_id' => $teamId,
            'channel_id' => $channelId,
        ])
    );
    $response->attachments[] = [
        'actions' => [
            [
                'type' => 'button',
                'text' => 'Register',
                'url' => $url,
            ],
            'footer' => $e->getMessage(),
        ],
        'color' => 'danger',
        'replace_original' => false,
        'title' => 'Bad Request',
        'text' => 'You don\'t seem to be registered to play.',
    ];
    echo (string)$response;
    exit();
}

$guzzle = new Guzzle(['base_uri' => $config['api']]);
$jwt = (new Builder())
    ->setIssuer('https://sr.digitaldarkness.com')
    ->setAudience($config['api'])
    ->setIssuedAt(time())
    ->setExpiration(time() + 60)
    ->set('email', $user->email)
    ->sign(new Sha256(), $config['secret'])
    ->getToken();

$campaignId = $characterId = null;
foreach ($user->slack as $slack) {
    if ($userId === $slack->user_id &&
        $teamId === $slack->team_id &&
        $channelId === $slack->channel_id) {

        $characterId = $slack->character_id;
        $campaignId = $slack->campaign_id;
        break;
    }
}
if ($characterId) {
    $character = new Character($characterId, $guzzle, $jwt);
} else {
    $character = new Character();
    $character->handle = 'GM';
}
$character->campaignId = $campaignId;

// Make sure it's not another character trying to edge this roll.
if (($payload->callback_id != $character->handle)
    && ($payload->callback_id != $campaignId)) {

    $response->replaceOriginal = false;
    $response->deleteOriginal = false;
    $response->toChannel = false;
    $response->attachments[] = [
        'color' => 'danger',
        'replace_original' => false,
        'response_type' => 'ephemeral',
        'title' => 'Bad Request',
        'text' => 'You can\'t click buttons on someone else\'s rolls.',
    ];
    echo (string)$response;
    exit();
}

if (!$type) {
    $type = $action;
    $args = json_decode($_POST['payload'], true);
} elseif (false !== strpos($type, ' ')) {
    $args = explode(' ', $type);
    $type = array_shift($args);
}

try {
    $class = 'RollBot\\' . ucfirst($type);
    $roll = new $class($character, $args);
} catch (\Error $e) {
    error_log($action . ' ' . $type . ' ' . $e->getMessage());
    $response->replaceOriginal = false;
    $response->toChannel = false;
    $response->attachments[] = [
        'color' => 'danger',
        'replace_original' => false,
        'response_type' => 'ephemeral',
        'title' => 'Bad Request',
        'text' => 'I don\'t know how to handle that action.',
    ];
    echo (string)$response;
    exit();
}

if ($roll instanceof GuzzleClientInterface) {
    $roll->setGuzzleClient(new Guzzle());
}
if ($roll instanceof MongoClientInterface) {
    $roll->setMongoClient($mongo);
}
if ($roll instanceof RedisClientInterface) {
    $roll->setRedisClient($redis);
}

if (defined(get_class($roll) . '::UPDATE_MESSAGE') && $roll::UPDATE_MESSAGE) {
    $response = (string)$roll;
    $originalMessage = json_decode($response);

    // Change the original message to not include the button, and grey out the
    // coloring.
    unset(
        $originalMessage->attachments[0]->actions,
        $originalMessage->attachments[0]->color
    );
    $originalMessage->attachments[] = $response->attachments[0];
    $originalMessage->response_type = 'in_channel';
    echo json_encode($originalMessage);
    exit();
}
echo $roll;
