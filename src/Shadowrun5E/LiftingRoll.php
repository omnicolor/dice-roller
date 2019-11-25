<?php

declare(strict_types=1);
namespace RollBot\Shadowrun5E;

use Commlink\Character;
use RollBot\Response;

/**
 * Roll an attribute-only test for the character.
 */
class LiftingRoll extends Number
{
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
