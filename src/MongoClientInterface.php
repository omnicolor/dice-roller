<?php
/**
 * Interface for Roll commands that need a Mongo client to work.
 */

declare(strict_types=1);
namespace RollBot;

use \MongoDb\Client;

/**
 * Interface for commands that use Mongo.
 */
interface MongoClientInterface
{
    /**
     * Set the Mongo client.
     * @param \MongoDb\Client $client
     * @return MongoClientInterface
     */
    public function setMongoClient(Client $client): MongoClientInterface;
}
