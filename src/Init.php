<?php
/**
 * Character wants to roll initiative.
 */

declare(strict_types=1);
namespace RollBot;

use Commlink\Character;

/**
 * Handle a user trying to roll initiative.
 */
class Init extends Roll implements RedisClientInterface
{
    use RedisClientTrait;

    /**
     * Character's base initiative.
     * @var int
     */
    protected $base = 0;

    /**
     * Character's rolled initiative.
     * @var int
     */
    protected $initiative = 0;

    /**
     * Campaign ID the character is attached to.
     * @var string
     */
    protected $campaignId;

    /**
     * Build a new initiative roller.
     * @param \Commlink\Character $character
     * @param array $args
     */
    public function __construct(Character $character, array $args)
    {
        $this->name = $character->handle;
        $this->campaignId = $character->campaignId;
        $this->base = $character->getReaction() + $character->getIntuition()
            + $character->getModifiedAttribute('initiative');
        $this->dice = 1 + $character->getModifiedAttribute('initiative-dice');
    }

    /**
     * Roll the character's initiative.
     * @return Roll
     */
    protected function roll(): Roll
    {
        $this->initiative = $this->base;
        for ($i = 0; $i < $this->dice; $i ++) {
            $roll = roll6();
            $this->rolls[] = $roll;
            $this->initiative += $roll;
        }

        return $this;
    }

    /**
     * Return the roll formatted for Slack.
     * @return string
     */
    public function __toString(): string
    {
        $response = new Response();
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

        $this->roll();
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
                '%d+%dd6: %s',
                $this->base,
                $this->dice,
                implode(' ', $this->rolls)
            ),
        ];
        return (string)$response;
    }

    /**
     * Fix a character's handle to work as an ID.
     * @return string
     */
    protected function fixHandle(): string
    {
        return strtolower(str_replace(' ', '_', $this->name));
    }
}