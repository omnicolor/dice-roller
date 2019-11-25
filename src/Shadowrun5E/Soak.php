<?php
/**
 * Roll a character's soak dice.
 */

declare(strict_types=1);
namespace RollBot;

use Commlink\Character;

/**
 * Roll soak.
 */
class Soak extends Roll
{
    /**
     * Constructor.
     * @param Character $character
     * @param array $unused
     */
    public function __construct(Character $character, array $args)
    {
        $ap = $args[1] ?? 0;
        $args = [
            $character->getSoak() + $ap,
            sprintf('Soak (AP %d)', $ap)
        ];
        parent::__construct($character, $args);
    }
}
