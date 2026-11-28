<?php

require_once("bootstrap.inc.php");
require_once("include_pouet/box-modalmessage.php");

if ($currentUser && !$currentUser->CanSubmitItems()) {
    redirect("index.php");
    exit();
}

// The class handling the requested request type, or null when requestType is
// missing, not a string (e.g. requestType[]=x) or not a known type.
function requestTypeClass()
{
    global $REQUESTTYPES;
    $requestType = @$_REQUEST["requestType"];
    if (!is_string($requestType) || !isset($REQUESTTYPES[$requestType])) {
        return null;
    }
    return $REQUESTTYPES[$requestType];
}

// Link to this page for one request type. It keeps only the prod/group being
// edited, so it works with or without an incoming query string.
function requestTypeLink($requestType)
{
    $params = [];
    foreach (["prod", "group"] as $item) {
        if (@$_REQUEST[$item]) {
            $params[$item] = (int) $_REQUEST[$item];
        }
    }
    $params["requestType"] = $requestType;
    return "submit_modification_request.php?" . http_build_query($params);
}

class PouetBoxModificationRequest extends PouetBox
{
    use PouetForm;
    public $formifier;
    public $fields;
    public function __construct()
    {
        parent::__construct();
        $this->uniqueID = "pouetbox_modificationrequest";
        $this->title = "submit a change request";
        $this->formifier = new Formifier();
        $this->fields = [];
    }

    public function Validate($data)
    {
        global $currentUser;

        $errormessage = [];

        if (!$currentUser) {
            $errormessage[] = "you need to be logged in first.";
            return $errormessage;
        }

        return [];
    }

    public function Commit($data)
    {
        $post = [];

        $requestTypeClass = requestTypeClass();
        if (!$requestTypeClass) {
            return ["no such request type!"];
        }

        // The item comes from the parameter matching the request type, and
        // must exist: approving the request changes the item with this id,
        // whatever kind of item the request was filed against
        $itemType = $requestTypeClass::GetItemType();
        $itemClasses = ["prod" => "PouetProd", "group" => "PouetGroup"];
        $itemID = (int) @$_REQUEST[$itemType];
        if (!isset($itemClasses[$itemType]) || !$itemClasses[$itemType]::Spawn($itemID)) {
            return ["no such " . $itemType . "!"];
        }

        $error = $requestTypeClass::ValidateRequest($data, $post);
        if ($error) {
            return $error;
        }
        $a = [];
        $a["requestType"] = $data["requestType"];
        $a["itemID"] = $itemID;
        $a["itemType"] = $itemType;
        $a["requestDate"] = date("Y-m-d H:i:s");
        $a["userID"] = get_login_id();

        $a["requestBlob"] = json_encode($post);

        global $reqID;
        $reqID = SQLLib::InsertRow("modification_requests", $a);

        return [];
    }
    public function LoadFromDB()
    {
        $this->fields = [
            "requestType" => [
                "type" => "select",
                "name" => "whatchu want",
                "assoc" => true,
            ],
        ];

        global $REQUESTTYPES;

        foreach ($REQUESTTYPES as $k => $v) {
            if (@$_REQUEST["prod"]  && $v::GetItemType() == "prod") {
                $this->fields["requestType"]["fields"][$k] = $v::Describe();
            }
            if (@$_REQUEST["group"] && $v::GetItemType() == "group") {
                $this->fields["requestType"]["fields"][$k] = $v::Describe();
            }
        }

        foreach ($_REQUEST as $k => $v) {
            if (@$this->fields[$k]) {
                $this->fields[$k]["value"] = $v;
            }
        }
    }

