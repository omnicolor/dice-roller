<?php

declare(strict_types=1);
namespace RollBot\Exception;

/**
 * Exception thrown when a campaign is required for a roll.
 */
class NoCampaignException extends RollBotException implements FieldsInterface
{
    protected $color = self::COLOR_DANGER;
    protected $title = 'Channel Not Registered';

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
     * Return the field needed for the response.
     * @return array
     */
    public function getFields(): array
    {
        return [
            [
                'title' => 'team_id',
                'value' => $this->teamId,
                'short' => true,
            ],
            [
                'title' => 'channel_id',
                'value' => $this->channelId,
                'short' => true,
            ]
        ];
    }

    /**
     * Set the Slack Team ID to use in the error message.
     * @param string $teamId
     * @return \RollBot\Exception\NoCampaignException
     */
    public function setTeamId(string $teamId): NoCampaignException
    {
        $this->teamId = $teamId;
        return $this;
    }

    /**
     * Set the Slack Channel ID to use in the error message.
     * @param string $channelId
     * @return \RollBot\Exception\NoCampaignException
     */
    public function setChannelId(string $channelId): NoCampaignException
    {
        $this->channelId = $channelId;
        return $this;
    }
}
