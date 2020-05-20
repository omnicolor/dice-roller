<?php

declare(strict_types=1);

require_once 'vendor/autoload.php';
$config = require 'config.php';

$loop = \React\EventLoop\Factory::create();
$client = new \CharlotteDunois\Yasmin\Client(array(), $loop);

$client->on('error', function ($error) {
    echo $error, PHP_EOL;
});

$client->on('ready', function () use ($client) {
    echo 'Logged in as ' . $client->user->tag . ' created on '
        . $client->user->createdAt->format('d.m.Y H:i:s') . PHP_EOL;
});

$client->on('message', function ($message) {
    if (substr($message->content, 0, 1) !== '/') {
        return;
    }
    echo 'Received Message from ' . $message->author->tag . ' in '
        . ($message->channel instanceof \CharlotteDunois\Yasmin\Interfaces\DMChannelInterface ? 'DM' : 'channel #' . $message->channel->name)
        . ' with: ' . $message->content, PHP_EOL;
    $message->reply('Right back at ya!');
});

$client->login($config['discord'])->done();
$loop->run();
