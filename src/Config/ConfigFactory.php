<?php namespace Genetsis\Config;

use Genetsis\Druid\Exceptions\IdentityException;

class ConfigFactory
{
    /**
     * Returns a Config singleton by name.
     *
     * @param $name
     * @return AbstractConfig
     * @throws IdentityException
     */
    public function getConfig($name)
    {
        $class = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $name)));
        $class = 'Genetsis\\Config\\' . $class;
        $this->checkConfig($class);
        return new $class;
    }

    /**
     * Determines if a variable is a valid grant.
     *
     * @param  mixed $class
     * @return boolean
     */
    public function isConfig($class)
    {
        return is_subclass_of($class, AbstractConfig::class);
    }

    /**
     * Checks if a variable is a valid grant.
     *
     * @throws IdentityException
     * @param  mixed $class
     * @return void
     */
    public function checkConfig($class)
    {
        if (!$this->isConfig($class)) {
            throw new IdentityException(sprintf(
                'Config "%s" must extend AbstractConfig',
                is_object($class) ? get_class($class) : $class
            ));
        }
    }

}