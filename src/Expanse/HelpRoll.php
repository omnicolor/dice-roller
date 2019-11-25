<?php

declare(strict_types=1);

namespace RollBot\Expanse;

use RollBot\Response;

/**
 * Handle a user asking for help.
 */
class HelpRoll
{
    /**
     * Return help formatted for Slack.
     */
    public function __toString(): string
    {
        $response = new Response();
        $response->text = 'RollBot allows you to roll Expanse dice';
        $response->attachments[] = [
            'text' => '`help` - Show help' . PHP_EOL
                . '`4 [text]` - Roll 3d6 dice adding 4 to the result, with '
                . 'optional text (automatics, perception, etc)' . PHP_EOL,
        ];
        return (string)$response;
    }
}
