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
}
