<?php

declare(strict_types=1);

namespace RollBot\Shadowrun5E;

use Commlink\Character;

/**
 * Interface for rolls that require a Shadowrun 5E character.
 */
interface CharacterInterface
{
    /**
     * Set the character to be used by the roll.
     * @param \Commlink\Character $character
     * @return CharacterInterface
     */
    public function setCharacter(Character $character): CharacterInterface;
}
