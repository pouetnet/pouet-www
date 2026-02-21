<?php

class CSRFProtect
{
    public function __construct()
    {
        if (session_id() == '') {
            die("Initialize sessions please!");
        }

        if (@$_SESSION["CSRFProtect"]) {
            // garbage collect
            foreach ($_SESSION["CSRFProtect"] as $k => $v) {
                if (time() > $v["time"] + 60 * 60 * 24) {
                    unset($_SESSION["CSRFProtect"][$k]);
                }
            }
        }
    }
    public function GenerateTokens()
    {
        $name  = "Protect" . bin2hex(random_bytes(4));
        $token = bin2hex(random_bytes(32));
        $_SESSION["CSRFProtect"][$name]["token"] = $token;
        $_SESSION["CSRFProtect"][$name]["time"] = time();
        return array("name" => $name,"token" => $token);
    }
    public function PrintToken()
    {
        $a = $this->GenerateTokens();
        printf("<input type='hidden' name='ProtName' value='%s'/>\n", _html($a["name"]));
        printf("<input type='hidden' name='ProtValue' value='%s'/>\n", _html($a["token"]));
    }
    public function ValidateToken()
    {
        if (@$_SESSION["CSRFProtect"][ $_POST["ProtName"] ] && hash_equals($_SESSION["CSRFProtect"][ $_POST["ProtName"] ]["token"], (string)$_POST["ProtValue"])) {
            unset($_SESSION["CSRFProtect"][ $_POST["ProtName"] ]);
            return true;
        }
        return false;
    }
}
