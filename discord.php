<?php

declare(strict_types=1);

namespace RollBot;

require_once 'vendor/autoload.php';
$config = require 'config.php';

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

$dispatcher = new DiscordDispatcher($config, $log, $mongo);

$loop = \React\EventLoop\Factory::create();
$client = new \CharlotteDunois\Yasmin\Client(array(), $loop);

$client->on('error', function ($error) {
    echo $error, PHP_EOL;
});

$client->on('ready', function () use ($client) {
    echo 'Logged in as ' . $client->user->tag . ' created on '
        . $client->user->createdAt->format('d.m.Y H:i:s') . PHP_EOL;
});

$client->on('message', function ($message) use ($dispatcher) {
    $dispatcher->handleRoll($message);
});

$client->login($config['discord'])->done();
$loop->run();
