<?php

declare(strict_types=1);

namespace RollBot\Shadowrun5E;

use Commlink\Character;
use RollBot\Response;

/**
 * Roll an attribute-only test for the character.
 */
class JudgeRoll extends Number
{
    /**
     * Build a judge intentions test.
     * @param Character $character
     */
    public function __construct(Character $character)
    {
        $args = [
            $character->getCharisma() + $character->getIntuition(),
            'Judge Intentions Test',
        ];
        parent::__construct($character, $args);
    }
}
