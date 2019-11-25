<?php

declare(strict_types=1);
namespace RollBot\Shadowrun5E;

use Commlink\Character;
use Commlink\Spell;
use RollBot\Response;

/**
 * Handle a character wanting to resist drain.
 */
class DrainRoll
{
    /**
     * Character object.
     * @var \Commlink\Character $character
     */
    protected $character;

    /**
     * Number of dice to roll.
     * @var int
     */
    protected $dice;

    /**
     * Force the spell was cast at.
     * @var int
     */
    protected $force;

    /**
     * Number of hits (apply limit or edge effects).
     * @var int
     */
    protected $hits;

    /**
     * Whether the spell was cast recklessly.
     * @var bool
     */
    protected $reckless;

    /**
     * Result of rolling the number of dice.
     * @var int[]
     */
    protected $rolls = [];

    /**
     * Spell that was cast.
     * @var \Commlink\Spell
     */
    protected $spell;

    /**
     * Number of successes rolled.
     * @var int
     */
    protected $successes = 0;

    /**
     * Build a Drain command.
     * @param \Commlink\Character $character
     * @param array $args
     */
    public function __construct(Character $character, array $args)
    {
        $this->character = $character;

        // Get rid of the command name.
        array_shift($args);

        if (count($args) < 3) {
            throw new \Exception(
                'Not enough arguments: '
                . '`/roll drain {spellId} {force} {hits} {reckless?}`'
            );
        }

        list($spellId, $this->force, $this->hits, $this->reckless) = $args;

        $this->reckless = (bool)$this->reckless;
        $this->spell = new Spell($spellId);
    }

    /**
     * Roll the required number of dice, tracking successes.
     * @return DrainRoll
     */
    protected function roll(): DrainRoll
    {
        // Roll the dice, keeping track of successes.
        for ($i = 0; $i < $this->dice; $i++) {
            $roll = random_int(1, 6);
            $this->rolls[] = $roll;
            if (5 <= $roll) {
                $this->successes++;
            }
        }
        rsort($this->rolls);
        return $this;
    }

    /**
     * Bold successes in the roll list.
     * @return DrainRoll
     */
    protected function prettifyRolls(): DrainRoll
    {
        array_walk($this->rolls, function(&$value, $key) {
            if ($value >= 5) {
                $value = sprintf('*%d*', $value);
            }
        });
        return $this;
    }

    /**
     * Calculate how much drain needs to be resisted based on the force and
     * drain code for the spell, along with whether the spell was recklessly
     * cast.
     * @return int
     */
    protected function getDrain(): int
    {
        $appliedDrain = str_replace(
            'F',
            (string)$this->force,
            $this->spell->drain
        );
        $appliedDrain = str_split($appliedDrain);
        if (1 === count($appliedDrain)) {
            $appliedDrain = (int)$appliedDrain[0];
        } elseif ('-' === $appliedDrain[1]) {
            $appliedDrain = (int)$appliedDrain[0] - (int)$appliedDrain[2];
        } else {
            $appliedDrain = (int)$appliedDrain[0] + (int)$appliedDrain[2];
        }

        // The minimum drain is two.
        $appliedDrain = max(2, $appliedDrain);

        // Reckless casting adds three.
        if ($this->reckless) {
            $appliedDrain += 3;
        }
        return $appliedDrain;
    }

    /**
     * Determine the number of dice to roll for drain resistance based on the
     * character's tradition.
     * @return int
     */
    protected function getDice(): int
    {
        $attributes = $this->character->magics['tradition']
            ->getDrainAttributes();
        $attributes = array_map('strtolower', $attributes);
        return $this->character->getModifiedAttribute($attributes[0]) +
            $this->character->getModifiedAttribute($attributes[1]);
    }

    /**
     * Return the drain resistance results formatted for Slack.
     * @return string
     */
    public function __toString(): string
    {
        $damageType = 'S';
        if ($this->hits > $this->character->magic) {
            $damageType = 'P';
        }
        $appliedDrain = $this->getDrain();
        $this->dice = $this->getDice();
        $this->roll()->prettifyRolls();

        $response = new Response();
        $response->text = sprintf(
            '%s is resisting %s drain at force %d',
            $this->character->handle,
            $this->spell->drain,
            $this->force
        );
        $response->attachments[] = [
            'fields' => [
                [
                    'title' => 'Spell',
                    'value' => $this->spell->name,
                    'short' => true,
                ],
                [
                    'title' => 'Drain code',
                    'value' => sprintf(
                        '%s%s = %d',
                        $this->spell->drain,
                        $this->reckless ? '+3' : '',
                        $appliedDrain
                    ),
                    'short' => true,
                ],
                [
                    'title' => 'Resist with',
                    'value' => $this->character->magics['tradition']->drain,
                    'short' => true,
                ],
                [
                    'title' => 'Damage type',
                    'value' => $damageType,
                    'short' => true,
                ],
                [
                    'title' => 'Reckless',
                    'value' => $this->reckless ? 'true' : 'false',
                    'short' => true,
                ],
                [
                    'title' => 'Damage',
                    'value' => sprintf(
                        '%d%s',
                        max($appliedDrain - $this->successes, 0),
                        $damageType
                    ),
                    'short' => true,
                ],
            ],
            'footer' => implode(' ', $this->rolls),
        ];
        $response->toChannel = true;
        return (string)$response;
    }
}
