<?php

declare(strict_types=1);
namespace RollBot\Shadowrun5E;

use Commlink\Character;
use Commlink\Spell;
use RollBot\GuzzleClientInterface;
use RollBot\GuzzleClientTrait;
use RollBot\MongoClientInterface;
use RollBot\MongoClientTrait;
use RollBot\RedisClientInterface;
use RollBot\RedisClientTrait;
use RollBot\Response;

/**
 * Handle the character wanting to cast a spell.
 */
class CastRoll
    implements GuzzleClientInterface, MongoClientInterface, RedisClientInterface
{
    use GuzzleClientTrait;
    use MongoClientTrait;
    use RedisClientTrait;

    const UPDATE_MESSAGE = false;

    /**
     * Current character
     * @var \Commlink\Character
     */
    protected $character;

    /**
     * Force the character is trying to cast the spell at.
     * @var int
     */
    protected $force;

    /**
     * Spell that the character is trying to cast.
     * @var \Commlink\Spell
     */
    protected $spell;

    /**
     * Arguments for making a roll.
     * @var array
     */
    protected $roll;

    /**
     * Create a new Cast command.
     * @param \Commlink\Character $character
     * @param array $args
     */
    public function __construct(Character $character, array $args)
    {
        $this->character = $character;

        if (isset($args['type'])
            && !strpos(
                $args['actions'][0]['selected_options'][0]['value'],
                '|'
            )) {
            // At least past the first stage.
            $this->spell = new Spell($args['actions'][0]['selected_options'][0]['value']);
        } elseif (isset($args['type'])) {
            // Past the second stage.
            [$this->force, $spellId] = explode(
                '|',
                $args['actions'][0]['selected_options'][0]['value']
            );
            $this->spell = new Spell($spellId);
        } elseif (count($args) === 1 && strpos($args[0], '|')) {
            $this->roll = $args[0];
        }
    }

    /**
     * Return the cast as a Slack message.
     * @return string
     */
    public function __toString(): string
    {
        if (!$this->character->magic) {
            return (string)$this->noMagic();
        }
        if ($this->roll) {
            return (string)$this->rollSpellcasting();
        }
        if (!$this->spell) {
            return (string)$this->chooseSpell();
        }
        if (!$this->force) {
            return (string)$this->chooseForce();
        }
        return (string)$this->promptSpellcasting();
    }

    /**
     * The character has chosen the spell and the force, have them roll their
     * spellcasting test.
     * @return Response
     */
    protected function promptSpellcasting(): Response
    {
        $response = new Response();
        $attachment = [
            'callback_id' => $this->character->handle,
            'text' => 'Roll your Spellcasting test.',
            'actions' => [
                [
                    'name' => 'cast',
                    'text' => 'Normal',
                    'type' => 'button',
                    'value' => sprintf(
                        'cast %d|%s|roll',
                        $this->force,
                        $this->spell->id
                    ),
                ],
                [
                    'name' => 'cast',
                    'text' => 'Reckless',
                    'type' => 'button',
                    'value' => sprintf(
                        'cast %d|%s|reckless',
                        $this->force,
                        $this->spell->id
                    ),
                ],
                [
                    'name' => 'cast',
                    'text' => 'Push the Limit',
                    'type' => 'button',
                    'value' => sprintf(
                        'cast %d|%s|push',
                        $this->force,
                        $this->spell->id
                    ),
                ],
            ],
        ];
        $response->attachments[] = $attachment;
        return $response;
    }

    /**
     * The user has clicked a button cast the spell.
     *
     * They either clicked a button to roll normally or use edge to Push the
     * Limit.
     * @return Response
     */
    protected function rollSpellcasting(): Response
    {
        $dice = $this->character->skills['spellcasting']->level
            + $this->character->magic;
        [$force, $spellId, $type] = explode('|', $this->roll);
        $spell = new Spell($spellId);
        $args = [
            $dice,
            $force,
            sprintf('Casting %s at force %d', $spell->name, $force),
        ];
        if ('push' === $type) {
            array_unshift($args, 'push');
        }

        // Should either build a normal Roll command or a Push command.
        $type = 'RollBot\\' . ucfirst($type);
        $roll = new $type($this->character, $args);
        $roll->setMongoClient($this->mongo)
            ->setRedisClient($this->redis);
        $roll = json_decode((string)$roll);

        if (!isset($roll->attachments[0]->actions)) {
            // If the character is out of edge, there won't already be a button,
            // and thus no actions array.
            $roll->attachments[0]->actions = [];
        }
        $roll->attachments[0]->actions[] = [
            'name' => 'drain',
            'text' => 'Resist Drain',
            'type' => 'button',
            'value' => sprintf(
                '%s|%d|%d',
                $spellId,
                $force,
                false
            ),
        ];

        // We can't change an ephemeral Slack message to a public one, so we
        // need to send a new one to the Slack web hook URL to let everyone see
        // the results.
        $search = [
            '_id' => new \MongoDB\BSON\ObjectID($this->character->campaignId),
        ];
        $campaign = $this->mongo->shadowrun->campaigns->findOne($search);
        $slackHook = $campaign['slack-hook'];
        $this->guzzle->request(
            'POST',
            $slackHook,
            [
                'body' => json_encode($roll),
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
            ]
        );

        // Finally, delete the ephemeral message to clean up the channel.
        $response = new Response();
        $response->text = '';
        $response->replaceOriginal = true;
        $response->deleteOriginal = true;
        return $response;
    }

    /**
     * Respond to the user that their character doesn't have magic, so they
     * can't cast a spell.
     * @return Response
     */
    protected function noMagic(): Response
    {
        $response = new Response();
        $response->attachments[] = [
            'color' => 'danger',
            'title' => 'Not Available',
            'text' => 'Only spellcasters can cast spells.',
        ];
        return $response;
    }

    /**
     * The character has spells, allow them to choose.
     * @return Response
     */
    protected function chooseSpell(): Response
    {
        $response = new Response();
        $options = [];
        foreach ($this->character->magics['spells'] as $spell) {
            $options[] = [
                'text' => $spell->name,
                'value' => $spell->id,
            ];
        }
        $attachment = [
            'callback_id' => $this->character->handle,
            'text' => 'What spell would you like to cast?',
            'actions' => [
                [
                    'name' => 'cast',
                    'text' => 'Pick a spell...',
                    'type' => 'select',
                    'options' => $options,
                ],
            ],
        ];
        $response->attachments[] = $attachment;
        return $response;
    }

    /**
     * The character has chosen to cast a specific spell, have them choose the
     * force.
     * @return Response
     */
    protected function chooseForce(): Response
    {
        $response = new Response();
        $options = [];
        for ($i = 1; $i <= $this->character->magic * 2; $i++) {
            $options[] = [
                'text' => sprintf('Force %d', $i),
                'value' => sprintf(
                    '%d|%s',
                    $i,
                    $this->spell->id
                ),
            ];
        }

        $attachment = [
            'callback_id' => $this->character->handle,
            'text' => sprintf(
                'What force would you like to cast %s at?',
                (string)$this->spell
            ),
            'actions' => [
                [
                    'name' => 'cast',
                    'text' => 'Pick a force...',
                    'type' => 'select',
                    'options' => $options,
                ],
            ],
        ];
        $response->attachments[] = $attachment;
        return $response;
    }
}
