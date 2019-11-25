<?php

declare(strict_types=1);
namespace RollBot\Exception;

/**
 * Interface for exceptions that have one or more actions associated with them.
 */
interface ActionsInterface
{
    /**
     * Return the action(s) needed for the response.
     * @return array
     */
    public function getActions(): array;
}
