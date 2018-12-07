<?php

namespace RollBot;

use Commlink\Character;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Signer\Hmac\Sha256;

require 'vendor/autoload.php';
$config = require 'config.php';

function loadUser(
    \MongoDB\Client $mongo,
    string $userId,
    string $teamId,
    string $channelId
) {
    $search = [
        'slack.user_id' => $userId,
        'slack.team_id' => $teamId,
        'slack.channel_id' => $channelId,
    ];
    return $mongo->shadowrun->users->findOne($search);
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
    $response->attachments[] = [
        'color' => 'danger',
        'title' => 'Bad Request',
        'text' => 'You don\'t seem to be registered to play. Let the channel ' .
            'know and they\'ll get you added.',
    ];
    echo $response;
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
if (!$characterId || !$campaignId) {
    $response->attachments[] = [
        'color' => 'danger',
        'title' => 'Bad Request',
        'text' => 'You don\'t seem to be registered to play. Let the channel ' .
            'know and they\'ll get you added.',
    ];
    echo $response;
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
$character = new Character($characterId, $guzzle, $jwt);
$character->campaignId = $campaignId;

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
echo $roll;
