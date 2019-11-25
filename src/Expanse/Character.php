<?php

declare(strict_types=1);

namespace RollBot\Expanse;

use MongoDB\Client as Mongo;

/**
 * Representation of an Expanse character.
 */
class Character
{
    public $name;
    public $owner;

    public function __construct()
    {
    }

    /**
     * Load a character from Mongo.
     * @param \MongoDB\Client $mongo
     * @param string $campaignId
     * @param string $owner
     * @return Character
     */
    public static function loadFromMongo(
        Mongo $mongo,
        string $campaignId,
        string $owner
    ): Character {
        $search = [
            'type' => 'expanse',
            'campaign' => $campaignId,
            'owner' => $owner,
        ];
        $rawCharacter = $mongo->shadowrun->characters->findOne($search);
        if (!$rawCharacter) {
            throw new \RuntimeException('Character not found');
        }

        $character = new self();
        $character->name = $rawCharacter->name;
        $character->owner = $rawCharacter->owner;
        return $character;
    }
}
