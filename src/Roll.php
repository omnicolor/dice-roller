<?php
/**
 * Roll dice with optional descriptive text.
 */

declare(strict_types=1);
namespace RollBot;

use Commlink\Character;

/**
 * Handle the character wanting to roll some dice.
 */
class Roll
    implements MongoClientInterface, RedisClientInterface
{
    use MongoClientTrait;
    use RedisClientTrait;

    /**
     * Current character
     * @var \Commlink\Character
     */
    protected $character;

    /**
     * Number of dice to roll.
     * @var int
     */
    protected $dice = 0;

    /**
     * Number of successes to keep.
     * @var ?int
     */
    protected $limit = null;

    /**
     * Name of whoever rolled.
     * @var string
     */
    protected $name = '';

    /**
     * Array of individual dice rolls.
     * @var string[]
     */
    protected $rolls = [];

    /**
     * Number of successes rolled.
     * @var int
     */
    protected $successes = 0;

    /**
     * Number of failures rolled.
     * @var int
     */
    protected $fails = 0;

    /**
     * Whether the roll was a glitch.
     * @var bool
     */
    protected $glitch = false;

    /**
     * Whether the roll was a critical glitch.
     * @var bool
     */
    protected $criticalGlitch = false;

    /**
     * Optional text to include with the roll.
     * @var string
     */
    protected $text;

    /**
     * Build a new generic Shadowrun roll.
     * @param \Commlink\Character $character
     * @param array $args
     */
    public function __construct(Character $character, array $args)
    {
        $this->character = $character;
        $this->name = $character->handle;
        $this->dice = array_shift($args);
        if (isset($args[0]) && is_numeric($args[0])) {
            $this->limit = array_shift($args);
        }
        $this->text = implode(' ', $args);
    }

    /**
     * Roll dice.
     * @return Roll
     */
    protected function roll(): Roll
    {
        // Roll the dice, keeping track of successes and failures.
        for ($i = 0; $i < $this->dice; $i++) {
            $roll = random_int(1, 6);
            $this->rolls[] = $roll;
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
        if ($this->name !== 'GM') {
            // Only non-GMs get to use edge.
            $lastRoll = [
                'dice' => $this->dice,
                'fails' => $this->fails,
                'successes' => $this->successes,
                'limit' => $this->limit,
                'text' => $this->text,
                'rolls' => $this->rolls,
                'criticalGlitch' => $this->criticalGlitch,
                'glitch' => $this->glitch,
            ];
            $this->redis->set(
                sprintf(
                    'last-roll.%s',
                    strtolower(str_replace(' ', '_', $this->name))
                ),
                json_encode($lastRoll)
            );
        }
        return $this;
    }

    /**
     * Bold successes, strike out failures in the roll list.
     * @return Roll
     */
    protected function prettifyRolls(): Roll
    {
        array_walk($this->rolls, function(&$value, $key) {
            if ($value >= 5) {
                $value = sprintf('*%d*', $value);
            } elseif ($value == 1) {
                $value = sprintf('~%d~', $value);
            }
        });
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
        if ($this->criticalGlitch) {
            $response->attachments[] = [
                'color' => 'danger',
                'title' => 'Critical Glitch!',
                'text' => sprintf(
                    '%s rolled %d ones with no successes!',
                    $this->name,
                    $this->fails
                ),
                'footer' => implode(' ', $this->rolls),
            ];
            $response->toChannel = true;
            return (string)$response;
        }

        if ($this->limit) {
            $text = sprintf('%d [%d]', $this->dice, $this->limit);
        } else {
            $text = $this->dice;
        }

        if ($this->limit && $this->limit < $this->successes) {
            $title = sprintf(
                '%s rolled %d successes, hit limit',
                $this->name,
                $this->limit
            );
        } else {
            $title = sprintf(
                '%s rolled %d successes',
                $this->name,
                $this->successes
            );
        }
        $color = 'good';
        if ($this->glitch) {
            $color = 'warning';
            $title .= ', glitched';
        } elseif (0 === $successes) {
            $color = 'danger';
        }
        if ($this->text) {
            $response->text = $this->text;
        }
        $attachment = [
            'callback_id' => $this->name,
            'color' => $color,
            'title' => $title,
            'text' => $text,
            'footer' => implode(' ', $this->rolls),
        ];

        // Non-GM characters that still have some edge get the second change
        // button.
        if ($this->name !== 'GM' && $this->character->edgeCurrent) {
            $attachment['actions'] = [
                [
                    'name' => 'edge',
                    'text' => 'Second Chance',
                    'type' => 'button',
                    'value' => 'second',
                ],
            ];
        }

        $response->attachments[] = $attachment;
        $response->toChannel = true;
        return (string)$response;
    }
}
