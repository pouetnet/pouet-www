<?php

class PouetBoxIndexNews extends PouetBox
{
    public $data;
    public $prod;
    public $link;
    public $title;
    public $content;
    public $timestamp;
    public function __construct()
    {
        parent::__construct();
        $this->uniqueID = "pouetbox_newsbox";
        $this->title = "news box";
    }

    public function Render()
    {
        echo "<div class='pouettbl ".$this->uniqueID."'>\n";
        echo " <h3><a href='".$this->link."'>"._html($this->title)."</a></h3>\n";
        echo " <div class='content'>\n".str_replace("<br>", "<br/>", $this->content)."\n</div>\n";
        echo " <div class='foot'>lobstregated at <a href='https://news.scene.org/'>Scene.org</a> on ".($this->timestamp)."</div>\n";
        echo "</div>\n";
    }
};

class PouetBoxIndexNewsBoxes extends PouetBoxCachable
{
    use PouetFrontPage;
    public $rss;
    public $limit;
    public $rssNews;
    public function __construct()
    {
        parent::__construct();

        $this->title = "news!";

        $this->cacheTime = 60 * 15;

        $this->uniqueID = "pouetbox_news";
        $this->rss = class_exists("DomDocument") ? new lastRSS(array(
          "cacheTime" => 5 * 60, // in seconds
          "dateFormat" => "Y-m-d",
          "stripHtml" => false,
        )) : null;

        $this->limit = 5;
    }

    public function LoadFromDB()
    {
        $this->rssNews = $this->rss ? $this->rss->get('https://news.scene.org/feeds/rss/') : array();
    }

    public function LoadFromCachedData($data)
    {
        $this->rssNews = unserialize($data);
    }

    public function GetCacheableData()
    {
        return serialize($this->rssNews);
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
          "limit" => array("name" => "number of news items visible","default" => 5,"min" => 1,"max" => 10),
        );
    }

    public function Render()
    {
        if (@!$this->rssNews['items']) {
            printf('Error: Unable to open news feed !');
        } else {
            $p = new PouetBoxIndexNews();
            for ($i = 0; $i < min(count($this->rssNews['items']), $this->limit); $i++) {
                if (!$this->rssNews['items'][$i]['title']) {
                    continue;
                }
                $p->content = $this->rssNews['items'][$i]['description'];
                $p->title = $this->rssNews['items'][$i]['title'];
                $p->link = $this->rssNews['items'][$i]['link'];
                $p->timestamp = $this->rssNews['items'][$i]['pubDate'];
                $p->Render();
            }
        }
    }
};

$indexAvailableBoxes[] = "NewsBoxes";
