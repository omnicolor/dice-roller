<?php

declare(strict_types=1);
namespace RollBot\Shadowrun5E;

use Commlink\Character;

/**
 * Roll an attribute-only test for the character.
 */
class LuckRoll extends Number
{
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
