<?php

require 'vendor/autoload.php';

use Teapot\StatusCode;

$players = require 'players.php';

function roll6() : int
{
    return random_int(1, 6);
}

class Response
{
    public $attachments;

    /**
     * @var string Text to send
     */
    public $text;

    /**
     * @var boolean Whether to also send the request to the channel it was
     * requested in
     */
    public $toChannel = false;

    public function __toString() : string
    {
        $res = [];
        if ($this->text) {
            $res['text'] = $this->text;
        }
        if ($this->toChannel) {
            $res['response_type'] = 'in_channel';
        } else {
            $res['response_type'] = 'ephemeral';
        }
        if ($this->attachments) {
            $res['attachments'] = $this->attachments;
        }
        return json_encode($res);
    }
}

$response = new Response();
$redis = new Predis\Client();

header('Content-Type: application/json');

if (!isset($_GET['text']) || !trim($_GET['text'])) {
    $response->attachments[] = [
        'color' => 'danger',
        'title' => 'Bad Request',
        'text' => 'You must include at least one command argument.' .
            PHP_EOL . 'For example: `/roll init` to roll your character\'s ' .
            'initiative, `/roll 1` to roll one die, or `/roll 12 6` to roll ' .
            'twelve dice with a limit of six.',
    ];
    echo $response;
    exit();
}

if (!isset($players[$_GET['user_name']])) {
    error_log('RollBot: ' . $_GET['user_name'] . ' not registered');
    $response->attachments[] = [
        'color' => 'danger',
        'title' => 'Bad Request',
        'text' => 'You don\'t seem to be registered to play. Let the channel ' .
            'know and they\'ll get you added.',
    ];
    echo $response;
    exit();
}

$player = $players[$_GET['user_name']];
$args = explode(' ', $_GET['text']);

if ('start-combat' === $args[0]) {
    if ($player['name'] !== 'Gamemaster') {
        $response->attachments[] = [
            'color' => 'danger',
            'title' => 'Not Gamemaster',
            'text' => 'Players can\'t start combat through Slack.',
        ];
        echo $response;
        exit();
    }
    if ($redis->get('combat')) {
        $response->attachments[] = [
            'color' => 'danger',
            'title' => 'Combat already started',
            'text' => 'You\'ve already requested to start combat. Would you ' .
                'like to end combat?',
        ];
        echo $response;
        exit();
    }

    $redis->set('combat', 1);
    foreach ($players as $playerInfo) {
        $redis->set(sprintf('initiative.%s', $playerInfo['name']), null);
    }

    // Add any goons!
    error_log('adding goons: ' . $args[1]);
    if (isset($args[1]) && file_exists($args[1] . '.php')) {
        $redis->set('combat.enemies', $args[1]);
        $enemies = require $args[1] . '.php';
        error_log(print_r($enemies, true));
        foreach ($enemies as $key => $enemy) {
            $initiative = $enemy['initiative']['base'];
            for ($i = 0; $i < $enemy['initiative']['dice']; $i++) {
                $initiative += roll6();
            }
            $redis->set(sprintf('initiative.%s', $key), $initiative);
        }
    }
    $response->toChannel = true;
    $response->attachments[] = [
        'color' => 'warning',
        'title' => 'Combat Started!',
        'text' => 'Everyone needs to roll initiative! (Type `/roll init`).',
    ];
    echo $response;
    exit();
}

if ('next' === $args[0]) {
    if ($player['name'] !== 'Gamemaster') {
        $response->attachments[] = [
            'color' => 'danger',
            'title' => 'Not Gamemaster',
            'text' => 'Players can\'t end initiative gathering or a turn.',
        ];
        echo $response;
        exit();
    }
    if (!$redis->get('combat')) {
        $response->attachments[] = [
            'color' => 'danger',
            'title' => 'Combat already started',
            'text' => 'You don\'t seem to be in combat. Would you like to ' .
                '`/roll start-combat`?',
        ];
        echo $response;
        exit();
    }
    if (2 != $redis->get('combat')) {
        // Close gathering initiative.
        $redis->set('combat', 2);
        $initiative = [];
        foreach ($players as $playerInfo) {
            $init = $redis->get(sprintf('initiative.%s', $playerInfo['name']));
            if (!$init) {
                continue;
            }
            $initiative[$playerInfo['name']] = $init;
        }
        if ($redis->get('combat.enemies')) {
            $enemies = require $args[1] . '.php';
            foreach ($enemies as $key => $enemy) {
                $initiative[$enemy['name']] = $redis->get(sprintf('initiative.%s', $key));
            }
        }
        arsort($initiative);
        $text = '';
        foreach ($initiative as $name => $init) {
            $text .= sprintf("*%s*: %d\n", $name, $init);
        }
        $response->toChannel = true;
        $response->attachments[] = [
            'color' => 'good',
            'title' => 'Starting Initiative Order',
            'text' => $text,
        ];
        echo $response;
        exit();
    }

    $initiative = [];
    foreach ($players as $playerInfo) {
        $init = $redis->get(sprintf('initiative.%s', $playerInfo['name']));
        if (!$init) {
            continue;
        }
        $init -= 10;
        if ($init <= 0) {
            continue;
        }
        $redis->set(sprintf('initiative.%s', $playerInfo['name']), $init);
        $initiative[$playerInfo['name']] = $init;
    }
    if ($redis->get('combat.enemies')) {
        $enemies = require $args[1] . '.php';
        foreach ($enemies as $key => $enemy) {
            $initiative[$enemy['name']] = $redis->get(sprintf('initiative.%s', $key));
        }
    }
    if (empty($initiative)) {
        // Clear initiative, request more initiative roll.
        $redis->set('combat', 1);
        foreach ($players as $playerInfo) {
            $redis->set(sprintf('initiative.%s', $playerInfo['name']), null);
        }
        $response->toChannel = true;
        $response->attachments[] = [
            'color' => 'warning',
            'title' => 'Next Combat Pass',
            'text' => 'Everyone needs to roll initiative! (Type `/roll init`).',
        ];
        echo $response;
        exit();
    }
    arsort($initiative);
    $text = '';
    foreach ($initiative as $name => $init) {
        $text .= sprintf("*%s*: %d\n", $name, $init);
    }
    $response->toChannel = true;
    $response->attachments[] = [
        'color' => 'good',
        'title' => 'Next Combat Pass Order',
        'text' => $text,
    ];
    echo $response;
    exit();
}

