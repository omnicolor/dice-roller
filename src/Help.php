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
        $response->text = 'RollBot allows you to roll Shadowrun dice';
        $response->attachments[] = [
            'text' => '`help` - Show help' . PHP_EOL
            . '`6 [text]` - Roll 6 dice, with optional text (automatics, '
            . 'perception, etc)' . PHP_EOL
            . '`12 6 [text]` - Roll 12 dice with a limit of 6' . PHP_EOL,
        ];
        $response->attachments[] = [
            'title' => 'Initiative Rolls',
            'text' => '`init` - Roll your initiative normally' . PHP_EOL
            . '`show` - Show current initiative status' . PHP_EOL
            . '`blitz` - Use Edge to Blitz and roll 5 dice',
        ];
        $response->attachments[] = [
            'title' => 'Combat Rolls',
            'text' => '`soak {AP=0}` - Roll your soak (body, armor, qualities, '
            . 'magic) with optional armor penetration' . PHP_EOL,
        ];
        $response->attachments[] = [
            'title' => 'Magic Rolls',
            'text' => '`cast` - Start dialog to cast a spell' . PHP_EOL
            . '`drain {spellId} {force} {hits} {reckless?}` - Try to '
            . 'resist drain',
        ];
        $response->attachments[] = [
            'title' => 'Attribute-Only Tests',
            'text' => '`composure` - Composure: Roll Charisma + Willpower'
            . PHP_EOL
            . '`lifting` - Lift/Carry: Roll Body + Strength' . PHP_EOL
            . '`judge` - Judge Intentions: Roll Charisma + Intuition' . PHP_EOL
            . '`memory` - Memory: Roll Logic + Willpower' . PHP_EOL
            . '`luck` - Luck: Roll Edge',
        ];
        $response->attachments[] = [
            'title' => 'Edge Effects',
            'text' => '`push 15 [6] [text]` - Push the limit, roll dice pool '
            . '+ edge, with exploding 6\'s, manually add your edge' . PHP_EOL
            . '`second` - Second Chance: Re-roll your last roll\'s failures',
        ];
        $response->attachments[] = [
            'title' => 'Misc Commands',
            'text' => '`campaign` - Return information about the campaign'
            . PHP_EOL
            . '`stats` - Show my character\'s stat block',
        ];
        return (string)$response;
    }
}
