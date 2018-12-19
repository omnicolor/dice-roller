<?php
/**
 * Trait for adding a Guzzle client to a Command.
 */

declare(strict_types=1);
namespace RollBot;

use \GuzzleHttp\Client;

/**
 * Trait for adding a Guzzle client to a Command.
 */
trait GuzzleClientTrait
{
    /**
     * Guzzle client
     * @var \GuzzleHttp\Client
     */
    protected $guzzle;

    /**
     * Set the Guzzle client.
     * @param \GuzzleHttp\Client $client
     * @return GuzzleClientInterface
     */
    public function setGuzzleClient(Client $client): GuzzleClientInterface
    {
        $this->guzzle = $client;
        return $this;
    }
}