if ('end-combat' === $args[0]) {
    if ($player['name'] !== 'Gamemaster') {
        $response->attachments[] = [
            'color' => 'danger',
            'title' => 'Not Gamemaster',
            'text' => 'Players can\'t start combat through Slack.',
        ];
        echo $response;
        exit();
    }
    if (!$redis->get('combat')) {
        $response->attachments[] = [
            'color' => 'danger',
            'title' => 'No Active Combat',
            'text' => 'Not in combat. Want to pick a fight?',
        ];
        echo $response;
        exit();
    }
    $redis->set('combat.enemies', false);
    $redis->set('combat', false);
    $response->toChannel = true;
    $response->attachments[] = [
        'color' => 'good',
        'title' => 'Combat Ended!',
        'text' => 'Combat is over. Everyone okay?',
    ];
    echo $response;
    exit();
}

if ('init' === $args[0]) {
    if ($player['name'] !== 'Gamemaster'
            && $redis->get(sprintf('initiative.%s', $player['name']))) {
        $response->attachments[] = [
            'color' => 'danger',
            'title' => 'Already rolled',
            'text' => 'You\'ve already rolled initiative.',
        ];
        echo $response;
        exit();
    }
    if ($player['name'] !== 'Gamemaster' && !isset($player['initiative'])) {
        $response->attachments[] = [
            'color' => 'danger',
            'title' => 'No statistics',
            'text' => 'No initiative score for you. Does the GM have your '
                . 'sheet?',
        ];
        echo $response;
        exit();
    }
    if ($player['name'] !== 'Gamemaster') {
        $base = $player['initiative']['base'];
        $dice = $player['initiative']['dice'];
        $rolls = [];
        $initiative = $base;
        for ($i = 0; $i < $dice; $i++) {
            $roll = roll6();
            $rolls[] = $roll;
            $initiative += $roll;
        }
        $redis->set(sprintf('initiative.%s', $player['name']), $initiative);
        $response->attachments[] = [
            'color' => '#439FE0',
            'title' => 'Initiative',
            'text' => sprintf(
                'Your initiative is *%d*.',
                $initiative
            ),
            'footer' => sprintf(
                '%d+%dd6: %s',
                $base,
                $dice,
                implode(' ', $rolls)
            ),
        ];
        echo $response;
        exit();
    }
    $text = '';
    foreach ($players as $key => $playerInfo) {
        if ('Gamemaster' === $playerInfo['name']) {
            continue;
        }
        $text .= sprintf(
            "*%s*: %d\n",
            $playerInfo['name'],
            $redis->get(sprintf('initiative.%s', $playerInfo['name']))
        );
    }
    if ($redis->get('combat.enemies')) {
        $enemies = require $redis->get('combat.enemies') . '.php';
        foreach ($enemies as $key => $enemy) {
            $text .= sprintf(
                "*%s*: %d\n",
                $enemy['name'],
                $redis->get(sprintf('initiative.%s', $key))
            );
        }
    }
    $response->text = $text;
    echo $response;
    exit();
}

