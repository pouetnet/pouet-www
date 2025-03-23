<?php

class PouetBoxIndexFeedWanted extends PouetBoxCachable
{
    use PouetFrontPage;
    public $limit;
    public $rss;
    public $rssData;
    public function __construct()
    {
        parent::__construct();
        $this->uniqueID = "pouetbox_feedwanted";
        $this->title = "wanted !";

        $this->cacheTime = 60 * 60;

        $this->rss = class_exists("DomDocument") ? new lastRSS(array(
          "cacheTime" => 5 * 60, // in seconds
          "dateFormat" => "Y-m-d",
          "stripHtml" => false,
        )) : null;
        if ($this->rss) {
            $this->rss->setItemTags(array(
              "link",
              "title",
              "pubDate",
              "wanted:demand",
              "wanted:area",
            ));
        }

        $this->limit = 5;
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
          "limit" => array("name" => "number of posts visible","default" => 5,"min" => 1,"max" => 10),
        );
    }

    public function LoadFromCachedData($data)
    {
        $this->rssData = unserialize($data);
    }

    public function GetCacheableData()
    {
        return serialize($this->rssData);
    }

    public function LoadFromDB()
    {
        $this->rssData = $this->rss ? $this->rss->get('https://wanted.scene.org/rss/?random=weighted') : array();
    }

    public function RenderBody()
    {
        if (@!$this->rssData['items']) {
            return;
        }
        echo "<ul class='boxlist'>\n";
        for ($i = 0; $i < min(count($this->rssData['items']), $this->limit); $i++) {
            $time = strtotime($this->rssData['items'][$i]['pubDate']);
            echo "<li>\n";
            if ((time() - $time) < 60 * 60 * 24 * 30) {
                echo "New! ";
            }
            echo "<a href='".$this->rssData['items'][$i]['link']."'>".$this->rssData['items'][$i]['title']."</a> ";
            echo "</li>\n";
        }
        echo "</ul>\n";
    }
    public function RenderFooter()
    {
        echo "  <div class='foot'><a href='https://wanted.scene.org/'>more at wanted !</a>...</div>\n";
        echo "</div>\n";
    }
};

$indexAvailableBoxes[] = "FeedWanted";
