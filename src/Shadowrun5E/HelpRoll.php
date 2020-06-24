<?php

declare(strict_types=1);

namespace RollBot\Shadowrun5E;

use RollBot\DiscordInterface;
use RollBot\Response;

/**
 * Handle a user asking for help.
 */
class HelpRoll implements DiscordInterface
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

    /**
     * Return the response formatted for Discord.
     * @return string
     */
    public function getDiscordResponse(): string
    {
        return 'RollBot is a Slack/Discord bot that lets you roll dice for '
            . 'various RPG systems. This channel is registered as Shadowrun '
            . '5E.' . PHP_EOL . PHP_EOL
            . 'Supported rolls:' . PHP_EOL
            . '• `help` - Show help' . PHP_EOL
            . '• `6 [text]` - Roll 6 dice, with optional text (automatics, '
            . 'perception, etc)' . PHP_EOL
            . '• `12 6 [text]` - Roll 12 dice with a limit of 6' . PHP_EOL
            . '• `XdY[+-M] [T]` - Roll X dice with Y pips, adding or '
            . 'subtracting M from the total, with optional T text' . PHP_EOL
            . '• `composure` - Roll your character\' composure (WIL+CHR)'
            . PHP_EOL
            . '• `judge` - Roll judge intentions (INT+CHR)' . PHP_EOL
            . '• `memory` - Roll memory (LOG+WIL)' . PHP_EOL
            . '• `lifting` - Roll lifting/carrying (STR+BOD)' . PHP_EOL
            . '• `stats` - DM your character\'s stats' . PHP_EOL
            . '• `luck` - Roll your edge stat';
    }

    /**
     * Set the Discord message.
     * @param \CharlotteDunois\Yasmin\Models\Message $message
     * @return DiscordInterface
     */
    public function setMessage(
        \CharlotteDunois\Yasmin\Models\Message $message
    ): DiscordInterface {
        return $this;
    }

    /**
     * Return whether the response should be in a DM.
     * @return bool
     */
    public function shouldDM(): bool
    {
        return false;
    }
}
