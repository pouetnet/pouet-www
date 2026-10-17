<?php

// thin wrapper class right now, but room for expansion.
class LOG
{
    public static function Write($string)
    {
        $entry = "==[ " . date("Y-m-d H:i:s") . " ]" . str_repeat("=", 50) . "\n"
                 . "URL:" . ($_SERVER["REQUEST_URI"] ?? "") . "\n"
                 . $string . "\n";
        // an undefined constant is a fatal since PHP 8: don't let a missing
        // config line turn every logged warning into a crash
        if (!defined("POUET_EXCEPTION_LOG")) {
            error_log($entry);
            return;
        }
        if ($f = fopen(POUET_EXCEPTION_LOG, "a")) {
            fwrite($f, $entry);
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
