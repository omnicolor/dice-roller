<?php
/**
 * Interface for Commands that need a Guzze client to work.
 */

declare(strict_types=1);
namespace RollBot;

use \GuzzleHttp\Client;

/**
 * Interface for commands that use Guzzle.
 */
interface GuzzleClientInterface
{
    /**
     * Set the Guzzle client.
     * @param \GuzzleHttp\Client $client
     * @return GuzzleClientInterface
     */
    public function setGuzzleClient(Client $client): GuzzleClientInterface;
}
