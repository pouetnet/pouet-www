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

    // Not in SceneID3StorageInterface, but SceneID3OAuth::Reset() calls it
    public function Reset()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            unset($_SESSION["sceneID"]);
        }
        $this->data = [];
    }
}

class PouetSceneID extends SceneID3
{
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

    public function ProcessAuthResponse($code = null, $state = null)
    {
        $state = $state ?? ($_GET["state"] ?? null);
        $expected = $this->storage->Get("state");
        if (!is_string($state) || !is_string($expected) || !hash_equals($expected, $state)) {
            throw new SceneID3Exception("State mismatch - try clearing your browser cookies!");
        }
        try {
            return parent::ProcessAuthResponse($code, $state);
        } finally {
            $this->storage->Set("state", null);
        }
    }

}
