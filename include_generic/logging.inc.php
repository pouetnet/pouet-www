<?php

// thin wrapper class right now, but room for expansion.
class LOG
{
    public static function Write($string)
    {
        if ($f = fopen(POUET_EXCEPTION_LOG, "a")) {
            fwrite($f, "==[ ".date("Y-m-d H:i:s")." ]".str_repeat("=", 50) . "\n" .
                        "URL:" . $_SERVER["REQUEST_URI"] . "\n" .
                        $string . "\n");
            fclose($f);
        }
    }
    public static function Error($string)
    {
        LOG::Write($string);
    }
    public static function Warning($string)
    {
        LOG::Write($string);
    }
}
