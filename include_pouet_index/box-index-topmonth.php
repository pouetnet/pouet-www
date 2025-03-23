<?php

class PouetBoxIndexTopMonth extends PouetBoxCachable
{
    use PouetFrontPage;
    public $data;
    public $prods;
    public $limit;
    public function __construct()
    {
        parent::__construct();
        $this->uniqueID = "pouetbox_topmonth";
        $this->title = "top of the month";

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
          "limit" => array("name" => "number of prods visible","default" => 10,"min" => 1,"max" => POUET_CACHE_MAX),
        );
    }

    public function LoadFromDB()
    {
        $s = new BM_Query("prods");
        $s->AddOrder("(prods.views/GREATEST((sysdate()-prods.addedDate)/100000, 0.25)+prods.views)*prods.voteavg*prods.voteup DESC");
        $s->AddWhere("prods.addedDate > DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $s->SetLimit(POUET_CACHE_MAX);
        $this->data = $s->perform();
        PouetCollectPlatforms($this->data);
    }

    public function RenderBody()
    {
        echo "<ul class='boxlist'>\n";
        $n = 0;
        foreach ($this->data as $p) {
            echo "<li>\n";
            $p->RenderAsEntry();
            echo "</li>\n";
            if (++$n == $this->limit) {
                break;
            }
        }
        echo "</ul>\n";
    }
    public function RenderFooter()
    {
        echo "  <div class='foot'><a href='toplist.php?dateFrom=".date("Y")."-01-01'>top of the year</a> :: <a href='toplist.php?days=30'>more</a>...</div>\n";
        echo "</div>\n";
    }
};

$indexAvailableBoxes[] = "TopMonth";
