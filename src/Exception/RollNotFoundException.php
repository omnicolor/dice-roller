<?php

declare(strict_types=1);

namespace RollBot\Exception;

/**
 * Exception thrown when the user tries to roll something not found.
 */
class RollNotFoundException extends RollBotException
{
    protected $color = self::COLOR_DANGER;
    protected $title = 'Bad Request';

    public function __construct()
    {
        parent::__construct(
            'That doesn\'t seem to be a valid command. Try `/roll help`.'
                . PHP_EOL
        );
    }
}
