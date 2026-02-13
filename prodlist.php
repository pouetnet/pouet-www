<?php

require_once("bootstrap.inc.php");

class PouetBoxProdlist extends PouetBox
{
    public $id;
    public $group;
    public $perPage;
    public $page;
    public $prods;
    public $count;

    public function __construct()
    {
        parent::__construct();
        $this->uniqueID = "pouetbox_prodlist";
    }

    public function LoadFromDB()
    {
        $this->perPage = get_setting("prodlistprods");

        $this->page = (int)max(1, (int)@$_GET["page"]);

        // double query for optimization
        
        // First pass: get IDs first (uses indices, then actual data restricted to IDs)
        $sqlGetIDs = new BM_Query();
        $sqlGetIDs->AddField("prods.id");
        $sqlGetIDs->AddTable("prods");
        $sqlGetIDs->AddJoin("LEFT", "parties as prods_party", "prods_party.id = prods.party");
        
        if (is_array(@$_GET["type"])) {
            $cond = array();
            foreach ($_GET["type"] as $type) {
                $cond[] = sprintf_esc("FIND_IN_SET('%s',prods.type)", $type);
            }
            $sqlGetIDs->AddWhere(implode(" OR ", $cond));
        }
        if (is_array(@$_GET["platform"])) {
            global $PLATFORMS;
            $platforms = array();
            foreach ($_GET["platform"] as $platform) {
                foreach ($PLATFORMS as $k => $v) {
                    if ($v["name"] == $platform) {
                        $platforms[] = $k;
                    }
                }
            }
            if ($platforms) {
                $sqlGetIDs->AddJoin("LEFT", "prods_platforms as pp", "pp.prod = prods.id");
                $sqlGetIDs->AddWhere(sprintf_esc("pp.platform in (%s)", implode(",", $platforms)));
            }
        }
        if (is_array(@$_GET["group"])) {
            foreach ($_GET["group"] as $v) {
                if ($v) {
                    $sqlGetIDs->AddWhere(sprintf_esc("(prods.group1 = %d OR prods.group2 = %d OR prods.group3 = %d)", $v, $v, $v));
                }
            }
        }
        if (@$_GET["releaseDateFrom"]) {
            $sqlGetIDs->AddWhere(sprintf_esc("prods.releaseDate >= '%s'", $_GET["releaseDateFrom"]));
        }
        if (@$_GET["releaseDateUntil"]) {
            $sqlGetIDs->AddWhere(sprintf_esc("prods.releaseDate <= '%s'", $_GET["releaseDateUntil"]));
        }
        if (@$_GET["addedDateFrom"]) {
            $sqlGetIDs->AddWhere(sprintf_esc("prods.addedDate >= '%s'", $_GET["addedDateFrom"]));
        }
        if (@$_GET["addedDateUntil"]) {
            $sqlGetIDs->AddWhere(sprintf_esc("prods.addedDate <= '%s'", $_GET["addedDateUntil"]));
        }
        if (@$_GET["party"]) {
            $sqlGetIDs->AddWhere(sprintf_esc("party = %d", $_GET["party"]));
        }
        if (@$_GET["partyYear"]) {
            $sqlGetIDs->AddWhere(sprintf_esc("party_year = %d", $_GET["partyYear"]));
        }
        if (@$_GET["partyRank"]) {
            $sqlGetIDs->AddWhere(sprintf_esc("party_place = %d", $_GET["partyRank"]));
        }
        if (@$_GET["partyRankHigher"]) {
            $sqlGetIDs->AddWhere(sprintf_esc("party_place <= %d", $_GET["partyRankHigher"]));
        }
        if (@$_GET["partyRankLower"]) {
            $sqlGetIDs->AddWhere(sprintf_esc("party_place >= %d", $_GET["partyRankLower"]));
        }

        $this->SetQueryOrder($sqlGetIDs);

        $sqlGetIDs->SetLimit($this->perPage, (int)(($this->page - 1) * $this->perPage));

        $ids = $sqlGetIDs->performWithCalcRows($this->count);
        if ($ids) {
          $ids = array_map(function($i){ return (int)$i->id; }, $ids);

          // Second pass: Get actual prod data
          $sqlProdData = new BM_Query("prods");
          $sqlProdData->AddWhere("prods.id IN (".implode(",",$ids).")");
          $this->SetQueryOrder($sqlProdData);
          $this->prods = $sqlProdData->perform();
        }
        else {
          $this->prods = array();
        }
        PouetCollectPlatforms($this->prods);
        PouetCollectAwards($this->prods);
    }
    
