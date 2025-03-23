<?php

class PouetBoxIndexAffilButton extends PouetBoxCachable
{
    public $data;
    public function __construct()
    {
        parent::__construct();
        $this->uniqueID = "pouetbox_affilbutton";
        $this->title = "affiliate buttons";
    }
    //use PouetFrontPage;

    public function Load($cached = false)
    {
        $s = new SQLSelect();
        $s->AddTable("buttons");
        $s->AddOrder("rand()");
        $s->AddWhere("dead = 0");
        $s->SetLimit("1");
        $this->data = SQLLib::SelectRow($s->GetQuery());

        $this->title = $this->data->type;
    }

    public function RenderContent()
    {
        echo "<a href='"._html($this->data->url)."'><img src='".POUET_CONTENT_URL."buttons/".$this->data->img."' title='"._html($this->data->alt)."' alt='"._html($this->data->alt)."'/></a>";
    }

    public function RenderFooter()
    {
        echo " <div class='foot'><a href='buttons.php'>more</a>...</div>";
        echo "</div>";
    }
};

$indexAvailableBoxes[] = "AffilButton";
