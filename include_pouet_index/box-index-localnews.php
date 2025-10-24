<?php

class PouetBoxIndexLocalNews extends PouetBox
{
    public $data;
    public $prod;
    public $link;
    public $title;
    public $content;
    public $timestamp;
    public $who;

    public function __construct()
    {
        parent::__construct();
        $this->uniqueID = "pouetbox_newsbox";
        $this->title = "news from pouët.net";
    }

    public function Render()
    {
        echo "<div class='pouettbl ".$this->uniqueID."'>\n";
        echo " <h3><a href='".$this->link."'>"._html($this->title)."</a></h3>\n";
        echo " <div class='content'>\n".str_replace("<br>", "<br/>", $this->content)."\n</div>\n";
        echo " <div class='foot'>lobstregated by ". $this->who ." on ".($this->timestamp)."</div>\n";
        echo "</div>\n";
    }
};

class PouetBoxIndexLocalNewsBoxes extends PouetBoxCachable
{
    use PouetFrontPage;
    public $limit;
    public $newsArray;
    public function __construct()
    {
        parent::__construct();

        $this->title = "news from pouët.net";
        $this->cacheTime = 0;

        $this->uniqueID = "pouetbox_localnews";
        $this->limit = 5;
    }

    public function LoadFromDB()
    {
        $s = new BM_query();
        $s->AddField("localnews.id as id");
        $s->AddField("localnews.content as content");
        $s->AddField("localnews.quand as timestamp");
        $s->AddField("localnews.title as title");
        $s->AddField("localnews.url as url");
        $s->AddTable("localnews");
        $s->attach(array("localnews" => "who"), array("users as poster" => "id"));
        $s->AddOrder("quand desc");
        $s->SetLimit(POUET_CACHE_MAX);

        $this->newsArray = $s->perform();
    }

    public function LoadFromCachedData($data)
    {
        $this->newsArray = unserialize($data);
    }

    public function GetCacheableData()
    {
        return serialize($this->newsArray);
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
          "limit" => array("name" => "number of local news items visible","default" => 5,"min" => 1,"max" => 10),
        );
    }

    public function Render()
    {
        $p = new PouetBoxIndexLocalNews();

        for ($i = 0; $i < min(count($this->newsArray), $this->limit); $i++) {
            $p->content = $this->newsArray[$i]->content;
            $p->title = $this->newsArray[$i]->title;
            $p->link = $this->newsArray[$i]->url;
            $p->timestamp=$this->newsArray[$i]->timestamp;
            $p->who=$this->newsArray[$i]->poster->PrintLinkedAvatar() . $this->newsArray[$i]->poster->PrintLinkedName();
            $p->Render();
        }

    }
};

$indexAvailableBoxes[] = "LocalNewsBoxes";
