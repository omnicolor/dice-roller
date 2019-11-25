<?php

declare(strict_types=1);
namespace RollBot;

/**
 * Generic dice roller.
 */
class Generic
{
    /**
     * Number of dice to roll.
     * @var int
     */
    protected $dice;

    /**
     * Number of potential pips per die.
     * @var int
     */
    protected $pips;

    /**
     * Remaining text in the command.
     * @var string
     */
    protected $remaining;

    /**
     * User that threw the dice.
     * @var string
     */
    protected $userId;

    /**
     * Constructor.
     * @param string $text
     * @param string $userId
     * @throws \RuntimeException
     */
    public function __construct(string $text, string $userId)
    {
        $this->userId = $userId;

        $matches = [];
        preg_match('/(\d+)d(\d+)(.*)/i', $text, $matches);

        // Remove the pattern that matched.
        array_shift($matches);

        $this->dice = (int)array_shift($matches);
        if ($this->dice > 100) {
            throw new \RuntimeException('LOL. No, just no.');
        }
        $this->pips = (int)array_shift($matches);
        $this->remaining = array_shift($matches);
    }

    /**
     * Return the roll formatted for Slack.
     * @return string
     */
    public function __toString(): string
    {
        $matches = [];
        $add = 0;
        if (preg_match('/^([+-]\d+) ?(.*)?/', $this->remaining, $matches)) {
            if (is_numeric($matches[1])) {
                $add = (int)$matches[1];
                $this->remaining = $matches[2];
            }
        }

        $for = '';
        if ($this->remaining) {
            $for = sprintf(', for "%s"', $this->remaining);
        }

        $adding = '';
        if ($add < 0) {
            $adding = sprintf(' subtracting %d', abs($add));
        } elseif ($add > 0) {
            $adding = sprintf(' adding %d', abs($add));
        }

        $rolls = [];
        for ($i = 0; $i < $this->dice; $i++) {
            $rolls[] = random_int(1, $this->pips);
        }
        $total = array_sum($rolls) + $add;

        $response = new Response();
        $response->text = sprintf(
            '<@%s> rolled %d %d-sided dice%s%s',
            $this->userId,
            $this->dice,
            $this->pips,
            $adding,
            $for
        );
        $response->attachments[] = [
            'text' => $total,
            'footer' => implode(' ', $rolls),
        ];
        $response->toChannel = true;
        return (string)$response;
    }
}
