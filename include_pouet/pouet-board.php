<?php

class PouetBoard extends BM_Class
{
    use PouetAPI { ToAPI as protected ToAPISuper; }
    public $id;
    public $name;
    public $sysop;
    public $phonenumber;
    public $addedDate;
    public $addedUser;
    public $telnetip;
    public $started;
    public $closed;

    public static function getTable()
    {
        return "boards";
    }
    public static function getFields()
    {
        return ["id","name","addedDate","addedUser"];
    }
    public static function getExtendedFields()
    {
        return ["sysop","phonenumber"];
    }
    public static function onAttach(&$node, &$query)
    {
        $node->attach($query, "addedUser", ["users as addedUser" => "id"]);
    }
    public function RenderLink()
    {
        return sprintf("<a href='boards.php?which=%d'>%s</a>", $this->id, _html($this->name));
    }

    public function ToAPI()
    {
        $array = $this->ToAPISuper();
        unset($array["addedUser"]);
        return $array;
    }
};

BM_AddClass("PouetBoard");
