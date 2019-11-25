<?php

declare(strict_types=1);

namespace RollBot\Shadowrun5E;

use Commlink\Character;
use RollBot\MongoClientInterface;
use RollBot\MongoClientTrait;
use RollBot\Response;

/**
 * Handle the character trying to roll negotiation.
 */
class NegotiateBlackMarketRoll implements MongoClientInterface
{
    use MongoClientTrait;

    /**
     * Character that's trying to negotiate on the black market.
     * @var \Commlink\Character
     */
    protected $character;

    /**
     * Array of individual dice rolls.
     * @var string[]|int[]
     */
    protected $rolls = [];

    /**
     * Number of successes rolled.
     * @var int
     */
    protected $successes = 0;

    /**
     * Number of failures rolled.
     * @var int
     */
    protected $fails = 0;

    /**
     * Whether the roll was a glitch.
     * @var bool
     */
    protected $glitch = false;

    /**
     * Whether the roll was a critical glitch.
     * @var bool
     */
    protected $criticalGlitch = false;

    /**
     * Number of successes the availability roll made.
     * @var int
     */
    protected $availSuccesses = 0;

    /**
     * Rolls that the availability test rolls.
     * @var string[]|int[]
     */
    protected $availRolls = [];

    /**
     * Search ID if we're trying to negotiate a specific one.
     * @var ?int
     */
    protected $searchId;

    /**
     * Build a new object to try to procure stuff on the black market.
     * @param \Commlink\Character $character
     * @param array $args
     */
    public function __construct(Character $character, array $args)
    {
        $this->character = $character;
        if (isset($args[1])) {
            $this->searchId = (int)$args[1];
        }
    }

    /**
     * Return the current date in the campaign.
     * @return \DateTimeImmutable
     */
    protected function getCurrentDate($campaignId): \DateTimeImmutable
    {
        $search = [
            '_id' => new \MongoDB\BSON\ObjectId($campaignId),
        ];
        $campaign = $this->mongo->shadowrun->campaigns->findOne($search);
        $date = $campaign['current-date'] ?? $campaign['start-date'];
        return new \DateTimeImmutable($date);
    }

    /**
     * Try to load the first non-rolled black market from Mongo.
     * @return \MongoDB\Model\BSONDocument
     * @throws \RuntimeException
     */
    protected function findBlackMarket(): \MongoDB\Model\BSONDocument
    {
        $currentDate = $this->getCurrentDate($this->character->campaignId);
        $search = ['_id' => new \MongoDB\BSON\ObjectId($this->character->id)];
        $blackMarkets = $this->mongo->shadowrun->characters->findOne($search);
        $blackMarkets = $blackMarkets['blackMarket'];
        if (0 === count($blackMarkets)) {
            throw new \RuntimeException('No black market search found');
        }
        if (null !== $this->searchId) {
            // Trying to negotiate a specific black market search.
            if (!isset($blackMarkets[$this->searchId])) {
                throw new \RuntimeException(
                    'Requested black market search not found'
                );
            }
            $search = $blackMarkets[$this->searchId];
            if (isset($search['deliverOn'])) {
                throw new \RuntimeException(
                    sprintf(
                        'The search has already been rolled, and will be '
                        . 'delivered on %s',
                        $search['deliverOn']
                    )
                );
            }
            if (isset($search['retryAfter'])) {
                // Only allow a new roll after the retry period if they failed
                // before.
                $retry = new \DateTimeImmutable($search['retryAfter']);
                $diff = $currentDate->diff($retry);
                if (!$diff->invert && $diff->days > 0) {
                    throw new \RuntimeException(
                        'It\'s too soon to retry your search'
                    );
                }
            }
            $search['id'] = $this->searchId;
            return $blackMarkets[$this->searchId];
        }
        $found = false;
        foreach ($blackMarkets as $key => $market) {
            if (isset($market['retryAfter']) || isset($market['deliverOn'])) {
                continue;
            }
            $found = $key;
            break;
        }
        if (false === $found) {
            throw new \RuntimeException(
                'All black market searches have already been rolled'
            );
        }
        $blackMarkets[$found]['id'] = $found;
        return $blackMarkets[$found];
    }

