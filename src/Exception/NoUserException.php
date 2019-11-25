<?php

declare(strict_types=1);
namespace RollBot\Exception;

/**
 * Exception thrown when a user is required for a roll.
 */
class NoUserException extends RollBotException implements ActionsInterface
{
    protected $color = self::COLOR_DANGER;
    protected $title = 'No User Registered';

    /**
     * Slack channel ID
     * @var string
     */
    protected $channelId;

    /**
     * Slack team ID
     * @var string
     */
    protected $teamId;

    /**
     * Slack user ID
     * @var string
     */
    protected $userId;

    /**
     * URL to the web front end
     * @var string
     */
    protected $web;

    /**
     * Return the action(s) needed for the response.
     * @return array
     */
    public function getActions(): array
    {
        $url = sprintf(
            '%ssettings?%s',
            $this->web,
            http_build_query([
                'channel_id' => $this->channelId,
                'team_id' => $this->teamId,
                'user_id' => $this->userId,
            ])
        );
        return [
            [
                'type' => 'button',
                'text' => 'Register',
                'url' => $url,
            ],
        ];
    }

    /**
     * Set the Slack channel ID.
     * @param string $channelId
     * @return \RollBot\NoUserException
     */
    public function setChannelId(string $channelId): NoUserException
    {
        $this->channelId = $channelId;
        return $this;
    }

    /**
     * Set the Slack team ID.
     * @param string $teamId
     * @return \RollBot\NoUserException
     */
    public function setTeamId(string $teamId): NoUserException
    {
        $this->teamId = $teamId;
        return $this;
    }

    /**
     * Set the Slack user ID.
     * @param string $userId
     * @return \RollBot\NoUserException
     */
    public function setUserId(string $userId): NoUserException
    {
        $this->userId = $userId;
        return $this;
    }

    /**
     * Set the URL to the Commlink frontend.
     * @param string $url
     * @return \RollBot\NoUserException
     */
    public function setWebUrl(string $url): NoUserException
    {
        $this->web = $url;
        return $this;
    }
}
