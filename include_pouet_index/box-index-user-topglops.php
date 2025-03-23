<?php

class PouetBoxIndexTopGlops extends PouetBoxCachable
{
    use PouetFrontPage;
    public $data;
    public $prods;
    public $limit;
    public function __construct()
    {
        parent::__construct();
        $this->uniqueID = "pouetbox_topglops";
        $this->title = "top of the glöps";

        $this->limit = 10;
    }

    public function LoadFromCachedData($data)
    {
        $this->data = unserialize($data);
    }

    public function GetCacheableData()
    {
        return serialize($this->data);
    }
    public function SetParameters($data)
    {
        if (isset($data["limit"])) {
            $this->limit = $data["limit"];
        }
    }
    public function GetParameterSettings()
    {
        return array(
          "limit" => array("name" => "number of users visible","default" => 10,"min" => 1,"max" => POUET_CACHE_MAX),
        );
    }

    public function LoadFromDB()
    {
        $s = new BM_Query("users");
        $s->AddOrder("users.glops desc");
        $s->SetLimit(POUET_CACHE_MAX);
        $this->data = $s->perform();
    }

    public function RenderBody()
    {
        echo "<ul class='boxlist'>\n";
        $n = 0;
        foreach ($this->data as $p) {
            echo "<li>\n";
            echo $p->PrintLinkedAvatar()." ";
            echo "<span class='prod'>".$p->PrintLinkedName()."</span>\n";
            echo "<span class='group'>:: ".$p->glops." glöps</span>\n";
            echo "</li>\n";
            if (++$n == $this->limit) {
                break;
            }
        }
        echo "</ul>\n";
    }
    public function RenderFooter()
    {
        echo "  <div class='foot'><a href='userlist.php'>more</a>...</div>\n";
        echo "</div>\n";
    }
};

$indexAvailableBoxes[] = "TopGlops";
