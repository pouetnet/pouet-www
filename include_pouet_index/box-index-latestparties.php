<?php

class PouetBoxIndexLatestParties extends PouetBoxCachable
{
    use PouetFrontPage;
    public $data;
    public $prods;
    public $limit;
    public function __construct()
    {
        parent::__construct();
        $this->uniqueID = "pouetbox_latestparties";
        $this->title = "latest parties";

        $this->limit = 5;
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
          "limit" => array("name" => "number of parties visible","default" => 5,"min" => 1,"max" => POUET_CACHE_MAX),
        );
    }

    public function LoadFromDB()
    {
        $s = new BM_Query("parties");
        $s->AddField("count(*) as prod_count");
        $s->AddField("prods.party_year");
        $s->AddJoin("", "prods", "prods.party=parties.id");
        $s->AddWhere(sprintf_esc("parties.id != %d", NO_PARTY_ID));
        $s->AddGroup("prods.party, prods.party_year");
        $s->AddOrder("prods.releaseDate desc, prods.id desc");
        $s->SetLimit(POUET_CACHE_MAX);
        $this->data = $s->perform();
    }

    public function RenderBody()
    {
        echo "<ul class='boxlist'>\n";
        $n = 0;
        foreach ($this->data as $p) {
            echo "<li>\n";
            echo " <span>";
            echo $p->PrintShort($p->party_year);
            if (file_exists($p->GetResultsLocalFileName($p->party_year))) {
                echo " ".$p->RenderResultsLink($p->party_year);
            }
            echo " </span>";
            echo " <span class='releasecount'>".$p->prod_count."</span>";
            echo "</li>\n";
            if (++$n == $this->limit) {
                break;
            }
        }
        echo "</ul>\n";
    }
    public function RenderFooter()
    {
        echo "  <div class='foot'><a href='parties.php'>more</a>...</div>\n";
        echo "</div>\n";
    }
};

$indexAvailableBoxes[] = "LatestParties";
