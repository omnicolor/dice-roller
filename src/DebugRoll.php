<?php

declare(strict_types=1);

namespace RollBot;

use CharlotteDunois\Yasmin\Models\Message;

/**
 * Handle a user asking for information about the setup.
 */
class DebugRoll implements DiscordInterface, MongoClientInterface, SlackInterface
{
    use MongoClientTrait;

    /**
     * Discord message that fired this.
     * @var ?Message
     */
    protected ?Message $message;

    /**
     * Figure out which Slack goes with this one (if any)
     * @param \MongoDB\Model\BSONDocument $user
     * @param string $teamId
     * @param string $channelId
     */
    protected function findSlack(
        \MongoDB\Model\BSONDocument $user,
        string $teamId,
        string $channelId
    ): bool {
        if (!$user || !isset($user['slack'])) {
            return false;
        }
        foreach ((array)$user['slack'] as $slack) {
            if ($slack['team_id'] !== $teamId) {
                // Different server
                continue;
            }
            if ($slack['channel_id'] !== $channelId) {
                // Different channel
                continue;
            }
            return true;
        }
        return false;
    }

    /**
     * Try to load a user registered to the current channel.
     * @param string $tag
     * @param string $channel
     * @return User
     * @throws \RuntimeException
     */
    protected function loadUser(string $tag, string $channel): User
    {
        return new User($this->mongo, $tag, null, $channel);
    }

    /**
     * Return the debug information into the channel.
     * @deprecated
     * @return string
     */
    public function __toString(): string
    {
        return (string)$this->getSlackResponse();
    }

    /**
     * Return the response formatted for Discord.
     * @return string
     */
    public function getDiscordResponse(): string
    {
        try {
            $user = $this->loadUser(
                $this->message->author->tag,
                $this->message->channel->name
            );
            $user = $user->email;
        } catch (\RuntimeException $e) {
            $user = 'No user registered';
        }

        $response = '**Debug Info**' . PHP_EOL
            . 'User: ' . $user . PHP_EOL;
        return $response;
    }

    /**
     * Set the Discord message.
     * @param \CharlotteDunois\Yasmin\Models\Message $message
     * @return DiscordInterface
     */
    public function setMessage(
        \CharlotteDunois\Yasmin\Models\Message $message
    ): DiscordInterface {
        $this->message = $message;
        return $this;
    }

    /**
     * Return the debug information to Slack.
     * @return Response
     */
    public function getSlackResponse(): Response
    {
        $channelId = $_POST['channel_id'] ?? 'Unknown';
        $teamId = $_POST['team_id'] ?? 'Unknown';
        $userId = $_POST['user_id'] ?? 'Unknown';
        $search = [
            'slack.user_id' => $userId,
        ];
        $user = $this->mongo->shadowrun->users->findOne($search);
        $attachment = [
            'title' => 'Debugging Info',
            'fields' => [
                [
                    'title' => 'Team ID',
                    'value' => $teamId,
                    'short' => true,
                ],
                [
                    'title' => 'Channel ID',
                    'value' => $channelId,
                    'short' => true,
                ],
                [
                    'title' => 'User ID',
                    'value' => $userId,
                    'short' => true,
                ],
            ],
        ];

        if ($user) {
            $hasSlack = $this->findSlack($user, $teamId, $channelId);
            $search = [
                'slack-channel' => $channelId,
                'slack-team' => $teamId,
            ];
            $campaign = $this->mongo->shadowrun->campaigns->findOne($search);

            $character = null;
            if ($campaign) {
                $search = [
                    'campaign' => (string)$campaign['_id'],
                    'owner' => $user['email'],
                ];
                $character = $this->mongo->shadowrun->characters->findOne($search);
            }

            $attachment['fields'][] = [
                'title' => 'User',
                'value' => $user['email'],
                'short' => true,
            ];
            $attachment['fields'][] = [
                'title' => 'User Systems',
                'value' => implode(', ', (array)$user['systems'] ?? []),
                'short' => true,
            ];

            if (!$hasSlack) {
                $attachment['fields'][] = [
                    'title' => 'Slack',
                    'value' => 'Registered, but not this channel',
                    'short' => false,
                ];
            } else {
                $attachment['fields'][] = [
                    'title' => 'Game Type',
                    'value' => $campaign['type'],
                    'short' => true,
                ];
                $attachment['fields'][] = [
                    'title' => 'Campaign',
                    'value' => $campaign['name'],
                    'short' => false,
                ];
                $attachment['fields'][] = [
                    'title' => 'Campaign',
                    'value' => (string)$campaign['_id'],
                    'short' => true,
                ];
            }
        } else {
            $attachment['fields'][] = [
                'title' => 'User',
                'value' => 'Not found',
                'short' => true,
            ];
        }

        $response = new Response();
        $response->text = 'RollBot Debug';
        $response->attachments[] = $attachment;
        return $response;
    }
}
