<?php
/**
 * Roll a lifting/carrying test.
 */

declare(strict_types=1);
namespace RollBot;

use Commlink\Character;

/**
 * Roll an attribute-only test for the character.
 */
class Lifting extends Roll implements RedisClientInterface
{
    use RedisClientTrait;

    /**
     * Build a lifting/carrying test.
     */
    public function __construct(Character $character, array $unused)
    {
        $args = [
            $character->getBody() + $character->getStrength(),
            'Lifting/Carrying Test',
        ];
        parent::__construct($character, $args);
    }
}