    protected function updateBlackMarket(\MongoDB\Model\BSONDocument $market)
    {
        $id = $market['id'];
        unset($market['id']);
    }

    /**
     * Return the number of dice the character can roll for negotiation.
     * @return int
     */
    protected function getNegotiation(): int
    {
        $negotiation = $this->character->skills['negotiation'] ?? 0;
        if ($negotiation) {
            // Character has the negotiation skill, use that.
            $negotiation = $negotiation->level;
        } else {
            // The character doesn't have negotiation. See if they have the influence
            // skill group.
            foreach ($this->character->skillGroups as $group) {
                if ('influence' !== $group->id) {
                    continue;
                }
                $negotiation = $group->level;
                break;
            }
        }
        if (0 === $negotiation) {
            // The character doesn't have negotiation skill or influence group
            // so they have to default.
            $negotiation = -1;
        }
        return $negotiation;
    }

    /**
     * Roll the dice, keeping track of things.
     * @param int $dice
     * @param int $avail
     * @return NegotiateBlackMarketRoll
     */
    protected function roll(int $dice, int $avail): NegotiateBlackMarketRoll
    {
        // Roll the dice, keeping track of successes and failures.
        for ($i = 0; $i < $dice; $i++) {
            $roll = random_int(1, 6);
            $this->rolls[] = $roll;
            if (5 <= $roll) {
                $this->successes++;
            }
            if (1 == $roll) {
                $this->fails++;
            }
        }

        // See if it was a glitch.
        if ($this->fails > 0 && $this->fails >= floor($dice / 2)) {
            $this->glitch = true;
            if (!$this->successes) {
                $this->criticalGlitch = true;
            }
        }
        rsort($this->rolls);

        // Now roll the opposition dice.
        for ($i = 0; $i < $avail; $i++) {
            $roll = random_int(1, 6);
            $this->availRolls[] = $roll;
            if (5 <= $roll) {
                $this->availSuccesses++;
            }
        }
        rsort($this->availRolls);

        return $this;
    }

    /**
     * Bold successes, strike out failures in the roll list.
     * @param string[]|int[] $rolls
     * @return string[]|int[]
     */
    protected function prettifyRolls(array $rolls): array
    {
        array_walk($rolls, function (&$value, $key) {
            if ($value >= 5) {
                $value = sprintf('*%d*', $value);
            } elseif ($value == 1) {
                $value = sprintf('~%d~', $value);
            }
        });
        return $rolls;
    }

    /**
     * Return a DateInterval for the amount of time the item should take to
     * deliver.
     * @param int $cost
     * @return \DateInterval
     */
    protected function getDeliveryTime(int $cost): \DateInterval
    {
        if ($cost < 100) {
            return new \DateInterval('P6H');
        }
        if ($cost < 1000) {
            return new \DateInterval('P1D');
        }
        if ($cost < 10000) {
            return new \DateInterval('P2D');
        }
        if ($cost < 100000) {
            return new \DateInterval('P1W');
        }
        return new \DateInterval('P1M');
    }

    /**
     * Based on the start date and how long delivery should take, return the
     * date that delivery will actually happen based on the number of
     * successes.
     * @param \DateTimeImmutable $start
     * @param \DateInterval $interval
     * @param int $successes
     * @return \DateTimeImmutable
     */
    protected function getModifiedDeliveryTime(
        \DateTimeImmutable $start,
        \DateInterval $interval,
        int $successes
    ): \DateTimeImmutable {
        $end = $start->add($interval);
        $int = $end->diff($start);
        $days = (int)($int->days / $successes);
        return $start->add(new \DateInterval(sprintf('P%dD', $days)));
    }

