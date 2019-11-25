<?php

declare(strict_types=1);
namespace RollBot\Shadowrun5E;

use Commlink\Character;
use RollBot\MongoClientInterface;
use RollBot\MongoClientTrait;
use RollBot\RedisClientInterface;
use RollBot\RedisClientTrait;

/**
 * Roll a composure test for the character.
 */
class ComposureRoll
    extends Number
    implements MongoClientInterface, RedisClientInterface
{
    use MongoClientTrait;
    use RedisClientTrait;

    /**
     * Build a composure test.
     * @param \Commlink\Character $character
     */
    public function __construct(Character $character)
    {
        $args = [
            $character->getCharisma() + $character->getWillpower(),
            'Composure Test',
        ];
        parent::__construct($character, $args);
    }
}
