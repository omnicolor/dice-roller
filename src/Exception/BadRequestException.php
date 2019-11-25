<?php

declare(strict_types=1);

namespace RollBot\Exception;

/**
 * Generic exception thrown when the user sent a bad request.
 */
class BadRequestException extends RollBotException
{
    protected $color = self::COLOR_DANGER;
    protected $title = 'Bad Request';
}
