<?php

declare(strict_types=1);

namespace RollBot\Shadowrun5E;

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
        $response->text = 'RollBot allows you to roll Shadowrun 5E dice';
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
            . '`stats` - Show my character\'s stat block' . PHP_EOL
            . '`addiction` - Start a dialog for avoiding addiction',
        ];
        return (string)$response;
    }
}