    private function SetQueryOrder(&$query)
    {
        $dir = "DESC";
        if (@$_GET["reverse"]) {
            $dir = "ASC";
        }
        switch (@$_GET["order"]) {
            case "type": $query->AddOrder("prods.type ".$dir);
                break;
            case "name": $query->AddOrder("prods.name ".$dir);
                break;
            case "group": $query->AddOrder("prods.group1 ".$dir);
                $query->AddOrder("prods.group2 ".$dir);
                $query->AddOrder("prods.group3 ".$dir);
                break;
            case "party": $query->AddOrder("prods_party.name ".$dir);
                $query->AddOrder("prods.party_year ".$dir);
                $query->AddOrder("prods.party_place ".$dir);
                break;
            case "thumbup": $query->AddOrder("prods.voteup ".$dir);
                break;
            case "thumbpig": $query->AddOrder("prods.votepig ".$dir);
                break;
            case "thumbdown": $query->AddOrder("prods.votedown ".$dir);
                break;
            case "thumbdiff": $query->AddOrder("(prods.voteup - prods.votedown) ".$dir);
                break;
            case "avg": $query->AddOrder("prods.voteavg ".$dir);
                break;
            case "views": $query->AddOrder("prods.views ".$dir);
                break;
            case "added": $query->AddOrder("prods.addedDate ".$dir);
                break;
            case "random": $query->AddOrder("RAND()");
                break;
        }
        $query->AddOrder("prods.releaseDate ".$dir);
        $query->AddOrder("prods.addedDate ".$dir);
    }

    public function Render()
    {
        echo "<table id='".$this->uniqueID."' class='boxtable pagedtable'>\n";
        $headers = array(
          "type" => "type",
          "name" => "prodname",
          "platform" => "platform",
          "group" => "group",
          "party" => "release party",
          "release" => "release date",
          "added" => "added",
          "thumbup" => "<span class='rulez' title='rulez'>rulez</span>",
          "thumbpig" => "<span class='isok' title='piggie'>piggie</span>",
          "thumbdown" => "<span class='sucks' title='sucks'>sucks</span>",
          "avg" => "avg",
          "views" => "popularity",
        );
        echo "<tr class='sortable'>\n";
        foreach ($headers as $key => $text) {
            $out = sprintf(
                "<th><a href='%s' class='%s%s %s'>%s</a></th>\n",
                adjust_query_header(array("order" => $key)),
                @$_GET["order"] == $key ? "selected" : "",
                (@$_GET["order"] == $key && @$_GET["reverse"]) ? " reverse" : "",
                "sort_".$key,
                $text
            );
            if ($key == "type" || $key == "name") {
                $out = str_replace("</th>", "", $out);
            }
            if ($key == "platform" || $key == "name") {
                $out = str_replace("<th>", " ", $out);
            }
            echo $out;
        }
        echo "</tr>\n";

        foreach ($this->prods as $p) {
            echo "<tr>\n";

            echo "<td>\n";
            echo $p->RenderTypeIcons();
            echo $p->RenderPlatformIcons();
            echo "<span class='prod'>".$p->RenderLink()."</span>\n";
            echo $p->RenderAccolades();
            echo "</td>\n";

            echo "<td>\n";
            echo $p->RenderGroupsShortProdlist();
            echo "</td>\n";

            echo "<td>\n";
            if ($p->placings) {
                echo $p->placings[0]->PrintResult();
            }
            echo "</td>\n";

            echo "<td class='date'>".$p->RenderReleaseDate()."</td>\n";
            echo "<td class='date'>".$p->RenderAddedDate()."</td>\n";

            echo "<td class='votes'>".$p->voteup."</td>\n";
            echo "<td class='votes'>".$p->votepig."</td>\n";
            echo "<td class='votes'>".$p->votedown."</td>\n";
            echo "<td class='votesavg'>".$p->RenderAvg()."</td>\n";

            $pop = (int)calculate_popularity($p->views);
            echo "<td>".progress_bar_solo($pop, $pop."%")."</td>\n";

            echo "</tr>\n";
        }

        echo "<tr>\n";
        echo "<td class='nav' colspan=".(count($headers) - 2).">\n";

        $numPages = ceil($this->count / $this->perPage);
        if ($this->page > 1) {
            echo "  <div class='prevpage'><a href='".adjust_query(array("page" => ($this->page - 1)))."'>previous page</a></div>\n";
        }
        if ($this->page < $numPages) {
            echo "  <div class='nextpage'><a href='".adjust_query(array("page" => ($this->page + 1)))."'>next page</a></div>\n";
        }

        echo "  <select name='page'>\n";
        for ($x = 1; $x <= $numPages; $x++) {
            printf("    <option value='%d'%s>%d</option>\n", $x, $x == $this->page ? " selected='selected'" : "", $x);
        }
        echo "  </select>\n";
        echo "  <input type='submit' value='Submit'/>\n";
        echo "</td>\n";
        echo "</tr>\n";
        echo "</table>\n";
    }
};

