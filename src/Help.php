<?php
/**
 * Show RollBot's help.
 */

declare(strict_types=1);
namespace RollBot;

use Commlink\Character;

/**
 * Handle a user asking for help.
 */
class Help
{
    /**
     * Set up a new instance of the help command.
     * @param \Commlink\Character $character
     * @param array $args
     */
    public function __construct(Character $character, array $args)
    {
    }

    /**
     * Return help formatted for Slack.
     */
    public function __toString(): string
    {
        $response = new Response();
        $text = '`help` - Show help' . PHP_EOL
            . '`init` - Roll your initiative' . PHP_EOL
            . '`show` - Show current initiative status' . PHP_EOL
            . '`6 [text]` - Roll 6 dice, with optional text (automatics, '
            . 'perception, etc)' . PHP_EOL
            . '`12 6 [text]` - Roll 12 dice with a limit of 6' . PHP_EOL
            . '`push 15 [6] [text]` - Push the limit, roll dice pool + edge, '
            . 'with exploding 6\'s';
        $response->attachments[] = [
            'title' => 'RollBot allows you to roll Shadowrun dice',
            'text' => $text,
        ];
        return (string)$response;
    }
}
