<?php namespace Genetsis\Config;

use Cache\Adapter\Apcu\ApcuCachePool;
use Cache\Adapter\Common\AbstractCachePool;
use Cache\Adapter\Filesystem\FilesystemCachePool;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;

class Cache extends AbstractConfig
{

    /**
     * @inheritdoc
     */
    protected function getName() : string
    {
        return 'cache';
    }

    /**
     * @param array $options
     *
     * @return AbstractCachePool $cache
     */
    public function config(array $options)
    {
        //if (function_exists('apcu_fetch')) {
        //    return new ApcuCachePool();
        //} else {
            $cacheDir = (empty($options['cacheDir'])) ? sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'identity-sdk' . DIRECTORY_SEPARATOR : $options['cacheDir'];

            if (!file_exists($cacheDir)) {
                mkdir($cacheDir);
            }

            return new FilesystemCachePool(new Filesystem(new Local($cacheDir)), '.');
        //}
    }
}
