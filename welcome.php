<?php

require_once("bootstrap.inc.php");
require_once("include_pouet/box-modalmessage.php");

$errormessage = "";

////////////////////////////////////////////////////////////

$TITLE = "welcome to pouët.net !";

require_once("include_pouet/header.php");
require("include_pouet/menu.inc.php");

if ($currentUser) {
    // A POST with a CSRF token, so an image or link elsewhere can't change
    // the avatar of whoever views it
    $csrf = new CSRFProtect();
    if (@$_POST["avatar"] && $csrf->ValidateToken()) {
        $sql = [];
        $sql["avatar"] = basename($_POST["avatar"]);
        if (!file_exists(POUET_CONTENT_LOCAL . "avatars/" . $sql["avatar"])) {
            $avatars = glob(POUET_CONTENT_LOCAL . "avatars/*.gif");
            $sql["avatar"] = $avatars ? basename($avatars[ array_rand($avatars) ]) : $currentUser->avatar;
        }

        SQLLib::UpdateRow("users", $sql, "id=" . (int) get_login_id());

        $currentUser->avatar = $sql["avatar"];
    }
    class PouetWelcome extends PouetBox
    {
        public function __construct()
        {
            $this->uniqueID = "pouetbox_welcome";
            $this->title = "welcome to pou&euml;t.net !";
        }
        public function RenderBody()
        {
            global $currentUser;
            echo " <div class='content'>\n";
            echo " <p>hi !</p>";
            echo " <p>welcome to our little site !</p>";
            echo " </div>\n";

            echo " <h2>:: account</h2>\n";
            echo " <div class='content'>\n";
            echo " <p>SceneID tells us your name is <b>" . _html($currentUser->nickname) . "</b> so that's what we're gonna stick to. you can go to your <a href='account.php'>profile</a> to change it.</p>";
            echo " <p id='currentAvatar'>we also randomly picked an avatar for you: " . $currentUser->PrintAvatar() . "</p>";
            echo " </div>\n";

            echo " <div class='content'>\n";
            echo " <p>if you don't like it, here are a bunch of other ones:</p>";

            $avatars = glob(POUET_CONTENT_LOCAL . "avatars/*.gif");
            shuffle($avatars);

            printf("<form action='%s' method='post'>\n", _html(selfPath()));
            $csrf = new CSRFProtect();
            $csrf->PrintToken();
            echo " <ul id='welcome-avatarlist'>";
            for ($x = 0; $x < 30; $x++) {
                $a = basename($avatars[$x]);
                printf("<li><button type='submit' name='avatar' value='%s'><img src='" . POUET_CONTENT_URL . "avatars/%s' class='avatar' alt='%s'></button></li>", _html($a), rawurlencode($a), _html($a));
            }
            echo " </ul>";
            echo "</form>\n";

            echo " <p>...or, again, you can go to your <a href='account.php'>profile</a> to pick from some more.</p>";
            echo " </div>\n";

            echo " <h2>:: demos</h2>\n";
            echo " <div class='content'>\n";
            echo " <p>...and of course as for your first activity on the site, you can just go and watch some demos - here's a little selection of the more popular prods:</p>";

            $s = new BM_Query("prods");
            $s->AddWhere("rank < 100  ");
            $s->AddOrder("rand()");
            $s->SetLimit(10);
            $prods = $s->perform();

            echo " <ul id='welcome-prodlist'>";
            foreach ($prods as $prod) {
                printf("<li><b>%s</b> by %s</li>", $prod->RenderLink(), $prod->RenderGroupsLong());
            }
            echo " </ul>";

            echo " <p>enjoy !</p>";

            echo " </div>\n";

            // login.php passes the page's file name and query, as it does for
            // its own redirect: keep it a page on this site, so a link can't
            // make it a javascript: URL or another site
            if (@$_GET["return"]) {
                echo " <h2>:: return</h2>\n";
                echo " <div class='content'>\n";
                echo " <p>...or you can continue whatever you were doing by <a href='" . _html(POUET_ROOT_URL . basename($_GET["return"])) . "'>clicking here</a></p>";
                echo " </div>\n";
            }
        }
    };

    $message = new PouetWelcome();
    $message->Render();
}

require("include_pouet/menu.inc.php");
require_once("include_pouet/footer.php");
