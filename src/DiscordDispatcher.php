<?php

declare(strict_types=1);

namespace RollBot;

use CharlotteDunois\Yasmin\Models\Message;
use MongoDB\Client as Mongo;
use Monolog\Logger;

/**
 * Dispatcher for handling Discord requests.
 */
class DiscordDispatcher
{
    /**
     * Configuration array.
     * @var array<string, mixed>
     */
    protected array $config;

    /**
     * Connection to Mongo.
     * @var Mongo $mongo
     */
    protected Mongo $mongo;

    /**
     * Monolog instance.
     * @var Logger $log
     */
    protected Logger $log;

    /**
     * User making the request, if it exists.
     * @var User
     */
    protected ?User $user;

    /**
     * Constructor.
     * @param array $config
     * @param Logger $log
     * @param Mongo $mongo
     */
    public function __construct(array $config, Logger $log, Mongo $mongo)
    {
        $this->config = $config;
        $this->log = $log;
        $this->mongo = $mongo;
    }

    /**
     * Try to load a user registered to the current channel.
     * @param string $tag
     * @param string $channel
     */
    protected function loadUser(string $tag, string $channel): void
    {
        try {
            $this->user = new User($this->mongo, $tag, null, $channel);
            $this->log->debug('DiscordDispatcher::loadUser');
        } catch (\RuntimeException $ex) {
            // Some rolls don't really require a user.
            $this->log->warning(
                'DiscordDispatcher::loadUser - No Discord user found',
                [
                    'tag' => $tag,
                    'channel' => $channel,
                ]
            );
        }
    }

    /**
     * Handle a user's roll and send a response.
     * @param CharlotteDunois\Yasmin\Models\Message $message
     */
    public function handleRoll(Message $message): void
    {
        if (
            'I am not a bot.' === $message->content
            && 'not-omni#5063' === $message->author->tag
        ) {
            $message->reply('I think you\'re a bot. It takes one to know one!');
            return;
        }
        if (substr($message->content, 0, 1) !== '/') {
            return;
        }
        if (!in_array($message->channel->name, $this->config['discord_channels'])) {
            $this->log->debug(
                'DiscordDispatcher::handleRoll ignoring message in other channel'
            );
            return;
        }

        $this->loadUser($message->author->tag, $message->channel->name);

        $content = explode(' ', $message->content);
        array_shift($content);
        $command = $content[0];
        $args = array_slice($content, 1);

        // See if the request if for Generic dice rolling.
        if (preg_match('/^\d+d\d+.*/i', $command)) {
            $this->log->debug('Generic XdY roll');
            $roll = new Generic(
                $command . ' ' . implode(' ', $args),
                $message->author->username
            );
            $message->channel->send($roll->getDiscordResponse());
            return;
        }

        try {
            $class = sprintf('RollBot\\%sRoll', ucfirst($command));
            $roll = new $class();
        } catch (\Error $ex) {
            $this->log->error(
                sprintf('Unable to find roll for %s: %s', $command, $ex)
            );
            $message->reply('Not sure what to do with that');
            return;
        }

        if ($roll instanceof ConfigurableInterface) {
            $roll->setConfig($this->config);
        }

        if (!($roll instanceof DiscordInterface)) {
            $message->reply('That is a valid command, but has not been set up for Discord yet');
            return;
        }

        $message->channel->send($roll->getDiscordResponse());
        return;
        switch ($command) {
            case 'help':
                $message->channel->send(sprintf(
                    'RollBot is a Slack/Discord bot that lets you roll dice '
                    . 'appropriate for various RPG systems. For example, if '
                    . 'you are playing The Expanse, it will roll three dice, '
                    . 'marking one of them as the "drama die", adding up the '
                    . 'result with the number you give for your '
                    . 'attribute+focus score, and return the result along with '
                    . 'any stunt points.' . PHP_EOL . PHP_EOL
                    . 'If your game uses Commlink (%s) as well, links in the '
                    . 'app will automatically roll in Slack, and changes made '
                    . 'to your character via Slack or Discord will appear in '
                    . 'Commlink.' . PHP_EOL . PHP_EOL
                    . '**Supported Systems**' . PHP_EOL
                    . 'The current channel is not registered for any of the '
                    . 'systems.' . PHP_EOL
                    . '· The Expanse' . PHP_EOL
                    . '· Shadowrun Anarchy' . PHP_EOL
                    . '· Shadowrun 5th Edition' . PHP_EOL
                    . '· Shadowrun 6th Edition' . PHP_EOL
                    . '· Star Trek Adventures' . PHP_EOL . PHP_EOL
                    . '**Commands For Unregistered Channels**' . PHP_EOL
                    . '`help` - Show help' . PHP_EOL
                    . '`XdY[+-M] [T]` - Roll X dice with Y pips, adding or '
                    . 'subtracting M from the total, with optional T text',
                    $this->config['web']
                ));
                return;
        }
        $this->log->debug(
            'DiscordDispatcher::handleRoll handling message',
            [
                'from' => $message->author->tag,
                'channel' => $message->channel->name,
                'message' => $message->content,
            ]
        );
        $message->reply('Not sure what to do with that');
    }
}
