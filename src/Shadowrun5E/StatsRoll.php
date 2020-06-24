<?php

declare(strict_types=1);

namespace RollBot\Shadowrun5E;

use Commlink\Character;
use RollBot\DiscordInterface;
use RollBot\Response;

/**
 * Handle the user wanting to see information about their character.
 */
class StatsRoll implements DiscordInterface
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

    /**
     * Return the response formatted for Discord.
     * @return string
     */
    public function getDiscordResponse(): string
    {
        if ($this->character->handle == 'GM') {
            return 'You\'re the GM... What else can we say?';
        }
        $attributes = [
            'body', 'agility', 'reaction', 'strength', 'willpower', 'logic',
            'intuition', 'charisma', 'magic', 'resonance',
        ];
        $response = sprintf('%s\'s Stats', $this->character->handle) . PHP_EOL;
        foreach ($attributes as $attribute) {
            $value = $this->character->getModifiedAttribute($attribute);
            // Ignore zero value attributes (magic, resonance)
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
            $response .= sprintf('• **%s** %s', ucfirst($attribute), $value)
                . PHP_EOL;
        }
        $response .= sprintf(
            '• **Edge**  %d / %d',
            $this->character->edgeCurrent,
            $this->character->edge
        ) . PHP_EOL
            . sprintf('• **Initiative**  %s', $this->character->getInitiative())
            . PHP_EOL
            . sprintf(
                '• **Physical Damage** %d / %d',
                $this->character->damagePhysical ?? 0,
                $this->character->getPhysicalMonitor()
            ) . PHP_EOL
            . sprintf(
                '• **Stun Damage** %d / %d',
                $this->character->damageStun ?? 0,
                $this->character->getStunMonitor()
            ) . PHP_EOL;
        if ($this->character->damageOverflow) {
            $response .= sprintf(
                '• **Overflow** %d / %d',
                $this->character->damageOverflow,
                $this->character->getOverflowMonitor()
            ) . PHP_EOL;
        }
        $response .= sprintf(
            '• **Physical Limit** %d',
            $this->character->getPhysicalLimit()
        ) . PHP_EOL
            . sprintf(
                '• **Mental Limit** %d',
                $this->character->getMentalLimit()
            ) . PHP_EOL
            . sprintf(
                '• **Social Limit** %d',
                $this->character->getSocialLimit()
            );
        if ($this->character->getAstralLimit()) {
            $response .= PHP_EOL . sprintf(
                '• **Astral Limit** %d',
                $this->character->getAstralLimit()
            );
        }

        return $response;
    }

    /**
     * Set the Discord message.
     * @param \CharlotteDunois\Yasmin\Models\Message $message
     * @return DiscordInterface
     */
    public function setMessage(
        \CharlotteDunois\Yasmin\Models\Message $message
    ): DiscordInterface {
        return $this;
    }

    /**
     * Return whether the response should be in a DM.
     * @return bool
     */
    public function shouldDM(): bool
    {
        return true;
    }
}
