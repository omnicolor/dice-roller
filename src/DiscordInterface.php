<?php

declare(strict_types=1);

namespace RollBot;

/**
 * Interface for Discord responses.
 */
interface DiscordInterface
{
    public function getDiscordResponse(): string;
}
