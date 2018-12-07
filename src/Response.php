<?php
/**
 * Response object for interacting with Slack
 */

declare(strict_types=1);
namespace RollBot;

/**
 * Slack response class.
 */
class Response
{
    /**
     * Array of attachments to include in the response.
     * @var array
     */
    public $attachments;

    /**
     * Text to send.
     * @var string
     */
    public $text;

    /**
     * Whether to also send the request to the channel it was requested in.
     * @var boolean
     */
    public $toChannel = false;

    /**
     * Return the response as a string.
     * @return string
     */
    public function __toString(): string
    {
        $res = [];
        if ($this->text) {
            $res['text'] = $this->text;
        }
        if ($this->toChannel) {
            $res['response_type'] = 'in_channel';
        } else {
            $res['response_type'] = 'ephemeral';
        }
        if ($this->attachments) {
            $res['attachments'] = $this->attachments;
        }
        return json_encode($res);
    }
}
