<?php

namespace RollBot;

use Commlink\Character;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Signer\Hmac\Sha256;

require 'vendor/autoload.php';
$config = require 'config.php';

/**
 * Try to load a campaign attached to the current team and channel.
 * @param \MongoDB\Client $mongo
 * @param string $teamId
 * @param string $channelId
 * @return \MongoDB\Model\BSONDocument
 */
function loadCampaign(
    \MongoDb\Client $mongo,
    string $teamId,
    string $channelId
): ?\MongoDB\Model\BSONDocument {
    $search = [
        'slack-team' => $teamId,
        'slack-channel' => $channelId,
    ];
    return $mongo->shadowrun->campaigns->findOne($search);
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
if (isset($_POST['payload'])) {
    require 'button.php';
    exit();
}

if (!isset($_POST['user_id'], $_POST['team_id'], $_POST['channel_id'])) {
    header('Content-Type: text/plain');
    echo 'Bad Request', PHP_EOL, PHP_EOL,
        'Your request does not seem to be a valid Slack slash command.',
        PHP_EOL;
    exit();
}

header('Content-Type: application/json');
if (!isset($_POST['text']) || !trim($_POST['text'])) {
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
$args = explode(' ', $_POST['text']);

try {
    $user = new User(
        $mongo,
        $_POST['user_id'],
        $_POST['team_id'],
        $_POST['channel_id']
    );
} catch (\Exception $e) {
    // The user is not registered, is the channel registered?
    $campaign = loadCampaign($mongo, $_POST['team_id'], $_POST['channel_id']);
    if (!$campaign) {
        $response->attachments[] = [
            'color' => 'danger',
            'title' => 'Bad Request',
            'text' => 'There does not seem to be a campaign linked to this '
            . 'channel. If you\'re the Gamemaster, go to the campaign page in '
            .  $config['web'] . ' and set it up.',
            'fields' => [
                [
                    'title' => 'team_id',
                    'value' => $_POST['team_id'],
                    'short' => true,
                ],
                [
                    'title' => 'channel_id',
                    'value' => $_POST['channel_id'],
                    'short' => true,
                ],
            ],
        ];
        echo (string)$response;
        exit();
    }
    sendUnregisteredResponse(
        $response,
        $config['web'],
        $_POST['user_id'],
        $_POST['team_id'],
        $_POST['channel_id']
    );
    exit();
}

$campaignId = $characterId = null;
foreach ($user->slack as $slack) {
    if ($_POST['user_id'] === $slack->user_id &&
        $_POST['team_id'] === $slack->team_id &&
        $_POST['channel_id'] === $slack->channel_id) {
        $characterId = $slack->character_id;
        $campaignId = $slack->campaign_id;
        break;
    }
}
if (!$campaignId) {
    sendUnregisteredResponse(
        $config['web'],
        $response,
        $_POST['user_id'],
        $_POST['team_id'],
        $_POST['channel_id']
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
} else {
    $character = new Character();
    $character->handle = 'GM';
}
$character->campaignId = $campaignId;

if (!is_numeric($args[0])) {
    try {
        $class = 'RollBot\\' . ucfirst($args[0]);
        $roll = new $class($character, $args);
    } catch (\Error $e) {
        $response->attachments[] = [
            'color' => 'danger',
            'title' => 'Bad Request',
            'text' => 'That doesn\'t seem to be a valid command. Try `/roll help`.',
        ];
        echo (string)$response;
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
