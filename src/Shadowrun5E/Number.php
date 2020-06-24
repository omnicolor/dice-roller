<?php

declare(strict_types=1);

namespace RollBot\Shadowrun5E;

use Commlink\Character;
use RollBot\DiscordInterface;
use RollBot\MongoClientInterface;
use RollBot\MongoClientTrait;
use RollBot\RedisClientInterface;
use RollBot\RedisClientTrait;
use RollBot\Response;

/**
 * Handle the character wanting to roll some dice.
 */
class Number implements DiscordInterface, MongoClientInterface, RedisClientInterface
{
    use MongoClientTrait;
    use RedisClientTrait;

    /**
     * Current character
     * @var \Commlink\Character
     */
    protected Character $character;

    /**
     * Number of dice to roll.
     * @var int
     */
    protected int $dice = 0;

    /**
     * Number of successes to keep.
     * @var ?int
     */
    protected ?int $limit = null;

    /**
     * Name of whoever rolled.
     * @var string
     */
    protected string $name = '';

    /**
     * Array of individual dice rolls.
     * @var array<string|int>
     */
    protected array $rolls = [];

    /**
     * Where the roll was made.
     * @var string
     */
    protected string $rolledOn = '';

    /**
     * Number of successes rolled.
     * @var int
     */
    protected int $successes = 0;

    /**
     * Number of failures rolled.
     * @var int
     */
    protected int $fails = 0;

    /**
     * Whether the roll was a glitch.
     * @var bool
     */
    protected bool $glitch = false;

    /**
     * Whether the roll was a critical glitch.
     * @var bool
     */
    protected bool $criticalGlitch = false;

    /**
     * Optional text to include with the roll.
     * @var string
     */
    protected string $text;

    /**
     * Build a new generic Shadowrun roll.
     * @param \Commlink\Character $character
     * @param array $args
     */
    public function __construct(Character $character, array $args)
    {
        $this->character = $character;
        $this->name = $character->handle;
        $this->dice = (int)array_shift($args);
        if (isset($args[0]) && is_numeric($args[0])) {
            $this->limit = (int)array_shift($args);
        }
        $this->text = implode(' ', $args);
    }

    /**
     * Return the roll as a Slack message.
     * @return string
     */
    public function __toString(): string
    {
        $this->roll('Slack');
        $rolls = $this->prettifyRolls();
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
                'footer' => implode(' ', $rolls),
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
        } elseif (0 === $this->successes) {
            $color = 'danger';
        }
        if ($this->text) {
            $response->text = $this->text;
        }
        $extraFooter = '';
        if ($this->rolledOn !== 'Slack') {
            $extraFooter = sprintf(' via %s', $this->rolledOn);
        }
        $attachment = [
            'callback_id' => $this->name,
            'color' => $color,
            'title' => $title,
            'text' => $text,
            'footer' => implode(' ', $rolls) . $extraFooter,
        ];

        // Non-GM characters that still have some edge get the second change
        // button.
        if (
            $this->rolledOn !== 'Slack'
            && $this->name !== 'GM' && $this->character->edgeCurrent
        ) {
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

    /**
     * Roll dice.
     * @return \RollBot\Shadowrun5E\Number
     */
    protected function roll(string $where): Number
    {
        if ($this->rolls) {
            return $this;
        }
        $this->rolledOn = $where;
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
     * @return array
     */
    protected function prettifyRolls(): array
    {
        $rolls = $this->rolls;
        array_walk($rolls, function (&$value, $key) {
            if ($value >= 5) {
                $value = sprintf('*%d*', $value);
            } elseif ($value == 1) {
                $value = sprintf('~%d~', $value);
            }
        });
        return $rolls;
    }

    /**
     * Bold successes, strike out failures in the roll list.
     * @return array
     */
    protected function prettifyRollsForDiscord(): array
    {
        $rolls = $this->rolls;
        array_walk($rolls, function (&$value, $key) {
            if ($value >= 5) {
                $value = sprintf('**%d**', $value);
            } elseif ($value == 1) {
                $value = sprintf('~~%d~~', $value);
            }
        });
        return $rolls;
    }

    /**
     * Return the response formatted for Discord.
     * @return string
     */
    public function getDiscordResponse(): string
    {
        $this->roll('Discord');
        $rolls = $this->prettifyRollsForDiscord();
        $extraFooter = '';
        if ($this->rolledOn !== 'Discord') {
            $extraFooter = sprintf(' via %s', $this->rolledOn);
        }
        $footer = sprintf(
            '%d%s: %s%s',
            $this->dice,
            $this->limit ? sprintf(' [%d]', $this->limit) : '',
            implode(' ', $rolls),
            $extraFooter
        );
        if ($this->criticalGlitch) {
            return sprintf(
                '**Critical Glitch:** %s rolled %d ones with no successes%s',
                $this->name,
                $this->fails,
                $this->text ? sprintf(' for "%s"', $this->text) : ''
            ) . PHP_EOL . $footer;
        }

        $limited = $this->limit && $this->limit < $this->successes;
        $successes = $limited ? $this->limit : $this->successes;
        return sprintf(
            '%s rolled %d success%s%s%s%s',
            $this->name,
            $limited ? $this->limit : $this->successes,
            $successes !== 1 ? 'es' : '',
            $limited ? ', hit limit' : '',
            $this->glitch ? ', **glitched**' : '',
            $this->text ? sprintf(' for "%s"', $this->text) : ''
        ) . PHP_EOL . $footer;
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
