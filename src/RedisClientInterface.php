<?php

declare(strict_types=1);

namespace RollBot;

use Predis\Client;

/**
 * Interface for commands that use Redis.
 */
interface RedisClientInterface
{
    /**
     * Set the Redis client.
     * @param \Predis\Client $client
     * @return RedisClientInterface
     */
    public function setRedisClient(Client $client): RedisClientInterface;
}
