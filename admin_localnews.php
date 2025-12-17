<?php

require_once("bootstrap.inc.php");
require_once("include_pouet/box-modalmessage.php");
require_once("include_pouet/box-party-submit.php");

if ($currentUser && !$currentUser->IsModerator()) {
    redirect("index.php");
    exit();
}

class PouetBoxAdminEditLocalNews extends PouetBox
{
    use PouetForm;
    public $id;
    public $item;
    public $fields;
    public $formifier;
    public function __construct($id)
    {
        parent::__construct();
        $this->uniqueID = "pouetbox_admineditlocalnews";
        $this->title = "edit local news";
        $this->id = $id;
        $this->formifier = new Formifier();

        $this->fields = array(
          "title" => array(
            "name" => "title",
          ),
          "url" => array(
            "name" => "url",
          ),
          "posttext" => array(
            "name" => "news content",
            "type" => "textarea",
          ),
        );

        if ($_POST) {
            foreach ($_POST as $k => $v) {
                if (@$this->fields[$k]) {
                    $this->fields[$k]["value"] = $v;
                }
            }
        }

    }

    public function Commit($data)
    {
        global $currentUser;

        $a = array();

        $a["title"] = $data["title"];
        $a["url"] = $data["url"];
        $a["content"] = $data["posttext"];
        $a["who"] = $currentUser->id;

        if ($data["localnewsID"]) {
            SQLLib::UpdateRow("localnews", $a, "id=".(int)$data["localnewsID"]);
        } else {
            SQLLib::InsertRow("localnews", $a);
        }

        return array();
    }

    public function LoadFromDB()
    {
        if ($this->id) {
            $s = new BM_Query();
            $s->AddTable("localnews");
            $s->AddWhere(sprintf_esc("id = %d", $this->id));
            $item = $s->perform();
            $this->item = $item[0];

            $this->fields["title"]["value"] = $this->item->title;
            $this->fields["url"]["value"] = $this->item->url;
            $this->fields["posttext"]["value"] = $this->item->content;
        }
    }

    public function Render()
    {
        global $REQUESTTYPES;
        echo "<div id='".$this->uniqueID."' class='pouettbl'>\n";
        echo "  <h2>".$this->title;
        if ($this->id) {
            printf(": #%d", $this->id);
            printf("<input type='hidden' name='localnewsID' value='%d'/>", $this->id);
        } else {
            echo " - add new";
        }
        echo "</h2>";
        echo "  <div class='content'>\n";
        $this->formifier->RenderForm($this->fields);
        echo "  </div>\n";
        echo "  <div class='foot'><input type='submit' value='Submit' /></div>";
        echo "</div>\n";
    }
}

class PouetBoxAdminEditLocalNewsList extends PouetBox
{
    use PouetForm;
    public $items;
    public function __construct()
    {
        parent::__construct();
        $this->uniqueID = "pouetbox_admineditfaqlist";
        $this->title = "edit local news";

        if (@$_GET["delLocalNews"]) {
            //echo "<script type='text/javascript'>alert('deleting...');</script>";
            SQLLib::Query("delete from localnews where id=".(int)$_GET["which"]);
            //redirect("admin_localnews.php");
        }
    }

    public function LoadFromDB()
    {
        $s = new BM_Query();
        $s->AddField("localnews.id as id");
        $s->AddField("localnews.content as content");
        $s->AddField("localnews.quand as quand");
        $s->AddField("localnews.title as title");
        $s->AddField("localnews.url as url");
        $s->AddTable("localnews");
        $s->attach(array("localnews" => "who"), array("users as author" => "id"));
        $s->AddOrder("quand desc");
        $this->items = $s->perform();
    }

    public function Render()
    {
        global $REQUESTTYPES;
        echo "<table id='".$this->uniqueID."' class='boxtable'>\n";
        echo "  <tr>\n";
        echo "    <th colspan='6'>".$this->title."</th>\n";
        echo "  </tr>\n";
        echo "  <tr>\n";
        echo "    <th>id</th>\n";
        echo "    <th>title</th>\n";
        echo "    <th>author</th>\n";
        echo "    <th>link</th>\n";
        echo "    <th>timestamp</th>\n";
        echo "    <th>action</th>\n";
        echo "  </tr>\n";

        foreach ($this->items as $r) {
            echo "  <tr>\n";
            echo "    <td>".$r->id."</td>\n";
            echo "    <td><a href='admin_localnews.php?id=".(int)$r->id."'>".$r->title."</a></td>\n";
            echo "    <td>".$r->author->PrintLinkedName()."</td>\n";
            echo "    <td><a target=\"_blank\" href='".$r->url."'>".$r->url."</a></td>\n";
            echo "    <td>".$r->quand."</td>\n";
            echo "    <td><a href=\"admin_localnews.php?which=".$r->id."&delLocalNews=1\">delete</a></td>\n";
            echo "  </tr>\n";
        }

        echo "  <tr>\n";
        echo "    <td colspan='6'><a href='admin_localnews.php?new=add'>add new item</a></th>\n";
        echo "  </tr>\n";
        echo "</table>\n";
    }
}

$form = new PouetFormProcessor();

if (@$_GET["id"] || @$_GET["new"] == "add") {
    $form->Add("adminModLocalNewsID", new PouetBoxAdminEditLocalNews(@$_GET["id"]));
} else {
    $form->Add("adminModLocalNews", new PouetBoxAdminEditLocalNewsList());
}

if ($currentUser && $currentUser->IsModerator()) {
    $form->SetSuccessURL("admin_localnews.php", true);
    $form->Process();
}

$TITLE = "edit local news";

require_once("include_pouet/header.php");
require("include_pouet/menu.inc.php");

echo "<div id='content'>\n";

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
