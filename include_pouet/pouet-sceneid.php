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
    // Time spent waiting on the network doesn't count toward
    // max_execution_time, so a hung id.scene.org would hold PHP-FPM workers
    private const CONNECT_TIMEOUT = 5;
    private const TIMEOUT = 10;

    // Replaces SceneID3's, which didn't check certificates, had no timeouts,
    // and returned false when the request failed: callers took that for an
    // empty answer. This throws instead, so login.php shows the cause and
    // GetSceneIDData falls back to its cache. 4xx answers still come back,
    // since SceneID3 reads the OAuth errors in their JSON bodies.
    protected function Request($url, $method = "GET", $contentArray = [], $headerArray = [])
    {
        $query = ["format" => $this->format];
        $body = null;
        if ($method === "GET") {
            $query = array_merge($contentArray, $query);
        } else {
            $body = http_build_query($contentArray);
            $headerArray["Content-Type"] = "application/x-www-form-urlencoded";
        }
        // SceneID3::User() passes a URL that already has "?id=N"
        $fullURL = $url . (str_contains($url, "?") ? "&" : "?") . http_build_query($query);
        $headers = [];
        foreach ($headerArray as $key => $value) {
            $headers[] = $key . ": " . $value;
        }

        if (function_exists("curl_init")) {
            [$status, $data] = $this->SendCurl($fullURL, $method, $headers, $body);
        } else {
            [$status, $data] = $this->SendStream($fullURL, $method, $headers, $body);
        }

        // Redirects aren't followed, so the Authorization header can't leave
        // for another host, and neither a 3xx nor a 5xx body is a SceneID answer
        $class = intdiv($status, 100);
        if ($class !== 2 && $class !== 4) {
            throw new SceneID3Exception("SceneID answered HTTP " . $status . " for " . $url);
        }
        return $data;
    }

    private function SendCurl($url, $method, $headers, $body)
    {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        if ($body !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }
        $data = curl_exec($curl);
        if ($data === false) {
            throw new SceneID3Exception("SceneID request failed: " . curl_error($curl));
        }
        return [curl_getinfo($curl, CURLINFO_RESPONSE_CODE), $data];
    }

    private function SendStream($url, $method, $headers, $body)
    {
        $data = @file_get_contents($url, false, stream_context_create([
            "http" => [
                "method" => $method,
                "header" => implode("\r\n", $headers),
                "content" => $body ?? "",
                "follow_location" => 0,
                // Return 4xx and 5xx bodies, not false and a warning
                "ignore_errors" => true,
                "timeout" => self::TIMEOUT,
            ],
            "ssl" => ["verify_peer" => true, "verify_peer_name" => true],
        ]));
        if ($data === false) {
            throw new SceneID3Exception("SceneID request failed: " . (error_get_last()["message"] ?? "unknown error"));
        }
        preg_match('{^HTTP/\S+ (\d{3})}', $http_response_header[0] ?? "", $match);
        return [(int) ($match[1] ?? 0), $data];
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
