<?php
/**
 * Show the current combat initiative.
 */

declare(strict_types=1);
namespace RollBot;

use Commlink\Character;

/**
 * Show the current combat initiative.
 */
class Show extends Roll implements RedisClientInterface
{
    use RedisClientTrait;

    /**
     * Campaign ID the character is attached to.
     * @var string
     */
    protected $campaignId;

    /**
     * Build a new initiative show-er.
     * @param \Commlink\Character $character
     * @param array $args
     */
    public function __construct(Character $character, array $args)
    {
        $this->campaignId = $character->campaignId;
    }

    /**
     * Return the current initiative information for Slack.
     * @return string
     */
    public function __toString(): string
    {
        $response = new Response();
        $initState = $this->redis->get(
            sprintf('combat.%s', $this->campaignId)
        );
        if (!$initState) {
            // The campaign combat flag is not set.
            $response->attachments[] = [
                'color' => 'danger',
                'title' => 'Not in combat',
                'text' => 'You do not appear to be in combat.',
            ];
            return (string)$response;
        }
        switch ($initState) {
            case 'collecting':
                $text = 'Waiting for everyone to roll initiative.';
                break;
            case 'combat':
                $text = 'Actively in combat.';
                break;
        }
        $combatants = $this->redis->get(
            sprintf('combatants.%s', $this->campaignId)
        );
        $combatants = json_decode($combatants);
        $fields = [];
        foreach ($combatants as $index => $value) {
            $fields[] = [
                'title' => $value->name,
                'value' => $value->initiative,
                'short' => true,
            ];
        }
        $response->attachments[] = [
            'color' => '#439FE0',
            'title' => 'Initiative',
            'text' => $text,
            'fields' => $fields,
        ];
        return (string)$response;
    }
}
