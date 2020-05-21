<?php

declare(strict_types=1);

namespace RollBot;

/**
 * Interface for Discord responses.
 */
interface DiscordInterface
{
    /**
     * Return the response formatted for Discord.
     * @return string
     */
    public function getDiscordResponse(): string;

    /**
     * Set the Discord message.
     * @param \CharlotteDunois\Yasmin\Models\Message $message
     * @return DiscordInterface
     */
    public function setMessage(\CharlotteDunois\Yasmin\Models\Message $message): DiscordInterface;

    /**
     * Return whether the response should be in a DM.
     * @return bool
     */
    public function shouldDM(): bool;
}
