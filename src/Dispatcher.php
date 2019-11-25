<?php

declare(strict_types=1);
namespace RollBot;

use MongoDB\Client as Mongo;
use Monolog\Logger;
use RollBot\Exception\BadRequestException;
use RollBot\Exception\NoCampaignException;
use RollBot\Exception\ServerException;

/**
 * Load things that need loading and route requests to corret place.
 */
class Dispatcher
{
    /**
     * @var string[]
     */
    protected $args;

    /**
     * @var ?\RollBot\Campaign
     */
    protected $campaign;

    /**
     * @var string
     */
    protected $channelId;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var \MongoDB\Client
     */
    protected $mongo;

    /**
     * @var \Monolog\Logger
     */
    protected $log;

    /**
     * @var string
     */
    protected $teamId;

    /**
     * @var string
     */
    protected $text;

    /**
     * @var ?\RollBot\User
     */
    protected $user;

    /**
     * @var string
     */
    protected $userId;

    /**
     * @var string
     */
    protected $webUrl;

    /**
     * Constructor.
     * @param array $post POST superglobal
     * @param array $config Config array
     * @param \MongoDB\Client $mongo
     * @param \Monolog\Logger $log
     */
    public function __construct(
        array $post,
        array $config,
        Mongo $mongo,
        Logger $log
    ) {
        $this->log = $log;
        $this->log->debug('Dispatcher::__construct');
        if (!isset($post['user_id'], $post['team_id'], $post['channel_id'])) {
            $this->log->warning('Request missing required data', $post);
            throw new BadRequestException(
                'Your request does not seem to be a valid Commlink slash command.'
            );
        }
        if (!isset($post['text']) || !trim($post['text'])) {
            throw new BadRequestException(
                'You must include at least one command argument.' . PHP_EOL
                    . 'For example: `/roll init` to roll your character\'s '
                    . 'initiative, `/roll 1` to roll one die, or `/roll 12 6` '
                    . 'to roll twelve dice with a limit of six.' . PHP_EOL
                    . PHP_EOL . 'Type `/roll help` for more help.'
            );
        }
        $this->args = explode(' ', $post['text']);
        $this->channelId = $post['channel_id'];
        $this->config = $config;
        $this->mongo = $mongo;
        $this->teamId = $post['team_id'];
        $this->text = $post['text'];
        $this->userId = $post['user_id'];
        $this->webUrl = $config['web'];

        $this->loadCampaign();
        $this->loadUser();
    }

    /**
     * Return the campaign (if it exists) that the request belongs to.
     * @return ?Campaign
     */
    public function getCampaign(): ?Campaign
    {
        $this->log->debug('Dispatcher::getCampaign');
        return $this->campaign;
    }

    /**
     * Try to load a campaign attached to the current team and channel.
     */
    protected function loadCampaign(): void
    {
        $this->log->debug('Dispatcher::loadCampaign');
        $search = [
            'slack-team' => $this->teamId,
            'slack-channel' => $this->channelId,
        ];
        try {
            $campaign = $this->mongo->shadowrun->campaigns->findOne($search);
        } catch (\MongoDB\Driver\Exception\ConnectionTimeoutException $ex) {
            $this->log->critical(sprintf(
                'Mongo Exception Thrown: %s',
                $ex->getMessage()
            ));
            throw new ServerException('RollBot is unable to respond!');
        }
        if (!$campaign) {
            $this->log->warning(
                'No campaign found',
                [
                    'teamId' => $this->teamId,
                    'channelId' => $this->channelId,
                ]
            );
            return;
        }
        $this->campaign = new Campaign($campaign);
    }

    /**
     * Try to load a user registered to the current channel.
     */
    protected function loadUser(): void
    {
        $this->log->debug('Dispatcher::loadUser');
        try {
            $this->user = new User(
                $this->mongo,
                $this->userId,
                $this->teamId,
                $this->channelId
            );
        } catch (\RuntimeException $ex) {
            // Some rolls don't really require a user.
            $this->log->warning(
                'No user found',
                [
                    'userId' => $this->userId,
                    'teamId' => $this->teamId,
                    'channelId' => $this->channelId,
                ]
            );
        }
    }

