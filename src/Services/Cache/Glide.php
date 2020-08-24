<?php


namespace A17\Twill\Services\Cache;

use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Local as Adapter;
use League\Flysystem\Cached\CachedAdapter;
use League\Flysystem\Cached\Storage\Psr6Cache as Store;

class Glide
{
    public function laravelPsr6Adapter()
    {
        $client = app('cache.psr6');

        $adapter = new CachedAdapter(new Adapter(storage_path('app')), new Store($client));

        return new Filesystem($adapter);
    }

    public function getStore($store)
    {
        if ($store === 'laravel') {
            return $this->laravelPsr6Adapter();
        }

        return $store;
    }
}
