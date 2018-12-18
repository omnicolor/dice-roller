<?php
/**
 * Show information about the Campaign attached to the channel.
 */

declare(strict_types=1);
namespace RollBot;

use Commlink\Character;

/**
 * Handle the user wanting information about the campaign.
 */
class Campaign implements MongoClientInterface
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
     * @param array $unused
     */
    public function __construct(Character $character, array $unused)
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
            '_id' => new \MongoDB\BSON\ObjectID($this->campaignId),
        ];
        $campaign = $this->mongo->shadowrun->campaigns->findOne($search);
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
