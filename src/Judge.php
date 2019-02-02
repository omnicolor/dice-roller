<?php
/**
 * Roll a judge intentions test.
 */

declare(strict_types=1);
namespace RollBot;

use Commlink\Character;

/**
 * Roll an attribute-only test for the character.
 */
class Judge extends Roll
{
    /**
     * Build a judge intentions test.
     * @param Character $character
     * @param array $unused
     */
    public function __construct(Character $character, array $unused)
    {
        $args = [
            $character->getCharisma() + $character->getIntuition(),
            'Judge Intentions Test',
        ];
        parent::__construct($character, $args);
    }
}
