<?php

define("EPHEMERALSTORAGE_PREFIX", "POUET:EPHSTOR:");

// Redis is a hard dependency, like for the PHP sessions: if it's
// unreachable, connect() throws and the page fails
class RedisStorage
{
    public $redis;
    public function __construct()
    {
        $this->redis = new Redis();
        $this->redis->connect('localhost');
    }
    public function get($key)
    {
        return unserialize($this->redis->get(EPHEMERALSTORAGE_PREFIX . $key));
    }
    // $ttl: seconds until the key expires, 0 for never
    public function set($key, $value, $ttl = 0)
    {
        if ($ttl > 0) {
            $this->redis->setex(EPHEMERALSTORAGE_PREFIX . $key, $ttl, serialize($value));
        } else {
            $this->redis->set(EPHEMERALSTORAGE_PREFIX . $key, serialize($value));
        }
    }
    public function has($key)
    {
        return $this->redis->exists(EPHEMERALSTORAGE_PREFIX . $key);
    }
}

$ephemeralStorage = new RedisStorage();
