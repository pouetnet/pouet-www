<?php

// SceneID3's constructor starts a session for its storage, and bootstrap
// builds one on every request, so every cookieless hit left a session in
// Redis (pouet-infra#55). This storage uses the session only when there
// already is one (login.php starts it before the auth redirect), and keeps
// the rest in memory, e.g. the client-credentials token user.php fetches.
class PouetSceneIDStorage implements SceneID3StorageInterface
{
    private $data = [];

    public function Set($key, $value)
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION["sceneID"][$key] = $value;
        } else {
            $this->data[$key] = $value;
        }
    }

    public function Get($key)
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return @$_SESSION["sceneID"][$key];
        }
        return @$this->data[$key];
    }
}

class PouetSceneID extends SceneID3
{
    // How long a login started on pouet can take at SceneID: the hour a
    // first visitor's session lasts (login.php)
    private const STATE_LIFETIME = 60 * 60;

    // Same as SceneID3's, minus the session storage
    public function __construct($options = [])
    {
        foreach (["clientID", "clientSecret", "redirectURI"] as $v) {
            if (!$options[$v]) {
                throw new Exception("'" . $v . "' invalid or missing from initializer array!");
            }
            $this->$v = $options[$v];
        }

        $this->storage = new PouetSceneIDStorage();
    }

    public function GenerateState()
    {
        return bin2hex(random_bytes(32));
    }

    // The session can last a year, and so would a state nobody came back
    // with: note when it was handed out, so ProcessAuthResponse() can let
    // it expire
    public function GetAuthURL()
    {
        $url = parent::GetAuthURL();
        $this->storage->Set("stateTime", time());
        return $url;
    }

    public function ProcessAuthResponse($code = null, $state = null)
    {
        $state = $state ?? ($_GET["state"] ?? null);
        $expected = $this->storage->Get("state");
        if (!is_string($state) || !is_string($expected) || !hash_equals($expected, $state)) {
            throw new SceneID3Exception("State mismatch - try clearing your browser cookies!");
        }
        try {
            $stateTime = $this->storage->Get("stateTime");
            if (!is_int($stateTime) || time() - $stateTime > self::STATE_LIFETIME) {
                throw new SceneID3Exception("Your login took too long - please log in again!");
            }
            return parent::ProcessAuthResponse($code, $state);
        } finally {
            $this->storage->Set("state", null);
            $this->storage->Set("stateTime", null);
        }
    }

}
