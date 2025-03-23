<?php

trait PouetAPI
{
    public function ToAPI()
    {
        $array = get_object_vars($this);
        foreach ($array as $k => $v) {
            $array[$k] = PouetAPIfy($this->$k);
        }
        return $array;
    }
}

function PouetAPIfy($o)
{
    if (is_object($o)) {
        if (has_trait($o, "PouetAPI")) {
            return $o->ToAPI();
        } else {
            foreach (get_object_vars($o) as $k => $v) {
                $o->$k = PouetAPIfy($o->$k);
            }
        }
    } elseif (is_array($o)) {
        foreach ($o as $k => $v) {
            $o[$k] = PouetAPIfy($o[$k]);
        }
    }
    return $o;
}
