<?php
// MySQL
define("SQL_HOST","db");
define("SQL_USERNAME","pouet");
define("SQL_PASSWORD","password");
define("SQL_DATABASE","pouet");

// SceneID API access
// get it here: https://id.scene.org/
define('SCENEID_USER', '');
define('SCENEID_PASS', '');
define('SCENEID_URL', '');

// keep this on, gives you better telemetry + etc.
define('POUET_TEST', true);

// change these values to your local settings so that the paths don't break
define('POUET_ROOT_PATH', "/");
define('POUET_COOKIE_DOMAIN', ".pouet.net");
define('POUET_CONTENT_URL', '/content/');

// Environment specific
define('POUET_MOBILE_HOSTNAME', 'm.pouet.net');
define('POUET_WEB_HOSTNAME', 'localhost:8080');
define('POUET_CONTENT_LOCAL', '/srv/content/');

// Dynamically defined constants
define('POUET_MOBILE', $_SERVER['HTTP_HOST'] == POUET_MOBILE_HOSTNAME);
define('POUET_ROOT_URL_SECURE', POUET_MOBILE ? 'https://'.POUET_MOBILE_HOSTNAME.POUET_ROOT_PATH : 'https://'.POUET_WEB_HOSTNAME.POUET_ROOT_PATH);
define('POUET_ROOT_URL', 'http://'.(POUET_MOBILE ? POUET_MOBILE_HOSTNAME : POUET_WEB_HOSTNAME).POUET_ROOT_PATH );

// if you don't have access to SceneID, you can use this class
// to fake yourself an instance of sceneID
// NOTE: this only works in test mode!

require_once( "sceneid3/sceneid3.inc.php");
class MySceneID extends SceneID3
{
  private string $returnURL;
  function __construct( $options = array() ) { $this->returnURL = $options["redirectURI"]; }
  function GetClientCredentialsToken() { return true; }
  function SetStorage( $storage ) {}
  function SetScope( $scope ) {}
  function SetFormat( $format ) {}
  function UnpackFormat( $data ) { return $data; }

  function GetAuthURL() { return $this->returnURL . "?code=123456&state=123456"; }
  function ProcessAuthResponse( $code = null, $state = null ) { return true; }
  function RefreshToken() { return true; }
  function VerifyToken() { return true; }

  // this is where the actual data is
  function User( $userID ) { return array(
    "success"=>true,
    "user"=>array(
      "id"=>(int)$userID,
      "first_name"=>"First",
      "last_name"=>"Last",
      "display_name"=>"User #".(int)$userID,
    ),
  ); }

  // change this value depending on who you wanna log in as.
  function Me() { return $this->User(1); }
}
