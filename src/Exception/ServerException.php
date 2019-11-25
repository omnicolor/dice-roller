<?php

declare(strict_types=1);
namespace RollBot\Exception;

/**
 * Exception thrown when there's a server issue.
 */
class ServerException extends RollBotException
{
    protected $color = self::COLOR_DANGER;
    protected $title = 'Server Error';
}
