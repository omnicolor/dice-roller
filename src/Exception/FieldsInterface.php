<?php

declare(strict_types=1);
namespace RollBot\Exception;

/**
 * Interface for exceptions that will include one or more fields.
 */
interface FieldsInterface
{
    /**
     * Return the field(s) needed for the response.
     * @return array
     */
    public function getFields(): array;
}
