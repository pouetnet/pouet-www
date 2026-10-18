<?php

error_reporting(E_ALL & ~E_NOTICE);

define("POUET_ROOT_LOCAL", dirname(__FILE__));
if (!file_exists(POUET_ROOT_LOCAL . "/include_generic/credentials.inc.php")) {
    die("Please create an include_generic/credentials.inc.php - you can use the credentials.inc.php.dist as an example");
}

require_once(POUET_ROOT_LOCAL . "/include_generic/credentials.inc.php");

// Credentials files that predate these get the prod URLs
if (!defined("POUET_API_URL")) {
    define("POUET_API_URL", "https://api.pouet.net/");
}
if (!defined("POUET_CARDBOARD_URL")) {
    define("POUET_CARDBOARD_URL", "https://cardboard.pouet.net/");
}

if (@$_SERVER['PATH_INFO']) {
    $path = strrchr($_SERVER['PATH_INFO'], "/");
    if ($path === false) {
        header("HTTP/1.1 404 Not Found");
        die("Invalid path");
    } else {
        $urlGuess = rtrim(POUET_ROOT_URL, "/") . $path;
        header("HTTP/1.1 404 Not Found");
        printf("<html><body><h1>Malformed URL</h1>Did you mean <a href='%s'>%s</a>?</body></html>", $urlGuess, $urlGuess);
        exit();
    }
}


require_once(POUET_ROOT_LOCAL . "/include_generic/sqllib.inc.php");
require_once(POUET_ROOT_LOCAL . "/include_generic/sceneid3/sceneid3.inc.php");
require_once(POUET_ROOT_LOCAL . "/include_generic/functions.inc.php");
require_once(POUET_ROOT_LOCAL . "/include_generic/libbb.php");
require_once(POUET_ROOT_LOCAL . "/include_generic/orm.inc.php");
require_once(POUET_ROOT_LOCAL . "/include_generic/formifier.inc.php");
require_once(POUET_ROOT_LOCAL . "/include_generic/LastRss.php");
require_once(POUET_ROOT_LOCAL . "/include_generic/csrf.inc.php");
require_once(POUET_ROOT_LOCAL . "/include_generic/rewriter.inc.php");
require_once(POUET_ROOT_LOCAL . "/include_generic/ephemeral_storage.inc.php");
require_once(POUET_ROOT_LOCAL . "/include_generic/sideload.inc.php");
require_once(POUET_ROOT_LOCAL . "/include_generic/logging.inc.php");

require_once(POUET_ROOT_LOCAL . "/include_pouet/enums.inc.php");
require_once(POUET_ROOT_LOCAL . "/include_pouet/request-classes-base.inc.php");
require_once(POUET_ROOT_LOCAL . "/include_pouet/request-classes-prod.inc.php");
require_once(POUET_ROOT_LOCAL . "/include_pouet/request-classes-group.inc.php");
require_once(POUET_ROOT_LOCAL . "/include_pouet/pouet-box.php");
require_once(POUET_ROOT_LOCAL . "/include_pouet/pouet-sceneid.php");

require_once(POUET_ROOT_LOCAL . "/include_pouet/pouet-api.php");
require_once(POUET_ROOT_LOCAL . "/include_pouet/pouet-prod.php");
require_once(POUET_ROOT_LOCAL . "/include_pouet/pouet-user.php");
require_once(POUET_ROOT_LOCAL . "/include_pouet/pouet-party.php");
require_once(POUET_ROOT_LOCAL . "/include_pouet/pouet-group.php");
require_once(POUET_ROOT_LOCAL . "/include_pouet/pouet-board.php");

require_once(POUET_ROOT_LOCAL . "/include_pouet/pouet-formprocessor.php");
require_once(POUET_ROOT_LOCAL . "/include_pouet/pouet-paginator.php");

if (POUET_TEST) {
    SQLLib::$debugMode = true;
    SQLLib::$telemetry = true;
} else {
    header('Strict-Transport-Security: max-age=2592000; includeSubDomains');
}

$lifetime = 60 * 60 * 24 * 365;
@ini_set('session.cookie_lifetime', $lifetime);
// How long Redis keeps a session after its last use. The server-wide default
// is 7 days, which logged people out long before their cookie expired.
@ini_set('session.gc_maxlifetime', $lifetime);

session_name("POUETSESS4");
session_set_cookie_params([
    "lifetime" => $lifetime,
    "path" => POUET_ROOT_PATH,
    "domain" => POUET_COOKIE_DOMAIN,
    "secure" => !POUET_TEST, // the local stack is plain HTTP
    "httponly" => true,
    "samesite" => "Lax",
]);
// Without a session cookie, the session waits until something needs it
// (login.php, a CSRF token): see start_session()
if (isset($_COOKIE[session_name()])) {
    start_session();
}

$sceneID = null;
if (POUET_TEST && class_exists("MySceneID")) {
    $sceneID = new MySceneID([
        "clientID" => SCENEID_USER,
        "clientSecret" => SCENEID_PASS,
        "redirectURI" => POUET_ROOT_URL . "login.php",
    ]);
} elseif (class_exists("PouetSceneID")) {
    $sceneID = new PouetSceneID([
        "clientID" => SCENEID_USER,
        "clientSecret" => SCENEID_PASS,
        "redirectURI" => POUET_ROOT_URL_SECURE . "login.php",
    ]);
}

$currentUser = null;
if (get_login_id()) {
    $id = get_login_id();

    $host = gethostbyaddr($_SERVER["REMOTE_ADDR"]);
    if ($host !== ".") {
        SQLLib::Query(sprintf_esc(
            "update users set lastip='%s', lasthost='%s', lastlogin='%s' where id=%d",
            $_SERVER["REMOTE_ADDR"],
            $host,
            date("Y-m-d H:i:s"),
            $id,
        ));
        $currentUser = PouetUser::Spawn($id);
    }
}

if (defined("POUET_EXCEPTION_LOG")) {
    set_exception_handler(function ($ex) {
        LOG::Error($ex);
        if (POUET_TEST) {
            echo "<pre>" . _html($ex) . "</pre>";
        }
    });
}

if ($currentUser && $currentUser->IsBanned()) {
    $_SESSION = $currentUser = null;
}

$timer["page"]["start"] = microtime_float();

$currentUserSettings = null;
if ($currentUser) {
    if ($ephemeralStorage->has("settings:" . $currentUser->id)) {
        $currentUserSettings = $ephemeralStorage->get("settings:" . $currentUser->id);
    }
    if (!$currentUserSettings) {
        $currentUserSettings = SQLLib::SelectRow(sprintf_esc("select * from usersettings where id=%d", $currentUser->id));
        if ($currentUserSettings) {
            $ephemeralStorage->set("settings:" . $currentUser->id, $currentUserSettings);
        }
    }
    if (!$currentUserSettings) {
        require_once("include_pouet/default_usersettings.php");
        $currentUserSettings = $DEFAULT_USERSETTINGS;
        $ephemeralStorage->set("settings:" . $currentUser->id, $currentUserSettings);
    }
} else {
    require_once("include_pouet/default_usersettings.php");
    $currentUserSettings = $DEFAULT_USERSETTINGS;
}
$TITLE = "";
$metaValues = [];
$metaValues["og:type"] = "website";
$metaValues["og:site_name"] = "pou\xC3\xABt.net";

$RSS = [];
$linkedData = [];
$linkedData["@context"] = "https://schema.org/";
