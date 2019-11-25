<?php

declare(strict_types=1);

namespace RollBot\Expanse;

use RollBot\Response;

/**
 * Handle a user rolling a generic roll.
 */
class Number
{
    /**
     * @var string[]
     */
    protected $args;

    /**
     * @var ?\RollBot\Expanse\Character
     */
    protected $character;

    /**
     * Set up a new instance of the help command.
     * @param array $args
     */
    public function __construct(array $args, ?Character $character = null)
    {
        $this->args = $args;
        $this->character = $character;
    }

    /**
     * Figure out how many (if any) stunt points a roll generated.
     * @param int[] $dice Dice rolls
     * @return int
     */
    protected function getStuntPoints(array $dice): int
    {
        $values = array_count_values($dice);
        if (count($values) === 3) {
            // No doubles, no stunt points.
            return 0;
        }
        return $dice[2];
    }

    /**
     * Return the roll formatted for Slack.
     * @return string
     */
    public function __toString(): string
    {
        $dice = [
            random_int(1, 6),
            random_int(1, 6),
            random_int(1, 6),
        ];
        $ability = (int)$this->args[0];
        $result = array_sum($dice) + $ability;
        $stuntPoints = $this->getStuntPoints($dice);
        if ($stuntPoints) {
            $result = sprintf('%d (%d SP)', $result, $stuntPoints);
        }
        $name = 'User';
        if ($this->character) {
            $name = $this->character->name;
        }

        $for = '';
        if (1 < count($this->args)) {
            $for = ' for ' . implode(' ', array_slice($this->args, 1));
        }

        $response = new Response();
        $response->text = sprintf('%s made a roll%s', $name, $for);
        $response->attachments[] = [
            'text' => $result,
            'footer' => sprintf('%d %d `%d`', ...$dice),
        ];
        $response->toChannel = true;
        return (string)$response;
    }
}
