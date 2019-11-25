<?php

declare(strict_types=1);

namespace RollBot;

use Predis\Client;

/**
 * Trait for adding a Redis client to a Command.
 */
trait RedisClientTrait
{
    /**
     * @var \Predis\Client
     */
    protected $redis;

    /**
     * Set the Redis client.
     * @param \Predis\Client $client
     * @return RedisClientInterface
     */
    public function setRedisClient(Client $client): RedisClientInterface
    {
        $this->redis = $client;
        return $this;
    }
}
