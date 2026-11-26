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

    // Same as SceneID3's RequestCURL(), but with TLS certificate checks on:
    // the library turns them off, so anyone on the network path to
    // id.scene.org could read our client secret and the login codes, and
    // answer /me with any user. No fallback without curl, since the
    // library's file_get_contents() one turns the checks off too.
    protected function Request($url, $method = "GET", $contentArray = [], $headerArray = [])
    {
        $headers = [];
        foreach ($headerArray as $k => $v) {
            $headers[] = $k . ": " . $v;
        }

        $getArray = $method == "GET" ? $contentArray : [];
        $getArray["format"] = $this->format;
        $url .= "?" . http_build_query($getArray);

        $ch = curl_init();
        if ($method == "POST") {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($contentArray));
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        return curl_exec($ch);
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
