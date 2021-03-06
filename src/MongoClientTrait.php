<?php

declare(strict_types=1);

namespace RollBot;

use MongoDB\Client;

/**
 * Trait for adding a Mongo client to a Command.
 */
trait MongoClientTrait
{
    /**
     * @var \MongoDB\Client
     */
    protected $mongo;

    /**
     * Set the Mongo client.
     * @param \MongoDB\Client $client
     * @return MongoClientInterface
     */
    public function setMongoClient(Client $client): MongoClientInterface
    {
        $this->mongo = $client;
        return $this;
    }
}
