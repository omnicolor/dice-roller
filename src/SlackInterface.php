<?php

declare(strict_types=1);

namespace RollBot;

/**
 * Interface for Slack responses.
 */
interface SlackInterface
{
    public function getSlackResponse(): Response;
}