if ('help' === $args[0]) {
    $text = '*Everyone*' . PHP_EOL
        . '  `init` - Roll your initiative' . PHP_EOL
        . '  `6 [text]` - Roll 6 dice, with optional text (automatics, '
        . 'perception, etc)' . PHP_EOL
        . '  `12 6 [text]` - Roll 12 dice with a limit of 6' . PHP_EOL
        . '  `push 15 [text]` - Pre-edge, roll dice pool + edge, with exploding 6\'s'
        . PHP_EOL
        . PHP_EOL
        . '*Gamemaster Only*' . PHP_EOL
        . '  `start-combat filename` - Ask everyone to roll initiative'
        . PHP_EOL
        . '  `end-combat` - Remove initiatives' . PHP_EOL
        . '  `next` - Move to next combat pass';
    $response->attachments[] = [
        'title' => 'RollBot allows you to roll Shadowrun dice.',
        'text' => $text,
    ];
    echo $response;
    exit();
}

if ('push' === $args[0]) {
    array_shift($args);
    if (!is_numeric($args[0])) {
        $response->attachments[] = [
            'color' => 'danger',
            'title' => 'Bad Request',
            'text' => 'The number of dice to roll needs to be a number.',
        ];
        echo $response;
        exit();
    }

    $dice = $text = array_shift($args);

    $text = 'Push the limit: ' . $text;

    if (isset($args[0])) {
        $text .= ' - ' . implode(' ', $args);
    }

    $rolls = [];
    $successes = 0;
    $fails = 0;
    $explosions = 0;

    for ($i = 0; $i < $dice; $i++) {
        $roll = roll6();
        $rolls[] = $roll;
        if (5 <= $roll) {
            $successes++;
            if (6 == $roll) {
                $explosions++;
                $dice++;
            }
        } elseif (1 == $roll) {
            $fails++;
        }
    }

    $glitch = false;
    if ($fails >= floor($dice / 2)) {
        $glitch = true;
    }

    rsort($rolls);
    array_walk($rolls, function(&$value, $key) {
        if ($value >= 5) {
            $value = sprintf('*%d*', $value);
        } elseif ($value == 1) {
            $value = sprintf('~%d~', $value);
        }
    });
    if ($glitch && !$successes) {
        $response->attachments[] = [
            'color' => 'danger',
            'title' => 'Critical Glitch!',
            'text' => sprintf(
                '%s rolled %d ones with no successes!',
                $player['name'],
                $fails
            ),
            'footer' => implode(' ', $rolls),
        ];
        $response->toChannel = true;
        echo $response;
        exit();
    }

    $title = sprintf('%s rolled %d successes', $player['name'], $successes);
    $color = 'good';
    if ($glitch) {
        $color = 'warning';
        $title .= ', glitched';
    } elseif (0 === $successes) {
        $color = 'danger';
    }
    $response->attachments[] = [
        'color' => $color,
        'title' => $title,
        'text' => $text,
        'footer' => implode(' ', $rolls)
            . sprintf(' (%d 6\'s exploded)', $explosions),
    ];
    $response->toChannel = true;
    echo $response;
    exit();
}

if (!is_numeric($args[0])) {
    $response->attachments[] = [
        'color' => 'danger',
        'title' => 'Bad Request',
        'text' => 'The number of dice to roll needs to be a number.',
    ];
    echo $response;
    exit();
}

// Roll normally.
$dice = $text = array_shift($args);
$limit = false;
if (isset($args[0]) && is_numeric($args[0])) {
    $limit = array_shift($args);
    $text .= sprintf(' [%d]', $limit);
}
if (isset($args[0])) {
    $text .= ' - ' . implode(' ', $args);
}

$rolls = [];
$successes = 0;
$fails = 0;

for ($i = 0; $i < $dice; $i++) {
    $roll = roll6();
    $rolls[] = $roll;
    if (5 <= $roll) {
        $successes++;
    }
    if (1 == $roll) {
        $fails++;
    }
}

$glitch = false;
if ($fails >= floor($dice / 2)) {
    $glitch = true;
}

rsort($rolls);
array_walk($rolls, function(&$value, $key) {
    if ($value >= 5) {
        $value = sprintf('*%d*', $value);
    } elseif ($value == 1) {
        $value = sprintf('~%d~', $value);
    }
});
if ($glitch && !$successes) {
    $response->attachments[] = [
        'color' => 'danger',
        'title' => 'Critical Glitch!',
        'text' => sprintf(
            '%s rolled %d ones with no successes!',
            $player['name'],
            $fails
        ),
        'footer' => implode(' ', $rolls),
    ];
    $response->toChannel = true;
    echo $response;
    exit();
}

if ($limit && $limit < $successes) {
    $title = sprintf(
        '%s rolled %d successes, hit limit',
        $player['name'],
        $limit
    );
} else {
    $title = sprintf('%s rolled %d successes', $player['name'], $successes);
}
$color = 'good';
if ($glitch) {
    $color = 'warning';
    $title .= ', glitched';
} elseif (0 === $successes) {
    $color = 'danger';
}
$response->attachments[] = [
    'color' => $color,
    'title' => $title,
    'text' => $text,
    'footer' => implode(' ', $rolls),
];
$response->toChannel = true;
echo $response;
