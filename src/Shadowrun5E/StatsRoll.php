<?php

declare(strict_types=1);

namespace RollBot\Shadowrun5E;

use Commlink\Character;
use RollBot\Response;

/**
 * Handle the user wanting to see information about their character.
 */
class StatsRoll
{
    /**
     * Character.
     * @var \Commlink\Character
     */
    protected $character;

    /**
     * Build a new Stats object.
     * @param \Commlink\Character $character
     */
    public function __construct(Character $character)
    {
        $this->character = $character;
    }

    /**
     * Return stats.
     * @return string
     */
    public function __toString(): string
    {
        $response = new Response();
        if ($this->character->handle == 'GM') {
            $response->attachments[] = [
                'title' => 'The World',
                'text' => 'You\'re the GM... What else can we say?',
            ];
            return (string)$response;
        }
        $response->text = $this->character->handle;
        $attachment = [
            'fields' => [],
        ];
        $attributes = [
            'body', 'agility', 'reaction', 'strength', 'willpower', 'logic',
            'intuition', 'charisma', 'magic', 'resonance',
        ];
        foreach ($attributes as $attribute) {
            $value = $this->character->getModifiedAttribute($attribute);
            if (!$value) {
                continue;
            }
            if ($this->character->$attribute != $value) {
                $value = sprintf(
                    '(%d) %d',
                    $this->character->$attribute,
                    $value
                );
            }
            $attachment['fields'][] = [
                'title' => ucfirst($attribute),
                'value' => $value,
                'short' => true,
            ];
        }
        $attachment['fields'][] = [
            'title' => 'Edge',
            'value' => sprintf(
                '%d / %d',
                $this->character->edgeCurrent,
                $this->character->edge
            ),
            'short' => true,
        ];
        $attachment['fields'][] = [
            'title' => 'Initiative',
            'value' => $this->character->getInitiative(),
            'short' => true,
        ];
        $attachment['fields'][] = [
            'title' => 'Physical Damage',
            'value' => sprintf(
                '%d / %d',
                $this->character->damagePhysical ?? 0,
                $this->character->getPhysicalMonitor()
            ),
            'short' => true,
        ];
        $attachment['fields'][] = [
            'title' => 'Stun Damage',
            'value' => sprintf(
                '%d / %d',
                $this->character->damageStun ?? 0,
                $this->character->getStunMonitor()
            ),
            'short' => true,
        ];
        $attachment['fields'][] = [
            'title' => 'Overflow',
            'value' => sprintf(
                '%d / %d',
                $this->character->damageOverflow,
                $this->character->getOverflowMonitor()
            ),
            'short' => true,
        ];
        $attachment['fields'][] = [
            'title' => 'Physical Limit',
            'value' => $this->character->getPhysicalLimit(),
            'short' => true,
        ];
        $attachment['fields'][] = [
            'title' => 'Mental Limit',
            'value' => $this->character->getMentalLimit(),
            'short' => true,
        ];
        $attachment['fields'][] = [
            'title' => 'Social Limit',
            'value' => $this->character->getSocialLimit(),
            'short' => true,
        ];
        if ($this->character->getAstralLimit()) {
            $attachment['fields'][] = [
                'title' => 'Astral Limit',
                'value' => $this->character->getAstralLimit(),
                'short' => true,
            ];
        }

        $response->attachments[] = $attachment;
        return (string)$response;
    }
}
