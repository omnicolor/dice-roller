<?php

namespace RollBot;

use Commlink\Character;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Signer\Hmac\Sha256;

require 'vendor/autoload.php';
$config = require 'config.php';

header('Access-Control-Allow-Origin: *');
if ('OPTIONS' === $_SERVER['REQUEST_METHOD']) {
    // Handle CORS pre-flight requests.
    echo 'OK';
    exit();
}
header('Content-Type: application/json');

$log = new \Monolog\Logger('RollBot');
$log->pushHandler(new \Monolog\Handler\StreamHandler(
    $config['log_file'],
    \Monolog\Logger::DEBUG
));
$mongo = new \MongoDB\Client(sprintf(
    'mongodb://%s:%s@%s',
    $config['mongo']['user'],
    urlencode($config['mongo']['pass']),
    $config['mongo']['host']
));

try {
    $dispatcher = new Dispatcher($_POST, $config, $mongo, $log);
    $roll = $dispatcher->getRoll();
} catch (Exception\RollBotException $ex) {
    $log->error(sprintf('RollBot exception: %s', $ex->getMessage()));
    $attachment = [
        'color' => $ex->getColor(),
        'title' => $ex->getTitle(),
        'text' => $ex->getMessage(),
    ];
    if ($ex instanceof \RollBot\Exception\FieldsInterface) {
        $attachment['fields'] = $ex->getFields();
    }
    if ($ex instanceof \RollBot\Exception\ActionsInterface) {
        $attachment['actions'] = $ex->getActions();
    }
    $response = new Response();
    $response->attachments[] = $attachment;
    echo (string)$response;
    exit();
} catch (\Exception $ex) {
    $log->error(sprintf('Exception: %s', $ex->getMessage()));
    $attachment = [
        'color' => 'danger',
        'title' => 'Error',
        'text' => $ex->getMessage(),
    ];
    $response = new Response();
    $response->attachments[] = $attachment;
    echo (string)$response;
    exit();
}

if ($roll instanceof ConfigurableInterface) {
    $roll->setConfig($config);
}
if ($roll instanceof GuzzleClientInterface) {
    $guzzle = new \GuzzleHttp\Client(['base_uri' => $config['api']]);
    $roll->setGuzzleClient($guzzle);
}
if ($roll instanceof RedisClientInterface) {
    $redis = new \Predis\Client();
    $roll->setRedisClient($redis);
}
if ($roll instanceof MongoClientInterface) {
    $roll->setMongoClient($mongo);
}
if (!isset($_POST['to_channel'])) {
    // Normal Slack interaction, not from Commlink.
    if ($roll instanceof SlackInterface) {
        echo (string)$roll->getSlackResponse();
    } else {
        echo (string)$roll;
    }
    exit();
}

if (!$dispatcher->getCampaign()) {
    $log->error(
        'Could not post to Slack channel, no campaign',
        ['characterId' => $character->id]
    );
    echo 'Could not post to campaign channel';
    exit();
}

$hook = $dispatcher->getCampaign()->getSlackHook();
if (!$hook) {
    $log->error(
        'Could not post to Slack channel, no hook URL',
        [
            'characterId' => $dispatcher->getCharacter()->id,
            'campaignId' => $dispatcher->getCampaign()->getId(),
        ]
    );
    echo 'Could not post to campaign channel';
    exit();
}
if ($roll instanceof SlackInterface) {
    $roll = (string)$roll->getSlackResponse();
} else {
    $roll = (string)$roll;
}
$guzzle = new \GuzzleHttp\Client();
$guzzle->request(
    'POST',
    $hook,
    [
        'body' => $roll,
        'headers' => [
            'Content-Type' => 'application/json',
        ],
    ]
);
echo $roll;
