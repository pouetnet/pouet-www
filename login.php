<?php

require_once("bootstrap.inc.php");
require_once("include_pouet/pouet-user.php");

//$csrf = new CSRFProtect();
if (@$_GET["error"]) {
    redirect("error.php?e=".rawurlencode($_GET["error_description"]));
}

if (!@$_GET["code"]) {
    $_SESSION["__return"] = @$_GET["return"];
    if (method_exists($sceneID, "SetScope")) {
        $sceneID->SetScope("basic user:email");
    }
    $sceneID->PerformAuthRedirect();
    exit();
}

$rv = null;
$err = "";
try {
    @$returnURL = $_SESSION["__return"] ?? "";
    unset($_SESSION["__return"]);

    $sceneID->ProcessAuthResponse();

    unset($_SESSION["user"]);

    session_regenerate_id(true);

    $SceneIDuser = $sceneID->Me();

    if (!@$SceneIDuser["success"] || !@$SceneIDuser["user"]["id"]) {
        redirect("error.php?e=".rawurlencode("User not found."));
    }

    $user = PouetUser::Spawn((int)$SceneIDuser["user"]["id"]);
    $welcome = false;
    if (!$user || !$user->id) {
        $entry = glob(POUET_CONTENT_LOCAL."avatars/*.gif");
        if (!empty($entry)) {
            $r = $entry[array_rand($entry)];
            $a = basename($r);
        } else {
            $a = '';
        }

        $user = new PouetUser();
        $user->id = (int)$SceneIDuser["user"]["id"];
        $user->nickname = substr($SceneIDuser["user"]["display_name"], 0, 16);
        $user->avatar = $a;

        $user->Create();

        $user = PouetUser::Spawn($user->id);

        $welcome = true;
    }

    if ($user->IsBanned()) {
        redirect("error.php?e=".rawurlencode("We dun like yer type 'round these parts."));
    }

    $email = trim((string)@$SceneIDuser["user"]["email"]);
    if (!$email && !empty($SceneIDuser["user"]["mail"])) {
        $email = trim((string)$SceneIDuser["user"]["mail"]);
    }
    if (!$email && !empty($SceneIDuser["user"]["emails"]) && is_array($SceneIDuser["user"]["emails"])) {
        foreach ($SceneIDuser["user"]["emails"] as $entry) {
            if (is_string($entry) && filter_var(trim($entry), FILTER_VALIDATE_EMAIL)) {
                $email = trim($entry);
                break;
            }
            if (is_array($entry)) {
                foreach (array("email","value","address") as $key) {
                    if (!empty($entry[$key]) && filter_var(trim($entry[$key]), FILTER_VALIDATE_EMAIL)) {
                        $email = trim($entry[$key]);
                        break 2;
                    }
                }
            }
        }
    }
    if (!($email && filter_var($email, FILTER_VALIDATE_EMAIL))) {
        $email = null;
    }

    SQLLib::UpdateRow("users", array(
      "sceneIDLastRefresh" => date("Y-m-d H:i:s"),
      "sceneIDData" => serialize($SceneIDuser["user"]),
      "email" => $email
    ), sprintf_esc("id=%d", $user->id));

    $_SESSION["user"] = $user;

    $currentUserSettings = SQLLib::SelectRow(sprintf_esc("select * from usersettings where id=%d", $user->id));
    if ($currentUserSettings) {
        $ephemeralStorage->set("settings:".$user->id, $currentUserSettings);
    }

    if ($welcome) {
        redirect("welcome.php" . $returnURL ? "?return=" . rawurlencode(basename($returnURL)) : "");
    } else {
        redirect(basename($returnURL ? $returnURL : "index.php"));
    }

} catch (SceneID3Exception $e) {
    redirect("error.php?e=".rawurlencode($e->GetMessage()));
}