    /**
     * Return the results of the character's roll.
     * @return string
     */
    public function __toString(): string
    {
        $response = new Response();
        try {
            $blackMarket = $this->findBlackMarket();
        } catch (\RuntimeException $e) {
            if (null === $this->searchId) {
                $response->toChannel = true;
            }
            $response->attachments[] = [
                'color' => 'danger',
                'title' => 'Black Market Search',
                'text' => sprintf(
                    'An error occurred trying negotiate your black market '
                    . 'search for %s: %s',
                    $this->character->handle,
                    $e->getMessage()
                ),
            ];
            return (string)$response;
        }
        $response->toChannel = true;

        $negotiation = $this->getNegotiation();
        $perDice = $blackMarket['total'] / 4;
        $grease = $blackMarket['grease'] / $perDice;
        $grease = min($grease, 12);
        $avail = (int)$blackMarket['avail'];
        $dice = $negotiation + $this->character->getCharisma() + $grease;
        $this->roll((int)$dice, $avail);
        $this->rolls = $this->prettifyRolls($this->rolls);

        if ($this->criticalGlitch) {
            $response->attachments[] = [
                'color' => 'danger',
                'title' => 'Black Market Search: Critical Glitch!',
                'text' => sprintf(
                    '%s critical glitched on %d dice for their black market '
                    . 'roll: Charisma (%d), Negotiation (%d), and Grease Dice '
                    . '(%d)',
                    $this->character->handle,
                    $dice,
                    $this->character->getCharisma(),
                    $negotiation,
                    $grease
                ),
                'footer' => implode(' ', $this->rolls),
            ];
            return (string)$response;
        }

        $hitLimit = false;
        $this->availRolls = $this->prettifyRolls($this->availRolls);
        if ($this->successes > $this->character->getSocialLimit()) {
            $hitLimit = true;
            $this->successes = $this->character->getSocialLimit();
        }

        $fields = [];
        $start = new \DateTimeImmutable($blackMarket['date']);
        $fields[] = [
            'title' => 'Start Date',
            'value' => $start->format('Y-m-d'),
            'short' => true,
        ];
        $time = $this->getDeliveryTime($blackMarket['total']);
        $netSuccesses = $this->successes - $this->availSuccesses;
        $title = 'Black Market Search ';
        if (0 === $netSuccesses) {
            // A tie in successes means double the base time for delivery.
            $title .= 'Tied';
            $delivered = $start->add($time)->add($time)->format('Y-m-d');
            $fields[] = [
                'title' => 'Delivery on',
                'value' => $delivered,
                'short' => true,
            ];
            $blackMarket['delivered'] = $delivered;
        } elseif (0 < $netSuccesses) {
            // Net successes divide the base time.
            $title .= 'Succeeded';
            $delivered = $this->getModifiedDeliveryTime(
                $start,
                $time,
                $netSuccesses
            )->format('Y-m-d');
            $fields[] = [
                'title' => 'Delivery on',
                'value' => $delivered,
                'short' => true,
            ];
            $blackMarket['delivered'] = $delivered;
        } else {
            // A failed test means you can try again after double the base time.
            $title .= 'Failed';
            $retry = $start->add($time)->add($time)->format('Y-m-d');
            $fields[] = [
                'title' => 'Try Again After',
                'value' => $retry,
                'short' => true,
            ];
            $blackMarket['retryAfter'] = $retry;
        }
        if ($hitLimit) {
            $title .= ', Hit Limit';
        }
        if ($this->glitch) {
            $title .= ', Glitched';
        }

        $response->attachments[] = [
            'title' => $title,
            'text' => sprintf(
                '%s rolled %d dice for their black market roll: '
                . 'Charisma (%d), Negotiation (%d), and Grease Dice (%d) and '
                . 'got %d net success%s.',
                $this->character->handle,
                $dice,
                $this->character->getCharisma(),
                $negotiation,
                $grease,
                $netSuccesses,
                $netSuccesses == 1 ? '' : 'es'
            ),
            'fields' => $fields,
            'footer' => implode(' ', $this->rolls) . ' vs '
            . implode(' ', $this->availRolls),
        ];

        $this->updateBlackMarket($blackMarket);
        return (string)$response;
    }
}
