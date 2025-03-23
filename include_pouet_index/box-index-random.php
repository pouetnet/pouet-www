<?php

class PouetBoxIndexRandom extends PouetBox
{
    public $data;
    public $prod;
    public function __construct()
    {
        parent::__construct();
        $this->uniqueID = "pouetbox_random";
        $this->title = "a random prod";
    }

    public function LoadFromCachedData($data)
    {
        $this->data = unserialize($data);
    }

    public function GetCacheableData()
    {
        return serialize($this->data);
    }

    public function LoadFromDB()
    {
        $id = SQLLib::SelectRow("SELECT prods.id as id FROM prods ORDER BY RAND() LIMIT 1")->id;

        $s = new BM_Query("prods");
        $s->AddWhere(sprintf_esc("prods.id = %d", $id));
        $s->SetLimit(1);
        $data = $s->perform();
        $this->data = reset($data);

        $a = array(&$this->data);
        PouetCollectPlatforms($a);
    }

    public function RenderContent()
    {
        if ($this->data) {
            $this->data->RenderAsEntry();
        }
    }
    public function RenderFooter()
    {
        echo "</div>\n";
    }
};

$indexAvailableBoxes[] = "Random";
