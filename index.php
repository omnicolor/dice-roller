<?php

namespace RollBot;

use Commlink\Character;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Signer\Hmac\Sha256;

require 'vendor/autoload.php';
$config = require 'config.php';

/**
 * Load a Commlink user based on the Slack information from the request.
 * @param \MongoDB\Client $mongo
 * @param string $userId
 * @param string $teamId
 * @param string $channelId
 * @return \MongoDB\Model\BSONDocument
 */
function loadUser(
    \MongoDB\Client $mongo,
    string $userId,
    string $teamId,
    string $channelId
): ?\MongoDB\Model\BSONDocument {
    $search = [
        'slack.user_id' => $userId,
        'slack.team_id' => $teamId,
        'slack.channel_id' => $channelId,
    ];
    return $mongo->shadowrun->users->findOne($search);
}

/**
 * The user is not registered in Commlink, give them a registration button.
 * @param Response $response
 * @param string $api
 * @param string|null $userId
 * @param string|null $teamId
 * @param string|null $channelId
 */
function sendUnregisteredResponse(
    Response $response,
    string $web,
    ?string $userId,
    ?string $teamId,
    ?string $channelId
): void {
    $url = sprintf(
        '%ssettings?%s',
        $web,
        http_build_query([
            'user_id' => $userId,
            'team_id' => $teamId,
            'channel_id' => $channelId,
        ])
    );
    $response->attachments[] = [
        'color' => 'danger',
        'title' => 'Bad Request',
        'text' => 'You don\'t seem to be registered to play.',
        'actions' => [
            [
                'type' => 'button',
                'text' => 'Register',
                'url' => $url,
            ],
        ],
    ];
    echo (string)$response;
}

$response = new Response();
$redis = new \Predis\Client();
$mongo = new \MongoDB\Client(
    sprintf(
        'mongodb://%s:%s@%s',
        $config['mongo']['user'],
        urlencode($config['mongo']['pass']),
        $config['mongo']['host']
    )
);


if (!isset($_GET['user_id'], $_GET['team_id'], $_GET['channel_id'])) {
    header('Content-Type: text/plain');
    echo 'Bad Request', PHP_EOL, PHP_EOL,
        'Your request does not seem to be a valid Slack slash command.',
        PHP_EOL;
    exit();
}

header('Content-Type: application/json');
if (!isset($_GET['text']) || !trim($_GET['text'])) {
    $response->attachments[] = [
        'color' => 'danger',
        'title' => 'Bad Request',
        'text' => 'You must include at least one command argument.' .
            PHP_EOL . 'For example: `/roll init` to roll your character\'s ' .
            'initiative, `/roll 1` to roll one die, or `/roll 12 6` to roll ' .
            'twelve dice with a limit of six.' . PHP_EOL . PHP_EOL
            . 'Type `/roll help` for more help.',
    ];
    echo $response;
    exit();
}
$args = explode(' ', $_GET['text']);

$user = loadUser($mongo, $_GET['user_id'], $_GET['team_id'], $_GET['channel_id']);
if (!$user) {
    sendUnregisteredResponse(
        $response,
        $config['web'],
        $_GET['user_id'],
        $_GET['team_id'],
        $_GET['channel_id']
    );
    exit();
}

$campaignId = $characterId = null;
foreach ($user->slack as $slack) {
    if ($_GET['user_id'] === $slack->user_id &&
        $_GET['team_id'] === $slack->team_id &&
        $_GET['channel_id'] === $slack->channel_id) {
        $characterId = $slack->character_id;
        $campaignId = $slack->campaign_id;
        break;
    }
}
if (!$campaignId) {
    sendUnregisteredResponse(
        $config['web'],
        $response,
        $_GET['user_id'],
        $_GET['team_id'],
        $_GET['channel_id']
    );
    exit();
}

$guzzle = new \GuzzleHttp\Client(['base_uri' => $config['api']]);
$jwt = (new Builder())
    ->setIssuer('https://sr.digitaldarkness.com')
    ->setAudience($config['api'])
    ->setIssuedAt(time())
    ->setExpiration(time() + 60)
    ->set('email', $user->email)
    ->sign(new Sha256(), $config['secret'])
    ->getToken();

if ($characterId) {
    $character = new Character($characterId, $guzzle, $jwt);
    $character->campaignId = $campaignId;
} else {
    $character = new Character();
    $character->handle = 'GM';
}

if (!is_numeric($args[0])) {
    try {
        $class = 'RollBot\\' . ucfirst($args[0]);
        $roll = new $class($character, $args);
    } catch (\Error $e) {
        echo 'Nope: ' . $e->getMessage();
        exit();
    }
} else {
    $roll = new Roll($character, $args);
}
if ($roll instanceof RedisClientInterface) {
    $roll->setRedisClient($redis);
}
if ($roll instanceof MongoClientInterface) {
    $roll->setMongoClient($mongo);
}
echo $roll;