    /**
     * Find a roll for an Expanse campaign.
     */
    protected function getExpanseRoll()
    {
        $this->log->debug('Dispatcher::getExpanseRoll');
        if (!$this->user) {
            $ex = Exception\NoUserException();
            $ex->setChannelId($this->channelId)
                ->setTeamId($this->teamId)
                ->setUserId($this->userId)
                ->setWebUrl($this->webUrl);
            throw $ex;
        }
        try {
        $character = Expanse\Character::loadFromMongo(
            $this->mongo,
            $this->campaign->getId(),
            $this->user->email
        );
        } catch (\RuntimeException $e) {
            // Characters are overrated.
            $character = null;
        }
        if (is_numeric($this->args[0])) {
            $roll = new Expanse\Number($this->args, $character);
            return $roll;
        }
        try {
            $class = sprintf(
                'RollBot\\Expanse\\%sRoll',
                ucfirst($this->args[0])
            );
            $roll = new $class();
        } catch (\Error $unused) {
            // Ignore the class not being found, they may have wanted
            // a generic roll.
            return null;
        }
        return $roll;
    }

    /**
     * Find a roll for a Shadowrun campaign.
     */
    protected function getShadowrun5eRoll()
    {
        $this->log->debug('Dispatcher::getShadowrun5eRoll');
        if (!$this->user) {
            $ex = new Exception\NoUserException(
                'You are not registered for this game'
            );
            $ex->setChannelId($this->channelId)
                ->setTeamId($this->teamId)
                ->setUserId($this->userId)
                ->setWebUrl($this->config['web']);
            throw $ex;
        }
        foreach ($this->user->slack as $slack) {
            if ($this->userId === $slack->user_id &&
                $this->teamId === $slack->team_id &&
                $this->channelId === $slack->channel_id) {

                $characterId = $slack->character_id;
                break;
            }
        }
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

        if ($characterId) {
            try {
                $character = new \Commlink\Character($characterId, $guzzle, $jwt);
            } catch (\RuntimeException $e) {
                throw new Exception\BadRequestException(sprintf(
                    'You don\'t seem to be the owner of character ID: %s',
                    $characterId
                ));
            }
        } else {
            $character = new Character();
            $character->handle = 'GM';
        }
        if (is_numeric($this->args[0])) {
            return new Shadowrun5E\Number($character, $this->args);
        }

        try {
            $class = sprintf(
                'RollBot\\Shadowrun5E\\%sRoll', ucfirst($this->args[0])
            );
            return new $class($character, $this->args);
        } catch (\Error $unused) {
            // Ignore the class not being found, they may have wanted
            // a generic roll.
            $this->log->debug($unused->getMessage());
            return null;
        }
    }

    /**
     * Determine whether the request is for a campaign (and thus
     * system-specific roll) or a generic roll.
     * @throws \RollBot\Exception\RollNotFoundException
     */
    public function getRoll()
    {
        $this->log->debug('Dispatcher::getRoll');
        $roll = null;
        if ($this->campaign) {
            if ('expanse' === $this->campaign->getType()) {
                $roll = $this->getExpanseRoll();
            } elseif ('shadowrun5e' === $this->campaign->getType()) {
                $roll = $this->getShadowrun5eRoll();
            }
        } else {
            $this->log->debug('No campaign', $this->args);
        }
        if ($roll) {
            return $roll;
        }

        // See if we want just a generic XdY request.
        if (preg_match('/^\d+d\d+.*/i', $this->text)) {
            $this->log->debug('Generic XdY roll');
            return new Generic($this->text, $this->userId);
        }
        try {
            $class = sprintf('RollBot\\%sRoll', ucfirst($this->args[0]));
            $roll = new $class();
        } catch (\Error $unused) {
            $this->log->error(
                sprintf('Unable to find roll for %s', $this->args[0])
            );
            throw new Exception\RollNotFoundException();
        }

        return $roll;
    }
}
