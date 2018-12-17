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
class Push
    extends Roll
    implements MongoClientInterface, RedisClientInterface
{
    use MongoClientTrait;
    use RedisClientTrait;

    /**
     * Character
     * @var \Commlink\Character
     */
    protected $character;

    /**
     * Number of sixes that exploded.
     * @var int
     */
    protected $explosions = 0;

    /**
     * Build a new Push the Limit Shadowrun roll.
     * @param \Commlink\Character $character
     * @param array $args
     */
    public function __construct(Character $character, array $args)
    {
        $this->character = $character;

        // Get rid of the command name and use the parent constructor.
        array_shift($args);
        parent::__construct($character, $args);
    }

    /**
     * Decrement a character's remaining edge.
     * @return Roll
     */
    protected function updateEdge(): Roll
    {
        $search = ['_id' => new \MongoDB\BSON\ObjectID($this->character->id)];
        $update = [
            '$set' => [
                'edgeCurrent' => $this->character->edgeCurrent,
            ],
        ];
        $this->mongo->shadowrun->characters->updateOne($search, $update);
        return $this;
    }

    /**
     * Roll dice.
     * @return Roll
     * @throws \RuntimeException if the character is out of edge
     */
    protected function roll(): Roll
    {
        if (!$this->character->edgeCurrent) {
            throw new \RuntimeException();
        }

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

        // Second Chance can't be used if you've already used edge on a test.
        $this->redis->del(
            sprintf(
                'last-roll.%s',
                strtolower(str_replace(' ', '_', $this->name))
            )
        );
        $this->character->edgeCurrent--;
        $this->updateEdge();
        return $this;
    }

    /**
     * Return the roll as a Slack message.
     * @return string
     */
    public function __toString(): string
    {
        $response = new Response();
        try {
            $this->roll()
                ->prettifyRolls();
        } catch (\RuntimeException $e) {
            $response->attachments[] = [
                'color' => 'danger',
                'title' => 'Out of Edge',
                'text' => 'You can\'t Push the Limit, you\'re out of Edge!',
            ];
            return (string)$response;
        }
        $response->toChannel = true;
        $footer = sprintf(
            '%s with %d exploded sixes, %d edge left',
            implode(' ', $this->rolls),
            $this->explosions,
            $this->character->edgeCurrent
        );

        if ($this->criticalGlitch) {
            $response->attachments[] = [
                'color' => 'danger',
                'title' => 'Critical Glitch!',
                'text' => sprintf(
                    '%s rolled %d ones with no successes!',
                    $this->name,
                    $this->fails
                ),
                'footer' => $footer,
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
            'footer' => $footer,
        ];
        return (string)$response;
    }
}
