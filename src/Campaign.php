<?php

declare(strict_types=1);

namespace RollBot;

/**
 * Representation of a campaign.
 */
class Campaign
{
    /**
     * @var string
     */
    protected $id;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var ?string
     */
    protected $slackHook;

    /**
     * @var string
     */
    protected $type;

    /**
     * Constructor.
     * @param \MongoDB\Model\BSONDocument $campaign
     */
    public function __construct(\MongoDB\Model\BSONDocument $campaign)
    {
        $this->id = (string)$campaign->_id;
        $this->name = $campaign->name;
        $this->slackHook = $campaign->{'slack-hook'} ?? null;
        $this->type = $campaign->type;
    }

    /**
     * Return the campaign's ID.
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Return the name of the campaign.
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Return the Slack hook URL for the campaign.
     * @return ?string
     */
    public function getSlackHook(): ?string
    {
        return $this->slackHook;
    }

    /**
     * Return the type of campaign (expanse, shadowrun5e, etc).
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }
}
