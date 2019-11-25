<?php

declare(strict_types=1);
namespace RollBot\Shadowrun5E;

use Commlink\Character;
use RollBot\MongoClientInterface;
use RollBot\MongoClientTrait;
use RollBot\RedisClientInterface;
use RollBot\RedisClientTrait;
use RollBot\Response;

/**
 * Handle a user trying to roll initiative with Blitz Edge Action.
 */
class BlitzRoll
    implements MongoClientInterface, RedisClientInterface
{
    use MongoClientTrait;
    use RedisClientTrait;

    const UPDATE_MESSAGE = false;

    /**
     * Character's base initiative.
     * @var int
     */
    protected $base = 0;

    /**
     * Campaign ID the character is attached to.
     * @var string
     */
    protected $campaignId;

    /**
     * Character object.
     * @var \Commlink\Character
     */
    protected $character;

    /**
     * Number of dice to roll. 5 because of Blitz.
     * @var int
     */
    protected $dice = 5;

    /**
     * Character's rolled initiative.
     * @var int
     */
    protected $initiative = 0;

    /**
     * Character's handle.
     * @var string
     */
    protected $name;

    /**
     * Build a new initiative roller.
     * @param \Commlink\Character $character
     * @param array $unused
     */
    public function __construct(Character $character, array $unused)
    {
        $this->name = $character->handle;
        $this->character = $character;
        $this->campaignId = $character->campaignId;
        $this->base = $character->getReaction() + $character->getIntuition()
            + $character->getModifiedAttribute('initiative');
    }

    /**
     * Decrement a character's remaining edge.
     * @return Roll
     */
    protected function updateEdge(): Blitz
    {
        $search = ['_id' => new \MongoDB\BSON\ObjectID($this->character->id)];
        $update = [
            '$set' => [
                'edgeCurrent' => $this->character->edgeCurrent - 1,
            ],
        ];
        $this->mongo->shadowrun->characters->updateOne($search, $update);
        return $this;
    }

    /**
     * Roll the character's initiative.
     * @return Roll
     */
    protected function roll(): Blitz
    {
        if (!$this->character->edgeCurrent) {
            throw new \RuntimeException('out');
        }
        $this->initiative = $this->base;
        for ($i = 0; $i < $this->dice; $i ++) {
            $roll = random_int(1, 6);
            $this->rolls[] = $roll;
            $this->initiative += $roll;
        }

        $lastRoll = $this->redis->del(
            sprintf(
                'last-roll.%s',
                strtolower(str_replace(' ', '_', $this->name))
            )
        );
        $this->updateEdge();
        return $this;
    }

    /**
     * Return the roll formatted for Slack.
     * @return string
     */
    public function __toString(): string
    {
        $response = new Response();
        $response->toChannel = false;
        $response->replaceOriginal = false;
        $initState = $this->redis->get(
            sprintf('combat.%s', $this->campaignId)
        );
        switch ($initState) {
            case 'collecting':
                // The character is in combat, and the system is collecting
                // initiatives.
                break;
            case 'combat':
                // It looks like combat's already started.
                $response->attachments[] = [
                    'color' => 'danger',
                    'title' => 'Combat in progress',
                    'text' => 'Everyone has already rolled initiative and '
                    . 'combat has begun. Assuming you and at least one '
                    . 'opponent survive, you\'ll get another chance then.',
                ];
                return (string)$response;
            case '':
                // The campaign combat flag is not set.
                $response->attachments[] = [
                    'color' => 'danger',
                    'title' => 'Not in combat',
                    'text' => 'You do not appear to be in combat.',
                ];
                return (string)$response;
        }
        $key = sprintf('combatants.%s', $this->campaignId);
        $combatants = $this->redis->get($key);
        if (!$combatants) {
            $response->attachments[] = [
                'color' => 'danger',
                'title' => 'Not in combat',
                'text' => 'You do not appear to be in combat.',
            ];
            return (string)$response;
        }
        $combatants = json_decode($combatants);
        foreach ($combatants as $index => $value) {
            if ($value->name == $this->name) {
                break;
            }
        }
        if ($value->initiative) {
            $response->attachments[] = [
                'color' => 'danger',
                'title' => 'Already rolled',
                'text' => sprintf(
                    'You\'ve already rolled initiative (%d).',
                    $value->initiative
                ),
            ];
            return (string)$response;
        }

        try {
            $this->roll();
        } catch (\RuntimeException $e) {
            $response->attachments[] = [
                'color' => 'danger',
                'title' => 'No More Edge',
                'text' => 'Tough luck chummer, you\'re out of edge. You\'ll '
                . 'have to roll initiative normally.',
            ];
            return (string)$response;
        }
        $combatants[$index]->initiative = $this->initiative;
        $this->redis->set($key, json_encode($combatants));
        $response->attachments[] = [
            'color' => '#439FE0',
            'title' => 'Initiative',
            'text' => sprintf(
                'Your initiative is *%d*.',
                $this->initiative
            ),
            'footer' => sprintf(
                '%d+%dd6: %s, %d edge left',
                $this->base,
                $this->dice,
                implode(' ', $this->rolls),
                $this->character->edgeCurrent - 1
            ),
        ];
        return (string)$response;
    }
}
