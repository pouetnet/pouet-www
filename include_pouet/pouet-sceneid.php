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
    protected function Request($url, $method = "GET", $contentArray = [], $headerArray = [])
    {
        $query = ["format" => $this->format];
        if ($method === "GET") {
            $query = array_merge($contentArray, $query);
        }
        $url .= "?" . http_build_query($query);
        $headers = [];
        foreach ($headerArray as $key => $value) {
            $headers[] = $key . ": " . $value;
        }
        if (function_exists("curl_init")) {
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HTTPHEADER => $headers,
            ]);
            if ($method === "POST") {
                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($contentArray));
            }
            $data = curl_exec($curl);
            curl_close($curl);
            return $data;
        }
        return file_get_contents($url, false, stream_context_create([
            "http" => [
                "method" => $method,
                "header" => implode("\r\n", $headers),
                "content" => $method === "POST" ? http_build_query($contentArray) : "",
                "follow_location" => 0,
            ],
            "ssl" => ["verify_peer" => true, "verify_peer_name" => true],
        ]));
    }

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