    public function Render()
    {
        $error = "";
        $js = "";

        echo "\n\n";
        echo "<div class='pouettbl' id='" . $this->uniqueID . "'>\n";

        echo "  <h2>" . $this->title . ": ";
        if (@$_REQUEST["prod"]) {
            $prod = PouetProd::Spawn($_REQUEST["prod"]);
            if (!$prod) {
                die("no such prod!");
            }
            echo $prod->RenderSingleRowShort();
        }
        if (@$_REQUEST["group"]) {
            $group = PouetGroup::Spawn($_REQUEST["group"]);
            if (!$group) {
                die("no such group!");
            }
            echo $group->RenderLong();
        }
        echo "</h2>\n";

        $error = "";
        $requestTypeClass = requestTypeClass();
        if (!@$_REQUEST["requestType"]) {
            echo "  <div class='content'>\n";
            global $REQUESTTYPES;
            echo "<label>whatchu want ?</label>\n";
            echo "<ul>\n";
            foreach ($REQUESTTYPES as $k => $v) {
                if (@$_REQUEST["prod"] && $v::GetItemType() == "prod") {
                    printf("  <li><a href='%s'>%s</a></li>", _html(requestTypeLink($k)), _html($v::Describe()));
                }
                if (@$_REQUEST["group"] && $v::GetItemType() == "group") {
                    printf("  <li><a href='%s'>%s</a></li>", _html(requestTypeLink($k)), _html($v::Describe()));
                }
            }
            printf("  <li><a href='%s'>%s</a></li>", _html(requestTypeLink("other")), _html("other request..."));
            echo "</ul>\n";
            $error = " ";
            echo "  </div>\n";
        } elseif (!$requestTypeClass) {
            // Checked before rendering anything from the request, so an
            // unknown type is never echoed back into the form.
            $error = "no such request type !";
            echo "  <div class='content'>" . $error . "</div>\n";
        } else {
            $this->fields["requestType"]["type"] = "statichidden";
            echo "  <div class='content'>\n";
            $this->formifier->RenderForm($this->fields);
            echo "  </div>\n";
            echo "  <h2>more data</h2>\n";
            echo "  <div class='content'>\n";
            $fields = [];

            $error = $requestTypeClass::GetFields($_REQUEST, $fields, $js);

            if ($fields && !$error) {
                foreach ($_POST as $k => $v) {
                    if (@$fields[$k]) {
                        $fields[$k]["value"] = $v;
                    }
                }
                $this->formifier->RenderForm($fields);
            }
            if ($error) {
                echo $error;
            }
            echo "  </div>\n";

        }

        if ($js) {
            echo "<script type='text/javascript'>\n";
            echo "<!--\n";
            echo $js;
            echo "//-->\n";
            echo "</script>\n";
        }

        if (!$error) {
            echo "  <div class='foot'><input type='submit' value='Submit'></div>";
        }
        echo "</div>\n";
    }
};

$TITLE = "submit a modification request";

if (@$_REQUEST["requestType"] == "other") {
    $from = "";
    if (@$_REQUEST["prod"]) {
        $from = "&fromProd=" . (int) $_REQUEST["prod"];
    } elseif (@$_REQUEST["group"]) {
        $from = "&fromGroup=" . (int) $_REQUEST["group"];
    }
    // With no prod or group, still send the user to the thread instead of a
    // blank page.
    redirect("topic.php?which=" . (int) FIXMETHREAD_ID . $from . "#pouetbox_bbspost");
}

require_once("include_pouet/header.php");
require("include_pouet/menu.inc.php");

echo "<div id='content'>\n";

$form = new PouetFormProcessor();

$form->successMessage
  = "your request was recorded and will be processed by a glöperator eventually !</a> <br>" // l33t h4x
  . "you can keep track of the status of your requests <a href='account.php#pouetbox_accountreq'>on your accounts page !";

if (@$_REQUEST["prod"]) {
    $form->SetSuccessURL("prod.php?which=" . (int) $_REQUEST["prod"], false);
} elseif (@$_REQUEST["group"]) {
    $form->SetSuccessURL("groups.php?which=" . (int) $_REQUEST["group"], false);
} else {
    $form->SetSuccessURL("", false);
}

$form->Add("logo", new PouetBoxModificationRequest());

if ($currentUser && $currentUser->CanSubmitItems() && (int) @$_POST["finalStep"] == 1) {
    $form->Process();
} else {
    unset($_POST[ PouetFormProcessor::fieldName ]);
}

if (get_login_id()) {
    $form->Display();
} else {
    require_once("include_pouet/box-login.php");
    $box = new PouetBoxLogin();
    $box->Render();
}

echo "</div>\n";

require("include_pouet/menu.inc.php");
require_once("include_pouet/footer.php");
