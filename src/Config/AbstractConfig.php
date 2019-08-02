<?php namespace Genetsis\Config;

abstract class AbstractConfig
{
    /**
     * Returns the name of this grant, eg. 'grant_name', which is used as the
     * grant type when encoding URL query parameters.
     *
     * @return string
     */
    abstract protected function getName() : string;

    /**
     * @param array $options
     * @return mixed
     */
    abstract protected function config(array $options);

    /**
     * @param array $options
     * @return mixed
     */
    public function set(array $options){
        return $this->config($options);
    }
}