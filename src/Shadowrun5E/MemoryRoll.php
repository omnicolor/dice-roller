<?php

declare(strict_types=1);
namespace RollBot\Shadowrun5E;

use Commlink\Character;

/**
 * Roll an attribute-only test for the character.
 */
class MemoryRoll extends Number
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
