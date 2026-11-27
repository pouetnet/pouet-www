<?php

require_once("bootstrap.inc.php");

// Only the logout link, which carries this session's token, logs out: a
// link or an [img] elsewhere pointing at logout.php does nothing
if (@$_SESSION["logoutToken"] && hash_equals($_SESSION["logoutToken"], (string) @$_GET["token"])) {
    $_SESSION = [];
}
redirect("index.php");
