<?php

declare(strict_types=1);
namespace RollBot\Shadowrun5E;

use Commlink\Character;
use RollBot\GuzzleClientInterface;
use RollBot\GuzzleClientTrait;
use RollBot\MongoClientInterface;
use RollBot\MongoClientTrait;
use RollBot\RedisClientInterface;
use RollBot\RedisClientTrait;
use RollBot\Response;

/**
 * Handle a character making an addiction test.
 */
class AddictionRoll
    implements GuzzleClientInterface, MongoClientInterface, RedisClientInterface
{
    use GuzzleClientTrait;
    use MongoClientTrait;
    use RedisClientTrait;

    const PSYCHOLOGICAL = 1;
    const PHYSIOLOGICAL = 2;
    const BOTH = 3;

    /**
     * Current character.
     * @var \Commlink\Character
     */
    protected $character;

    /**
     * True if all of the questions have been asked.
     * @var boolean|null
     */
    protected $done;

    /**
     * The drug the character took.
     * @var array|null
     */
    protected $drug;

    /**
     * Collection of all drugs.
     * @var array
     */
    protected $drugs = [
        'alcohol' => [
            'id' => 'alcohol',
            'name' => 'Alcohol',
            'rating' => 3,
            'threshold' => 2,
            'type' => self::PHYSIOLOGICAL,
        ],
        'bliss' => [
            'id' => 'bliss',
            'name' => 'Bliss',
            'rating' => 5,
            'threshold' => 3,
            'type' => self::BOTH,
        ],
        'cram' => [
            'id' => 'cram',
            'name' => 'Cram',
            'rating' => 4,
            'threshold' => 3,
            'type' => self::PSYCHOLOGICAL,
        ],
        'deepweek' => [
            'id' => 'deepweed',
            'name' => 'Deepweed',
            'rating' => 4,
            'threshold' => 2,
            'type' => self::PHYSIOLOGICAL,
        ],
        'jazz' => [
            'id' => 'jazz',
            'name' => 'Jazz',
            'rating' => 8,
            'threshold' => 3,
            'type' => self::BOTH,
        ],
        'kamikaze' => [
            'id' => 'kamikaze',
            'name' => 'Kamikaze',
            'rating' => 9,
            'threshold' => 3,
            'type' => self::PHYSIOLOGICAL,
        ],
        'long-haul' => [
            'id' => 'long-haul',
            'name' => 'Long Haul',
            'rating' => 2,
            'threshold' => 1,
            'type' => self::PSYCHOLOGICAL,
        ],
        'nitro' => [
            'id' => 'nitro',
            'name' => 'Nitro',
            'rating' => 9,
            'threshold' => 3,
            'type' => self::BOTH,
        ],
        'novacoke' => [
            'id' => 'novacoke',
            'name' => 'Novacoke',
            'rating' => 7,
            'threshold' => 2,
            'type' => self::BOTH,
        ],
        'psyche' => [
            'id' => 'psyche',
            'name' => 'Psyche',
            'rating' => 6,
            'threshold' => 2,
            'type' => self::PSYCHOLOGICAL,
        ],
        'zen' => [
            'id' => 'zen',
            'name' => 'Zen',
            'rating' => 3,
            'threshold' => 1,
            'type' => self::PSYCHOLOGICAL,
        ],
        /*
        '' => [
            'id' => '',
            'name' => '',
            'rating' => ,
            'threshold' => ,
            'type' => self::,
        ],
        */
    ];

    /**
     * What type of drug we're rolling for.
     * @var integer|null
     */
    protected $type;

    /**
     * Number of weeks since the drug has been used.
     * @var integer
     */
    protected $weeks;

    /**
     * Constructor.
     * @param Character $character
     * @param array $args
     */
    public function __construct(Character $character, array $args)
    {
        $this->character = $character;

        if (1 === count($args) && false === strpos($args[0], '|')) {
            // First time through the addiction questions, nothing else to do.
            return;
        }

        if (1 === count($args)) {
            // We've got an attempt to actually roll some dice!
            list($drug, $this->weeks, $this->type, $this->done) =
                explode('|', $args[0]);
            $this->drug = $this->drugs[$drug];
            return;
        }

        if (isset($args['actions'][0]['selected_options'][0]['value'])) {
            list($drug, $this->weeks) = explode(
                '|',
                $args['actions'][0]['selected_options'][0]['value']
            );
            $this->drug = $this->drugs[$drug];
        }
    }

    /**
     * Return the addiction test as a Slack message.
     * @return string
     */
    public function __toString(): string
    {
        if (isset($this->type)) {
            return (string)$this->rollTest();
        }
        if (isset($this->weeks)) {
            return (string)$this->promptForTest();
        }
        if (isset($this->drug)) {
            return (string)$this->promptForTime();
        }
        return (string)$this->promptForDrug();
    }

    /**
     * Roll an addiction test for either a pyschological or physiological drug.
     * @return Response
     */
    protected function rollTest(): Response
    {
        $dice = $this->character->getWillpower();
        if ($this->type == 'psy') {
            $dice += $this->character->getLogic();
            $type = 'psychological';
        } else {
            $dice += $this->character->getBody();
            $type = 'physiological';
        }

        $title = sprintf(
            '%s addition test versus threshold %d (%s)',
            $this->drug['name'],
            $this->drug['threshold'] - $this->weeks,
            $type
        );
        $args = [$dice, $title];
        $roll = new Number($this->character, $args);
        $roll->setMongoClient($this->mongo);
        $roll->setRedisClient($this->redis);
        $roll = json_decode((string)$roll);

        $search = [
            '_id' => new \MongoDB\BSON\ObjectId($this->character->campaignId),
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

        if ($this->drug['type'] !== self::BOTH) {
            // The drug only requires one test, get rid of the original message.
            $response = new Response();
            $response->text = '';
            $response->replaceOriginal = true;
            $response->deleteOriginal = true;
            return $response;
        }

        if ($this->done) {
            // The drug required both tests, but we're done.
            $response = new Response();
            $response->text = '';
            $response->replaceOriginal = true;
            $response->deleteOriginal = true;
            return $response;
        }

        // The drug requires both types of tests. Change the message.
        $roll = new Response();
        $attachment = [
            'callback_id' => $this->character->handle,
            'text' => 'Roll the other addiction test.',
            'actions' => [],
        ];
        if ($type === 'psychological') {
            $attachment['actions'][] = [
                'name' => 'addiction',
                'text' => 'Psychological',
                'type' => 'button',
                'value' => sprintf(
                    'addiction %s|%d|psy|done',
                    $this->drug['id'],
                    $this->weeks
                ),
            ];
        } else {
            $attachment['actions'][] = [
                'name' => 'addiction',
                'text' => 'Physiological',
                'type' => 'button',
                'value' => sprintf(
                    'addiction %s|%d|phys|done',
                    $this->drug['id'],
                    $this->weeks
                ),
            ];
        }
        $roll->attachments[] = $attachment;
        return $roll;
    }

    /**
     * We know the drug and the number of weeks, so we can ask them to roll for
     * it.
     * @return Response
     */
    protected function promptForTest(): Response
    {
        $drug = $this->drug;
        $threshold = $drug['threshold'] - (int)$this->weeks;
        if ($threshold <= 0) {
            $roll = new Response();
            $roll->attachments[] = [
                'color' => 'success',
                'title' => sprintf(
                    '%s avoided addiction!',
                    $this->character->handle
                ),
                'text' => sprintf(
                    'Enough time has passed since they used that they don\'t ' .
                    'need to make a roll to avoid addiction to %s.',
                    $drug['name']
                ),
            ];
        } else {
            $roll = new Response();
            $attachment = [
                'callback_id' => $this->character->handle,
                'text' => sprintf(
                    'Roll your addiction test%s.',
                    $drug['type'] === self::BOTH ? 's' : ''
                ),
                'actions' => [],
            ];
            if ($drug['type'] & self::PSYCHOLOGICAL) {
                $attachment['actions'][] = [
                    'name' => 'addiction',
                    'text' => 'Psychological',
                    'type' => 'button',
                    'value' => sprintf(
                        'addiction %s|%d|psy',
                        $drug['id'],
                        $this->weeks
                    ),
                ];
            }
            if ($drug['type'] & self::PHYSIOLOGICAL) {
                $attachment['actions'][] = [
                    'name' => 'addiction',
                    'text' => 'Physiological',
                    'type' => 'button',
                    'value' => sprintf(
                        'addiction %s|%d|phys',
                        $drug['id'],
                        $this->weeks
                    ),
                ];
            }
            $roll->attachments[] = $attachment;
        }

        // We can't change an ephemeral Slack message to a public one, so we
        // need to send a new one to the Slack web hook URL to let everyone see
        // the results.
        $search = [
            '_id' => new \MongoDB\BSON\ObjectId($this->character->campaignId),
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
     * We know what the character took, now give them options for how long it's
     * been since they've indulged.
     * @return Response
     */
    protected function promptForTime(): Response
    {
        $weeks = 11 - $this->drug['rating'];
        $threshold = $this->drug['threshold'];
        $options = [
            [
                'text' => 'I just took some more',
                'value' => sprintf(
                    '%s|%d',
                    $this->drug['id'],
                    0
                ),
            ]
        ];
        for ($i = 1; $i < min($weeks, $threshold); $i++) {
            $options[] = [
                'text' => sprintf(
                    '%d week%s',
                    $i,
                    $i === 1 ? '' : 's'
                ),
                'value' => sprintf(
                    '%s|%d',
                    $this->drug['id'],
                    $i
                ),
            ];
        }
        if ($weeks > $threshold) {
            $options[] = [
                'text' => sprintf('%d or more weeks', $threshold),
                'value' => sprintf(
                    '%s|%d',
                    $this->drug['id'],
                    $threshold
                ),
            ];
        }

        $response = new Response();
        $attachment = [
            'callback_id' => $this->character->handle,
            'text' => sprintf(
                'For %s, you need to roll an addiction test every %d weeks. '
                . 'How long has it been since you\'ve used?',
                $this->drug['name'],
                11 - $this->drug['rating']
            ),
            'actions' => [
                [
                    'name' => 'addiction',
                    'text' => 'How long...',
                    'type' => 'select',
                    'options' => $options,
                ],
            ],
        ];
        $response->attachments[] = $attachment;
        return $response;

    }

    /**
     * Starting point of an addiction check: give the character drug choices.
     * @return Response
     */
    protected function promptForDrug(): Response
    {
        $response = new Response();
        $options = [];
        foreach ($this->drugs as $drug) {
            $options[] = [
                'text' => $drug['name'],
                'value' => $drug['id'],
            ];
        }
        $attachment = [
            'callback_id' => $this->character->handle,
            'text' => 'What drug did you take?',
            'actions' => [
                [
                    'name' => 'addiction',
                    'text' => 'Pick a drug...',
                    'type' => 'select',
                    'options' => $options,
                ],
            ],
        ];
        $response->attachments[] = $attachment;
        return $response;
    }
}
