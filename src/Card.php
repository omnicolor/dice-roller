<?php

declare(strict_types=1);
namespace RollBot;

use Commlink\Character;

/**
 * Handle the user trying to draw a single random card from a deck.
 */
class Card
{
    /**
     * Character's name.
     * @var string
     */
    protected $name;

    /**
     * Build a new card drawer.
     * @param \Commlink\Character $character
     */
    public function __construct(Character $character)
    {
        $this->name = $character->handle;
    }

    /**
     * Convert a number 0 to 3 into a standard card suit.
     * @param int $suit
     * @return string
     */
    protected function convertSuit(int $suit): string
    {
        $suits = [
            'Spades',
            'Hearts',
            'Diamonds',
            'Clubs',
        ];
        return $suits[$suit];
    }

    /**
     * Convert a card value into a valid card (2-10, J, Q, K, A).
     * @param int $value
     * @return string
     */
    protected function convertCard(int $value): string
    {
        if ($value <= 10) {
            return (string)$value;
        }
        $faceCards = [
            'Jack',
            'Queen',
            'King',
            'Ace',
        ];
        return $faceCards[$value - 11];
    }

    /**
     * Return the roll formatted for Slack.
     * @return string
     */
    public function __toString(): string
    {
        $suit = random_int(0, 3);
        $value = random_int(2, 14);
        $response = new Response();
        $response->attachments[] = [
            'color' => '#439FE0',
            'title' => 'Pick a Card',
            'text' => sprintf(
                '%s drew the %s of %s.',
                $this->name,
                $this->convertCard($value),
                $this->convertSuit($suit)
            ),
        ];
        $response->toChannel = true;
        return (string)$response;
    }
}
