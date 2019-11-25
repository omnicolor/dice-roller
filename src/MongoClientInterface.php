<?php
/**
 * Interface for Roll commands that need a Mongo client to work.
 */

declare(strict_types=1);
namespace RollBot;

use \MongoDB\Client;

/**
 * Interface for commands that use Mongo.
 */
interface MongoClientInterface
{
    /**
     * Set the Mongo client.
     * @param \MongoDB\Client $client
     * @return MongoClientInterface
     */
    public function setMongoClient(Client $client): MongoClientInterface;
}