class PouetBoxProdlistSelectors extends PouetBox
{
    public $types;
    public function Load()
    {
        $row = SQLLib::selectRow("DESC prods type");
        $this->types = enum2array($row->Type);
    }
    public function Render()
    {
        global $PLATFORMS;
        echo "<table id='pouetbox_prodlist_selector' class='boxtable'>\n";
        echo "<tr>\n";
        echo "  <th colspan='2'>selection</th>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "  <td>\n";
        echo "  type :\n";
        echo "  <select name='type[]' multiple='multiple' size='10'>\n";
        if (!@$_GET["type"]) {
            $_GET["type"] = array();
        }
        foreach ($this->types as $v) {
            echo "  <option".((!is_array($_GET["type"]) || array_search($v, $_GET["type"]) === false) ? "" : " selected='selected'").">".$v."</option>\n";
        }
        echo "  </select>\n";
        echo "  </td>\n";

        echo "  <td>\n";
        echo "  platform :\n";
        echo "  <select name='platform[]' multiple='multiple' size='10'>\n";
        if (!@$_GET["platform"]) {
            $_GET["platform"] = array();
        }
        $plat = array();
        foreach ($PLATFORMS as $v) {
            $plat[] = $v["name"];
        }
        usort($plat, "strcasecmp");
        foreach ($plat as $v) {
            echo "  <option".((!is_array($_GET["platform"]) || array_search($v, $_GET["platform"]) === false) ? "" : " selected='selected'").">".$v."</option>\n";
        }
        echo "  </select>\n";
        echo "  </td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "  <td class='foot' colspan='2'><input type='submit' value='Submit'/></td>\n";
        echo "</tr>\n";
        echo "</table>\n";
    }
};

///////////////////////////////////////////////////////////////////////////////

$q = new PouetBoxProdlistSelectors();
$q->Load();

$p = new PouetBoxProdlist();
$p->Load();
$TITLE = "prodlist";
if ($p->page > 1) {
    $TITLE .= " :: page ".(int)$p->page;
}


require_once("include_pouet/header.php");
require("include_pouet/menu.inc.php");

echo "<div id='content'>\n";
echo "<form action='prodlist.php' method='get'>\n";

foreach ($_GET as $k => $v) {
    if ($k != "page" && $k != "type" && $k != "platform") { // hidden fields only
        if (is_array($v)) {
            foreach ($v as $k2 => $v2) {
                echo "<input type='hidden' name='"._html($k)."[]' value='"._html($v2)."'/>\n";
            }
        } else {
            echo "<input type='hidden' name='"._html($k)."' value='"._html($v)."'/>\n";
        }
    }
}
if ($q) {
    $q->Render();
}
if ($p) {
    $p->Render();
}
echo "</form>\n";
echo "</div>\n";

require("include_pouet/menu.inc.php");
require_once("include_pouet/footer.php");
