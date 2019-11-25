<?php

declare(strict_types=1);
namespace RollBot\Shadowrun5E;

use Commlink\Character;
use RollBot\Response;

/**
 * Roll soak.
 */
class SoakRoll extends Number
{
    /**
     * Constructor.
     * @param Character $character
     */
    public function __construct(Character $character)
    {
        $ap = $args[1] ?? 0;
        $args = [
            $character->getSoak() + $ap,
            sprintf('Soak (AP %d)', $ap)
        ];
        parent::__construct($character, $args);
    }
}
