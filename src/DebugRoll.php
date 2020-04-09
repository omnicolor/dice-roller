<?php

declare(strict_types=1);

namespace RollBot;

/**
 * Handle a user asking for information about the setup.
 */
class DebugRoll implements MongoClientInterface
{
    use MongoClientTrait;

    /**
     * Figure out which Slack goes with this one (if any)
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
     * Return the debug information into the channel.
     * @return string
     */
    public function __toString(): string
    {
        $channelId = $_POST['channel_id'] ?? 'Unknown';
        $teamId = $_POST['team_id'] ?? 'Unknown';
        $userId = $_POST['user_id'] ?? 'Unknown';
        $search = [
            'slack.user_id' => $userId,
        ];
        $user = $this->mongo->shadowrun->users->findOne($search);
        $attachment = [
            'title' => 'Debugging info',
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
        return (string)$response;
    }
}
