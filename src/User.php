<?php

declare(strict_types=1);

namespace RollBot;

/**
 * Simple user class.
 */
class User
{
    /**
     * Mongo result for the user
     * @var ?\MongoDB\Model\BSONDocument
     */
    protected $user;

    /**
     * Try to load a user from the database.
     * @param \MongoDB\Client $mongo
     * @param string $userId
     * @param string $teamId
     * @param string $channelId
     */
    public function __construct(
        \MongoDB\Client $mongo,
        string $userId,
        string $teamId,
        string $channelId
    ) {
        $search = [
            'slack.user_id' => $userId,
            'slack.team_id' => $teamId,
            'slack.channel_id' => $channelId,
        ];
        $this->user = $mongo->shadowrun->users->findOne($search);
        if (!$this->user) {
            throw new \RuntimeException('User not found');
        }
    }

    /**
     * Return a property of the user.
     * @return mixed
     */
    public function __get(string $name)
    {
        if (!$this->user) {
            return null;
        }
        if (property_exists($this->user, $name)) {
            return $this->user->$name;
        }
        $trace = debug_backtrace();
        trigger_error(
            'Undefined property via __get(): ' . $name . ' in '
                . $trace[0]['file'] . ' on line ' . $trace[0]['line'],
            E_USER_NOTICE
        );
        return null;
    }
}
