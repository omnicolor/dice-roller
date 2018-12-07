<?php
/**
 * Second chance: Re-roll failures.
 */

declare(strict_types=1);
namespace RollBot;

use Commlink\Character;

/**
 * Handle a user requesting to use the Second Chance edge effect.
 *
 * Re-rolls any non-successes from the previous roll, but does not remove
 * glitches. Sixes do not explode and limits still count.
 */
class Second extends Roll implements MongoClientInterface, RedisClientInterface
{
    use MongoClientTrait;
    use RedisClientTrait;

    /**
     * Character
     * @var \Commlink\Character
     */
    protected $character;

    /**
     * Set up a Second Change roll.
     * @param \Commlink\Character $character
     * @param array $args
     */
    public function __construct(Character $character, array $args) {
        $this->character = $character;
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
     * Load the last roll from Redis, then re-roll non-successes.
     * @throws \LogicException If trying to second chance a critical glitch
     * @throws \RuntimeException If trying to second chance without edge or a last roll
     * @return Roll
     */
    protected function roll(): Roll
    {
        if (!$this->character->edgeCurrent) {
            throw new \RuntimeException('out');
        }
        $lastRoll = $this->redis->get(
            sprintf(
                'last-roll.%s',
                strtolower(str_replace(' ', '_', $this->name))
            )
        );
        if (!$lastRoll) {
            throw new \RuntimeException('no');
        }
        $lastRoll = json_decode($lastRoll);
        if ($lastRoll->criticalGlitch) {
            throw new \LogicException();
        }
        $this->dice = $lastRoll->dice;
        $this->successes = $lastRoll->successes;
        $this->limit = $lastRoll->limit;
        $this->text = $lastRoll->text;
        $this->glitch = (bool)$lastRoll->glitch;

        // Only save the successes in the rolls array.
        $this->rolls = array_filter($lastRoll->rolls, function ($v) {
            return $v >= 5;
        });

        // Only re-roll non-successes.
        $dice = $this->dice - $this->successes;
        for ($i = 0; $i < $dice; $i++) {
            $roll = random_int(1, 6);
            $this->rolls[] = $roll;
            if (5 <= $roll) {
                $this->successes++;
            }
            if (1 == $roll) {
                $this->fails++;
            }
        }

        rsort($this->rolls);

        // See if it was a glitch.
        if ($this->fails > 0 && $this->fails >= floor($this->dice / 2)) {
            $this->glitch = true;
            if (!$this->successes) {
                $this->criticalGlitch = true;
            }
        }
        $lastRoll = $this->redis->del(
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
            if ($e->getMessage() == 'no') {
                $response->attachments[] = [
                    'color' => 'danger',
                    'title' => 'No Last Roll',
                    'text' => 'You asked to use the Second Chance edge effect, '
                    . 'but we don\'t have a last roll for you. This may be '
                    . 'because the last roll used edge.',
                ];
            } else {
                $response->attachments[] = [
                    'color' => 'danger',
                    'title' => 'No More Edge',
                    'text' => 'Tough luck chummer, you\'re out of edge.',
                ];
            }
            return (string)$response;
        } catch (\LogicException $e) {
            // Second chance can not be used to fix a Critical Glitch.
            $response->attachments[] = [
                'color' => 'danger',
                'title' => 'Critical Glitch',
                'text' => 'Second Chance can not be used to fix a critical '
                . 'glitch.',
            ];
            return (string)$response;
        }
        $response->toChannel = true;
        $footer = sprintf(
            '%s, %d edge left',
            implode(' ', $this->rolls),
            $this->character->edgeCurrent
        );

        if ($this->criticalGlitch) {
            $response->attachments[] = [
                'color' => 'danger',
                'title' => 'Critical Glitch!',
                'text' => sprintf(
                    '%s rolled %d ones with no successes on Second Chance!',
                    $this->name,
                    $this->fails
                ),
                'footer' => $footer,
            ];
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
            $title .= ', still glitched';
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
