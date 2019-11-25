<?php

declare(strict_types=1);
namespace RollBot;

/**
 * Interface for objects that need config data.
 */
interface ConfigurableInterface {
    /**
     * Set the configuration parameters for the object.
     * @param array $config
     * @return ConfigurableInterface
     */
    public function setConfig(array $config): ConfigurableInterface;
}
