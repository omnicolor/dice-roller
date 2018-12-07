<?php
/**
 * Trait for adding a Mongo client to a Command.
 */

declare(strict_types=1);
namespace RollBot;

use \MongoDb\Client;

/**
 * Trait for adding a Mongo client to a Command.
 */
trait MongoClientTrait
{
    /**
     * @var \MongoDb\Client
     */
    protected $mongo;

    /**
     * Set the Mongo client.
     * @param \MongoDb\Client $client
     * @return MongoClientInterface
     */
    public function setMongoClient(Client $client): MongoClientInterface
    {
        $this->mongo = $client;
        return $this;
    }
}
