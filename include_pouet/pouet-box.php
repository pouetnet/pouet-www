<?php

class PouetBox
{
    public $title;
    public $uniqueID;
    public $logz;
    public $classes;
    public function __construct() // constructor
    {
        $this->title = "";
        $this->uniqueID = "pouetbox";
        $this->classes = array();
    }

    public function GetData() // override
    {
        return null;
    }

    public function RenderHeader()
    {
        echo "\n\n";
        echo "<div class='pouettbl".($this->classes ? (" ".implode(" ", $this->classes)) : "")."' id='".$this->uniqueID."'>\n";
        $this->RenderTitle();
    }

    public function RenderTitle()
    {
        echo " <h2>".$this->title."</h2>\n";
    }

    public function RenderBody()
    {
        echo " <div class='content'>\n";
        $this->RenderContent();
        echo " </div>\n";
    }

    public function RenderContent() // override
    {
        echo "content comes here";
    }

    public function RenderFooter()
    {
        echo "</div>\n";
    }

    public function Render()
    {
        global $timer;
        $timer[$this->uniqueID." render"]["start"] = microtime_float();
        $this->RenderHeader();
        $this->RenderBody();
        $this->RenderFooter();
        $timer[$this->uniqueID." render"]["end"] = microtime_float();
    }

    public function RenderBuffered()
    {
        ob_start();
        $this->Render();
        return ob_get_clean();
    }

    public function IsVisibleLoggedOut()
    {
        return true;
    }

    public function LoadFromDB() // override
    {
    }

    public function Load()
    {
        global $timer;
        $timer[$this->uniqueID." load"]["start"] = microtime_float();
        $this->LoadFromDB();
        $timer[$this->uniqueID." load"]["end"] = microtime_float();
    }
}

trait PouetForm
{
    public function Validate($data)
    {
        return array();
    }

    public function Commit($data)
    {
        return array();
    }

    public function ParsePostMessage($data)
    {
        $errors = $this->Validate($data);

        if ($errors && count($errors)) {
            return $errors;
        }

        return $this->Commit($data);
    }

    public function GetInsertionID()
    {
        return 0;
    }
}

trait PouetFrontPage
{
    public function SetParameters($data)
    {
    }
    public function GetParameterSettings()
    {
        return array();
    }
}

class PouetBoxCachable extends PouetBox
{
    public $cacheTime;
    public function __construct()
    {
        parent::__construct();
        $this->cacheTime = 60 * 60 * 24;
    }
    public function GetCacheableData() // override
    {
        return "";
    }

    public function LoadFromCachedData($data) // override
    {
    }

    public function GetCacheFilename()
    {
        return POUET_ROOT_LOCAL . "/cache/".$this->uniqueID.".cache";
    }

    public function SaveToCache()
    {
        $s = $this->GetCacheableData();
        if ($s !== false) {
            file_put_contents($this->GetCacheFilename(), $s);
        }
    }

    public function GetCachedData()
    {
        return file_get_contents($this->GetCacheFilename());
    }

    public function IsCacheValid()
    {
        $f = $this->GetCacheFilename();
        return (file_exists($f) && ((time() - filemtime($f)) < $this->cacheTime));
    }

    public function ForceCacheUpdate()
    {
        $this->LoadFromDB();
        $this->SaveToCache();
    }
    public function Load($cached = false)
    {
        global $timer;
        $timer[$this->uniqueID." load"]["start"] = microtime_float();
        if ($cached) {
            if ($this->IsCacheValid()) {
                $this->logz .= "<!-- loading ".$this->uniqueID." from cache... -->\n";
                $this->LoadFromCachedData($this->GetCachedData());
            } else {
                $this->LoadFromDB();
                $this->SaveToCache();
            }
        } else {
            $this->LoadFromDB();
        }
        $timer[$this->uniqueID." load"]["end"] = microtime_float();
    }

};
