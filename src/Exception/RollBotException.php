<?php

declare(strict_types=1);
namespace RollBot\Exception;

abstract class RollBotException extends \Exception
{
    const COLOR_DANGER = 'danger';

    protected $color;
    protected $title;

    /**
     * Return the exceptions information as an attachment for a Slack response.
     * @return array
     */
    public function getAttachment(): array
    {
        return [
            'color' => $this->color,
            'title' => $this->title,
            'text' => $this->getMessage(),
        ];
    }

    /**
     * Return the color code to use for the Slack response.
     * @return string
     */
    public function getColor(): string
    {
        return $this->color;
    }

    /**
     * Return the title of the exception to put into the Slack response.
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }
}
