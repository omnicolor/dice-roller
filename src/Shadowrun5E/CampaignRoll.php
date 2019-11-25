<?php

declare(strict_types=1);
namespace RollBot\Shadowrun5E;

use Commlink\Character;
use RollBot\MongoClientInterface;
use RollBot\MongoClientTrait;
use RollBot\Response;

/**
 * Handle the user wanting information about the campaign.
 */
class CampaignRoll implements MongoClientInterface
{
    use MongoClientTrait;

    /**
     * Campaign ID.
     * @var string
     */
    protected $campaignId;

    /**
     * Character's handle.
     * @var string
     */
    protected $handle;

    /**
     * Build a new Campaign information object
     * @param \Commlink\Character $character
     */
    public function __construct(Character $character)
    {
        $this->campaignId = $character->campaignId;
        $this->handle = $character->handle;
    }

    /**
     * Return the information as a Slack message.
     * return string
     */
    public function __toString(): string
    {
        $search = [
            '_id' => new \MongoDB\BSON\ObjectId($this->campaignId),
        ];
        $campaign = $this->mongo->shadowrun->campaigns->findOne($search);
        error_log(print_r($campaign, true));
        $response = new Response();
        $response->attachments[] = [
            'title' => $campaign->name,
            'fields' => [
                [
                    'title' => 'Date',
                    'value' => date(
                        'l, F jS Y',
                        strtotime(
                            $campaign['current-date'] ?? $campaign['start-date']
                        )
                    ),
                    'short' => true,
                ],
                [
                    'title' => 'Handle',
                    'value' => $this->handle,
                    'short' => true,
                ],
                [
                    'title' => 'Notes',
                    'value' => $campaign['notes'],
                    'short' => false,
                ],
            ],
        ];
        return (string)$response;
    }
}
