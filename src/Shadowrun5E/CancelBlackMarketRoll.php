<?php

declare(strict_types=1);

namespace RollBot\Shadowrun5E;

use Commlink\Character;
use RollBot\MongoClientInterface;
use RollBot\MongoClientTrait;
use RollBot\Response;

/***
 * Handle a user trying to cancel a Black Market negotiation they've tried to
 * start.
 */
class CancelBlackMarketRoll implements MongoClientInterface
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
     */
    public function __construct(Character $character)
    {
        $this->character = $character;
    }

    /**
     * Update Mongo to remove the first unrolled black market entry.
     * @return CancelBlackMarketRoll
     * @throws \RuntimeException
     */
    protected function cancelBlackMarket(): CancelBlackMarketRoll
    {
        $search = ['_id' => new \MongoDB\BSON\ObjectId($this->character->id)];
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
            throw new \RuntimeException(
                'All black market searches have been started'
            );
        }
        $update = [
            '$pull' => [
                'blackMarket' => $blackMarkets[$cancel],
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
