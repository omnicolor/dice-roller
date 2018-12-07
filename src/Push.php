<?php
/**
 * Roll dice with pre-edge.
 */

declare(strict_types=1);
namespace RollBot;

use Commlink\Character;

/**
 * Handle a user asking to roll some dice with pre-edge.
 *
 * Pushing the limit would add the character's edge to their dice pool, ignore
 * the test's limit, and use the Rule of Six (exploding sixes).
 */
class Push extends Roll
{
    /**
     * Number of sixes that exploded.
     * @var int
     */
    protected $explosions = 0;

    /**
     * Build a new generic Shadowrun roll.
     * @param \Commlink\Character $character
     * @param array $args
     */
    public function __construct(Character $character, array $args)
    {
        // Get rid of the command name and use the parent constructor.
        array_shift($args);
        parent::__construct($character, $args);
    }

    /**
     * Roll dice.
     * @return Roll
     */
    protected function roll(): Roll
    {
        $dice = $this->dice;

        // Roll the dice, keeping track of successes, failures, and exploding
        // sixes.
        for ($i = 0; $i < $dice; $i++) {
            $roll = random_int(1, 6);
            $this->rolls[] = $roll;
            if (6 == $roll) {
                // Explode the six
                $this->explosions++;
                $dice++;
            }
            if (5 <= $roll) {
                $this->successes++;
            }
            if (1 == $roll) {
                $this->fails++;
            }
        }

        // See if it was a glitch.
        if ($this->fails > 0 && $this->fails >= floor($this->dice / 2)) {
            $this->glitch = true;
            if (!$this->successes) {
                $this->criticalGlitch = true;
            }
        }
        rsort($this->rolls);
        return $this;
    }

    /**
     * Return the roll as a Slack message.
     * @return string
     */
    public function __toString(): string
    {
        $this->roll()
            ->prettifyRolls();
        $response = new Response();
        $response->toChannel = true;

        if ($this->criticalGlitch) {
            $response->attachments[] = [
                'color' => 'danger',
                'title' => 'Critical Glitch!',
                'text' => sprintf(
                    '%s rolled %d ones with no successes!',
                    $this->name,
                    $this->fails
                ),
                'footer' => sprintf(
                    '%s with %d exploded sixes',
                    implode(' ', $this->rolls),
                    $this->explosions
                ),
            ];
            return (string)$response;
        }

        if ($this->limit) {
            $text = sprintf('%d ~[%d]~', $this->dice, $this->limit);
        } else {
            $text = $this->dice;
        }

        if ($this->limit && $this->limit < $this->successes) {
            $title = sprintf(
                '%s rolled %d successes, ignored limit',
                $this->name,
                $this->successes
            );
        } else {
            $title = sprintf(
                '%s rolled %d successes',
                $this->name,
                $this->successes
            );
        }
        $color = 'good';
        if ($glitch) {
            $color = 'warning';
            $title .= ', glitched';
        } elseif (0 === $successes) {
            $color = 'danger';
        }
        if ($this->text) {
            $response->text = $this->text;
        }
        $response->attachments[] = [
            'color' => $color,
            'title' => $title,
            'text' => $text,
            'footer' => sprintf(
                '%s with %d exploded sixes',
                implode(' ', $this->rolls),
                $this->explosions
            ),
        ];
        return (string)$response;
    }
}
