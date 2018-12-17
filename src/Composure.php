<?php
/**
 * Roll a composure test.
 */

declare(strict_types=1);
namespace RollBot;

use Commlink\Character;

/**
 * Roll a composure test for the character.
 */
class Composure
    extends Roll
    implements MongoClientInterface, RedisClientInterface
{
    /**
     * Build a composure test.
     */
    public function __construct(Character $character, array $unused)
    {
        $args = [
            $character->getCharisma() + $character->getWillpower(),
            'Composure Test',
        ];
        parent::__construct($character, $args);
    }
}
