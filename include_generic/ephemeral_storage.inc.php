<?php

interface IEphemeralStorage
{
    public static function available();
    public function get($key);
    public function set($key, $value);
    public function has($key);
}
define("EPHEMERALSTORAGE_PREFIX", "POUET:EPHSTOR:");

// NOTE:
// This is NOT a good replacement for NoSQL storages
// This is just a fallback!
class SessionStorage implements IEphemeralStorage
{
    public static function available()
    {
        return true;
    }
    public function __construct()
    {
        //@session_start();
    }
    public function get($key)
    {
        return $_SESSION[EPHEMERALSTORAGE_PREFIX.$key];
    }
    public function set($key, $value)
    {
        $_SESSION[EPHEMERALSTORAGE_PREFIX.$key] = $value;
    }
    public function has($key)
    {
        return isset($_SESSION[EPHEMERALSTORAGE_PREFIX.$key]);
    }
}

// this is better but it's brutally slow :)
class FileStorage implements IEphemeralStorage
{
    public $dir;
    public static function available()
    {
        return true;
    }
    public function __construct()
    {
        $this->dir = "/tmp/ephstor/";
        @mkdir($this->dir);
    }
    public function keyToFilename($key)
    {
        $s =  $this->dir . EPHEMERALSTORAGE_PREFIX . $key;
        return str_replace(":", "_", $s);
    }
    public function get($key)
    {
        return unserialize(file_get_contents($this->keyToFilename($key)));
    }
    public function set($key, $value)
    {
        @file_put_contents($this->keyToFilename($key), serialize($value));
    }
    public function has($key)
    {
        return file_exists($this->keyToFilename($key));
    }
}

class RedisStorage implements IEphemeralStorage
{
    public $redis;
    public static function available()
    {
        return class_exists("Redis");
    }
    public function __construct()
    {
        $this->redis = new Redis();
        $this->redis->connect('localhost');
    }
    public function get($key)
    {
        return unserialize($this->redis->get(EPHEMERALSTORAGE_PREFIX.$key));
    }
    public function set($key, $value)
    {
        $this->redis->set(EPHEMERALSTORAGE_PREFIX.$key, serialize($value));
    }
    public function has($key)
    {
        return $this->redis->exists(EPHEMERALSTORAGE_PREFIX.$key);
    }
}

// add memcached on demand

$ephemeralStorage = null;
foreach (array(
  "RedisStorage",
  "FileStorage",
  "SessionStorage",
  ) as $cls) {
    if ($cls::available()) {
        $ephemeralStorage = new $cls();
        break;
    }
}
