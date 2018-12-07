<?php
/**
 * Roll a luck test.
 */

declare(strict_types=1);
namespace RollBot;

use Commlink\Character;

/**
 * Roll an attribute-only test for the character.
 */
class Luck extends Roll implements RedisClientInterface
{
    use RedisClientTrait;

    /**
     * Build a luck test.
     */
    public function __construct(Character $character, array $unused)
    {
        $args = [
            $character->edge,
            'Luck Test',
        ];
        parent::__construct($character, $args);
    }
}
