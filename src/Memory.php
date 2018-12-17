<?php
/**
 * Roll a memory test.
 */

declare(strict_types=1);
namespace RollBot;

use Commlink\Character;

/**
 * Roll an attribute-only test for the character.
 */
class Memory
    extends Roll
    implements MongoClientInterface, RedisClientInterface
{
    /**
     * Build a memory test.
     */
    public function __construct(Character $character, array $unused)
    {
        $args = [
            $character->getLogic() + $character->getWillpower(),
            'Memory Test',
        ];
        parent::__construct($character, $args);
    }
}
