<?php

declare(strict_types=1);

namespace RollBot;

use CharlotteDunois\Yasmin\Models\DMChannel;
use CharlotteDunois\Yasmin\Models\Message;
use Commlink\Character;
use MongoDB\Client as Mongo;
use Monolog\Logger;

/**
 * Dispatcher for handling Discord requests.
 */
class DiscordDispatcher
{
    /**
     * Campaign attached to the channel.
     * @var ?Campaign
     */
    protected ?Campaign $campaign;

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
     * Server we're working with.
     * @var string
     */
    protected string $server;

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
     */
    protected function loadUser(string $tag): void
    {
        try {
            $this->user = new User(
                $this->mongo,
                $tag,
                $this->server,
                $this->channel->name
            );
        } catch (\RuntimeException $ex) {
            // Some rolls don't really require a user.
            $this->log->warning(
                'DiscordDispatcher::loadUser - No Discord user found',
                [
                    'tag' => $tag,
                    'server' => $this->server,
                    'channel' => $this->channel,
                ]
            );
            return;
        }

        // Clean up the Discord block, since there could be more than one.
        foreach ((array)$this->user->discord as $discord) {
            if (
                $discord['tag'] === $tag
                && $discord['server'] === $this->server
                && $discord['channel'] === $this->channel->name
            ) {
                $this->user->discord  = (array)$discord;
                break;
            }
        }
    }

    /**
     * Try to load a campaign attached to the current team and channel.
     */
    protected function loadCampaign(): void
    {
        $this->campaign = null;
        if (!isset($this->server)) {
            // DM's don't have servers, so we can't associate a campaign.
            return;
        }

        $search = [
            'discord-server' => $this->server,
            'discord-channel' => $this->channel->name,
        ];
        try {
            $campaign = $this->mongo->shadowrun->campaigns->findOne($search);
        } catch (\MongoDB\Driver\Exception\ConnectionTimeoutException $ex) {
            $this->log->critical(
                sprintf(
                    'DiscordDispatcher::loadCampaign - Mongo Exception Thrown: %s',
                    $ex->getMessage()
                ),
                $search
            );
            return;
        }
        if (!$campaign) {
            $this->log->warning(
                'DiscordDispatcher::loadCampaign - No campaign found',
                $search
            );
            return;
        }
        $this->log->debug(
            'DiscordDispatcher::loadCampaign - Found campaign',
            (array)$campaign
        );
        $this->campaign = new Campaign($campaign);
    }

    /**
     * Try to load the character attached to the current campaign and channel.
     * @return Character
     * @throws \RuntimeException
     */
    protected function loadCharacter(): Character
    {
        $character = new Character();
        if (isset($this->user->discord['gm'])) {
            $character->handle = $this->user->discord['gm'];
            return $character;
        }
        $characterId = $this->user->discord['characterID'];
        $guzzle = new \GuzzleHttp\Client(['base_uri' => $this->config['api']]);
        $jwt = (new \Lcobucci\JWT\Builder())
            ->setIssuer('https://sr.digitaldarkness.com')
            ->setAudience($this->config['api'])
            ->setIssuedAt(time())
            ->setExpiration(time() + 60)
            ->set('email', $this->user->email)
            ->sign(
                new \Lcobucci\JWT\Signer\Hmac\Sha256(),
                $this->config['secret']
            )
            ->getToken();
        try {
            $character = new Character($characterId, $guzzle, $jwt);
        } catch (\RuntimeException $e) {
            $this->log->error(
                'DiscordDispatcher::loadCharacter - No access',
                [
                    'server' => $this->server,
                    'channel' => $this->channel->name,
                    'user' => $this->user->email,
                    'character' => $characterId,
                ]
            );
            throw new Exception\BadRequestException(sprintf(
                'You don\'t seem to be the owner of character ID: %s',
                $characterId
            ));
        }

        return $character;
    }

    /**
     * Find a roll for a Shadowrun campaign.
     * @param array $args
     * @throws \Error
     * @throws \Exception
     */
    protected function getShadowrun5eRoll(array $args)
    {
        $this->log->debug('DiscordDispatcher::getShadowrun5eRoll');
        if (!isset($this->user)) {
            throw new \Exception('You are not registered for this game');
        }
        $character = $this->loadCharacter();
        if (is_numeric($args[0])) {
            return new Shadowrun5E\Number($character, $args);
        }

        $class = sprintf(
            'RollBot\\Shadowrun5E\\%sRoll',
            ucfirst($args[0])
        );
        return new $class($character, $args);
    }

    /**
     * Handle a user's roll and send a response.
     * @param CharlotteDunois\Yasmin\Models\Message $message
     */
    public function handleRoll(Message $message): void
    {
        $this->channel = $message->channel;
        if ($this->channel instanceof DMChannel) {
            if (substr($message->content, 0, 1) !== '/') {
                $message->content = '/roll ' . $message->content;
            }
            $this->log->debug(
                'DiscordDispatcher::handleRoll handling DM'
            );
        } else {
            $this->server = (string)$this->channel->guild->id;
        }
        if (substr($message->content, 0, 1) !== '/') {
            // Ignore non-command chatter.
            return;
        }
        if (
            !($this->channel instanceof DMChannel) &&
            !in_array($this->channel->name, $this->config['discord_channels'])
        ) {
            // Ignore message in non-DM and non-monitored channel.
            return;
        }

        $this->loadUser($message->author->tag);

        $content = explode(' ', $message->content);
        array_shift($content);
        $command = $content[0];
        $args = array_slice($content, 1);

        // See if the request if for Generic dice rolling.
        if (preg_match('/^\d+d\d+.*/i', $command)) {
            $this->log->debug(
                'DiscordDispatcher::handleRoll - Generic XdY roll'
            );
            $roll = new Generic(
                $command . ' ' . implode(' ', $args),
                $this->user->discord[0]['gm'] ?? $message->author->username
            );
            $message->channel->send($roll->getDiscordResponse());
            return;
        }

        $this->loadCampaign();
        $roll = null;
        if ($this->campaign && 'shadowrun5e' === $this->campaign->getType()) {
            try {
                $roll = $this->getShadowrun5eRoll($content);
            } catch (\Exception $ex) {
                $message->reply($ex->getMessage());
                return;
            } catch (\Error $unused) {
                // Ignore: they may want to try a non-Shadowrun roll.
            }
        }

        if (!$roll) {
            try {
                $class = sprintf('RollBot\\%sRoll', ucfirst($command));
                $roll = new $class();
            } catch (\Error $ex) {
                $this->log->error(
                    'DiscordDispatcher::handleRoll - Unable to find roll',
                    $content
                );
                $message->reply('Not sure what to do with that');
                return;
            }
        }

        if (!($roll instanceof DiscordInterface)) {
            $message->reply(
                'That is a valid command, but has not been set up for Discord'
            );
            return;
        }

        if ($roll instanceof ConfigurableInterface) {
            $roll->setConfig($this->config);
        }
        if ($roll instanceof MongoClientInterface) {
            $roll->setMongoClient($this->mongo);
        }
        if ($roll instanceof RedisClientInterface) {
            $redis = new \Predis\Client();
            $roll->setRedisClient($redis);
        }

        $roll->setMessage($message);

        if ($roll->shouldDM()) {
            $message->author->createDM()->then(function ($dm) use ($roll) {
                $dm->send($roll->getDiscordResponse());
            });
            return;
        }

        $this->channel->send($roll->getDiscordResponse());
    }
}
