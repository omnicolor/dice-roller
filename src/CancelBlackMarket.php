<?php
/**
 * Character wants to cancel their Black Market roll.
 */

declare(strict_types=1);
namespace RollBot;

use Commlink\Character;

/***
 * Handle a user trying to cancel a Black Market negotiation they've tried to
 * start.
 */
class CancelBlackMarket
    implements MongoClientInterface
{
    use MongoClientTrait;

    /**
     * Character that's trying to cancel the rolls.
     * @var \Commlink\Character
     */
    protected $character;

    /**
     * Build a new object to cancel a black market attempt.
     * @param \Commlink\Character $character
     * @param array $unused
     */
    public function __construct(Character $character, array $unused)
    {
        $this->character = $character;
    }

    /**
     * Update Mongo to remove the first unrolled black market entry.
     * @return CancelBlackMarket
     * @throws \RuntimeException
     */
    protected function cancelBlackMarket(): CancelBlackMarket
    {
        $search = ['_id' => new \MongoDB\BSON\ObjectID($this->character->id)];
        $blackMarkets = $this->mongo->shadowrun->characters->findOne($search);
        $blackMarkets = $blackMarkets['blackMarket'];
        if (0 === count($blackMarkets)) {
            throw new \RuntimeException('No black market searches found');
        }
        $cancel = false;
        foreach ($blackMarkets as $key => $market) {
            if (isset($market['rolled'])) {
                continue;
            }
            $cancel = $key;
            break;
        }
        if (false === $cancel) {
            throw new \RuntimeException('All black market searches have been started');
        }
        $update = [
            '$pull' => [
                'blackMarket' => $blackMarkets[$key],
            ],
        ];
        $this->mongo->shadowrun->characters->updateOne($search, $update);
        return $this;
    }

    /**
     * Return this "roll" formatted for Slack.
     * @return string
     */
    public function __toString(): string
    {
        $response = new Response();
        $response->toChannel = true;
        try {
        $this->cancelBlackMarket();
        } catch (\RuntimeException $e) {
            $response->attachments[] = [
                'color' => 'danger',
                'title' => 'Black Market Search Cancelled',
                'text' => sprintf(
                    '%s tried to cancel their black market search, but had an '
                    . 'error: %s',
                    $this->character->handle,
                    $e->getMessage()
                ),
            ];
            return (string)$response;
        }
        $response->attachments[] = [
            'title' => 'Black Market Search Cancelled',
            'text' => sprintf(
                '%s cancelled their black market search.',
                $this->character->handle
            ),
        ];
        return (string)$response;
    }
}
