<?php

// Based on an original Release by Rob Thomas (xrobau@gmail.com)
// Copyright Rob Thomas (2009)
// Extensive modifications by Michael Newton (miken32@gmail.com)
// Copyright 2016 Michael Newton
// Updated for PHP 8 compatibility

class Routepermissions extends \FreePBX\FreePBX_Helpers implements \FreePBX\BMO
{
    static $module = "routepermissions";
    protected $db;
    protected $FreePBX;

    public function __construct($freepbx = null)
    {
        if ($freepbx === null) {
            throw new \Exception("FreePBX Object Not Found");
        }
        $this->FreePBX = $freepbx;
        $this->db = $freepbx->Database;
    }

    public function getRightNav($request)
    {
        return "";
    }

    public function getActionBar($request)
    {
        return array();
    }

    public function ajaxRequest($command, &$setting){
        return method_exists(get_called_class(), $command);
    }

    public function ajaxHandler()
    {
        $request = $_REQUEST;
        $command = isset($_REQUEST["command"]) ? $_REQUEST["command"] : "";
        if (method_exists(get_called_class(), $command)) {
            $return = self::$command($request);
            if ($return) {
                return $return;
            } else {
                return array("status"=>false, "message"=>sprintf(_("Command %s failed"), $command));
            }
        }
        return array("status"=>false, "message"=>_("Unknown command"));
    }

    public function search($query = null, &$results = null)
    {
        return false;
    }

    public function install()
    {
        $columns = array(
            "exten"     => array("type"=>"integer"),
            "routename" => array("type"=>"string", "length"=>25),
            "allowed"   => array("type"=>"string", "length"=>3, "default"=>"YES", "notnull"=>false),
            "faildest"  => array("type"=>"string", "length"=>255, "default"=>"", "notnull"=>false),
            "prefix"    => array("type"=>"string", "length"=>16, "default"=>"", "notnull"=>false),
        );
        $indices = array(
            "idx_exten" => array("type"=>"index", "cols"=>array("exten")),
            "idx_route" => array("type"=>"index", "cols"=>array("routename")),
        );
        try {
            $table = $this->db->migrate("routepermissions");
            $table->modify($columns, $indices);
        } catch (\Exception $e) {
            die_freepbx(sprintf(_("Error updating routepermissions table: %s"), $e->getMessage()));
        }

        $stmt = $this->db->query("SELECT COUNT(exten) FROM routepermissions");
        if ($stmt->fetchColumn() > 0) {
            out(_("Found data, using existing route permissions"));
            try {
                $this->db->exec("UPDATE routepermissions SET prefix=faildest, faildest='' WHERE faildest RLIKE '^[a-d0-9*#]+$'");
            } catch (\PDOException $e) {
                die_freepbx(sprintf(_("Error updating routepermissions table: %s"), $e->getMessage()));
            }
        } else {
            outn(_("New install, populating default allow permission&hellip; "));
            $extens = array();
            $devices = \FreePBX::Core()->getAllUsersByDeviceType();
            foreach($devices as $exten) {
                if ($exten['id']) {
                    $extens[] = $exten['id'];
                }
            }
            try {
                $routes = \FreePBX::Core()->getAllRoutes();
                $query = "INSERT INTO routepermissions (exten, routename, allowed, faildest) VALUES (?, ?, 'YES', '')";
                $stmt = $this->db->prepare($query);
                foreach($extens as $ext) {
                    foreach ($routes as $r) {
                        $this->db->execute($stmt, array($ext, $r["name"]));
                    }
                }
            } catch (\Exception $e) {
                die_freepbx(sprintf(_("Error populating routepermissions table: %s"), $e->getMessage()));
            }
            out(_("complete"));
        }
    }

    public function uninstall()
    {
        $amp_conf = \FreePBX\Freepbx_conf::create();

        outn(_("Removing routepermissions database table&hellip; "));
        try {
            $this->db->query("DROP TABLE routepermissions");
            out(_("complete"));
        } catch (\PDOException $e) {
            out(sprintf(_("Error removing routepermissions table: %s"), $e->getMessage()));
        }

        outn(_("Removing AGI script&hellip; "));
        $agidir = $amp_conf->get("ASTAGIDIR");
        $result = @unlink("$agidir/checkperms.agi");
        if (!$result) {
            out(_("failed! File must be removed manually"));
        } else {
            out(_("complete"));
        }
    }

    public function backup()
    {
        return;
    }

    public function restore($backup)
    {
        return;
    }

    public function genConfig()
    {
        return;
    }

    public function writeConfig()
    {
        return;
    }

    public function myDialplanHooks()
    {
        return true;
    }

    public function doDialplanHook(&$ext, $engine, $pri)
    {
        if ($engine !== "asterisk") {
            return false;
        }

        if (is_array($ext->_exts)) {
            foreach ($ext->_exts as $context=>$extensions) {
                if (strncmp($context, "macro-dialout-", 14) === 0) {
                    $ext->splice($context, "s", 1, new \ext_agi("checkperms.agi"));
                    $ext->add($context, "barred", 1, new \ext_noop("Route administratively banned for this user."));
                    $ext->add($context, "barred", 2, new \ext_hangup());
                    $ext->add($context, "reroute", 1, new \ext_goto("1", "\${ARG2}", "from-internal"));
                }
            }
        }

        foreach (\FreePBX::Core()->getAllRoutes() as $route) {
            $context = "outrt-" . $route['route_id'];
            $routename = $route["name"];
            $routes = core_routing_getroutepatternsbyid($route["route_id"]);
            foreach ($routes as $rt) {
                $extension = $rt["match_pattern_prefix"] . $rt["match_pattern_pass"];
                if (preg_match("/\.|z|x|\[|\]/i", (string)$extension)) {
                    $extension = "_".$extension;
                }
                if (!empty($rt['match_cid'])) {
                    $cid = (preg_match("/\.|z|x|\[|\]/i", $rt['match_cid']))
                        ? '_'.$rt['match_cid']
                        : $rt['match_cid'];
                    $extension = $extension.'/'.$cid;
                }
                $ext->splice($context, $extension, 1, new \ext_setvar("__ROUTENAME", $routename));
            }
        }
    }

    public static function myGuiHooks() {
        return array("core");
    }

    public function doGuiHook(&$currentcomponent, $module) {
        $pagename   = isset($_REQUEST["display"]) ? $_REQUEST["display"] : "";
        $extdisplay = isset($_REQUEST["extdisplay"]) ? $_REQUEST["extdisplay"] : "";
        $action     = isset($_REQUEST["action"]) ? $_REQUEST["action"] : "";
        $section    = _("Outbound Route Permissions");
        $i          = 0;

        if (
            $module !== "core" || $action === "del" || empty($extdisplay) ||
            ($pagename !== "extensions" && $pagename !== "users")
        ) {
            return false;
        }

        $routes = $this->getRoutes();
        try {
            $stmt = $this->db->prepare("SELECT allowed, faildest, prefix FROM routepermissions WHERE routename = ? AND exten = ?");
        } catch (\PDOException $e) {
            return false;
        }
        
        if(is_array($routes)) {
            foreach ($routes as $route) {
                try {
                    $stmt->execute(array($route, $extdisplay));
                    $res = $stmt->fetch(\PDO::FETCH_NUM);
                } catch (\PDOException $e) {
                    continue;
                }
                if (is_array($res) && count($res) > 0) {
                    list($allowed, $faildest, $prefix) = $res;
                } else {
                    $allowed = "YES";
                    $faildest = "";
                    $prefix = ""; // Ensure variable is defined
                }
                if ($allowed === "NO" && !empty($prefix)) {
                    $allowed = "REDIRECT";
                }
                $route_html = htmlspecialchars($route);
                $yes = _("Allow");
                $no = _("Deny");
                $redirect = _("Redirect w/prefix");
                $i += 10;
                
                // Fixed JS string for compatibility
                $js = '$("input[name=" + this.name.replace(/^routepermissions_perm_(\d+)-(.*)$/, "routepermissions_prefix_$1-$2") + "]").val("").prop("disabled", (this.value !== "REDIRECT")).prop("required", (this.value === "REDIRECT"));var id=$("select[name=" + $("#" + this.name.replace(/^routepermissions_perm_(\d+)-(.*)$/, "routepermissions_faildest_$1-$2")).val() + "]").val("").change().prop("disabled", (this.value !== "NO")).data("id");$("select[data-id=" + id + "]").prop("disabled", (this.value !== "NO"))';
                
                $radio = new \gui_radio(
                    "routepermissions_perm_$i-$route",
                    array(
                        array("value"=>"YES", "text"=>$yes),
                        array("value"=>"NO", "text"=>$no),
                        array("value"=>"REDIRECT", "text"=>$redirect),
                    ),
                    $allowed,
                    sprintf(_("Allow access to %s"), $route_html),
                    "",
                    false,
                    htmlspecialchars($js),
                    "",
                    true
                );
                $currentcomponent->addguielem($section, $radio);

                $selects = new \gui_drawselects(
                    "routepermissions_faildest_$i-$route",
                    $i,
                    $faildest,
                    sprintf(_("Failure destination for %s"), $route_html),
                    "",
                    false,
                    "",
                    _("Use default"),
                    ($allowed !== "NO"),
                    ""
                );
                $currentcomponent->addguielem($section, $selects);

                $input = new \gui_textbox(
                    "routepermissions_prefix_$i-$route",
                    $prefix,
                    sprintf(_("Redirect prefix for %s"), $route_html),
                    "",
                    "",
                    "",
                    true,
                    0,
                    ($allowed !== "REDIRECT"),
                    false,
                    "",
                    true
                );
                $currentcomponent->addguielem($section, $input);
            }
        }
    }

    public static function myConfigPageInits() {
        return array("extensions", "users");
    }

    public function doConfigPageInit($module)
    {
        $pagename    = isset($_REQUEST["display"]) ? $_REQUEST["display"] : "index";
        $extdisplay  = isset($_REQUEST["extdisplay"]) ? $_REQUEST["extdisplay"] : null;
        $action      = isset($_REQUEST["action"]) ? $_REQUEST["action"] : null;
        $route_perms = array();
        if (empty($extdisplay) || empty($action)) {
            return false;
        }
        foreach ($_POST as $k=>$v) {
            if (!preg_match("/routepermissions_(faildest|perm|prefix)_\d+-(.*)/", $k, $matches)) {
                continue;
            }
            $route_name = $matches[2];
            if (!isset($route_perms[$route_name])) {
                $route_perms[$route_name] = array("faildest"=>null, "perms"=>"YES", "prefix"=>null);
            }
            switch ($matches[1]) {
                case "faildest":
                    $faildest_index = substr($v, 4);
                    $faildest_type = isset($_POST[$v]) ? $_POST[$v] : null;
                    if ($faildest_type && isset($_POST["$faildest_type$faildest_index"])) {
                        $route_perms[$route_name]["faildest"] = $_POST["$faildest_type$faildest_index"];
                    }
                    break;
                case "perm":
                    // Fix for PHP 8 explode/list error
                    if (strpos($v, '=') !== false) {
                        list($foo, $perm) = explode("=", $v, 2);
                    } else {
                        $perm = $v;
                    }
                    $route_perms[$route_name]["perms"] = $perm;
                    break;
                case "prefix":
                    $route_perms[$route_name]["prefix"] = $v;
                    break;
            }
        }
        if (count($route_perms) === 0) {
            return false;
        }

        switch ($action) {
            case "add":
            case "edit":
                try {
                    $stmt = $this->db->prepare("DELETE FROM routepermissions WHERE exten = ?");
                    $stmt->execute(array($extdisplay));
                    $stmt = $this->db->prepare("INSERT INTO routepermissions (exten, routename, allowed, faildest, prefix) VALUES(?, ?, ?, ?, ?)");
                } catch (\PDOException $e) {
                    return false;
                }
                foreach($route_perms as $route_name=>$data) {
                    if ($data["perms"] === "REDIRECT") {
                        $data["faildest"] = null;
                        $data["perms"] = "NO";
                        if (empty($data["prefix"])) {
                            $data["prefix"] = null;
                        }
                    } elseif ($data["perms"] === "NO") {
                        $data["prefix"] = null;
                    } else {
                        $data["perms"] = "YES";
                        $data["faildest"] = $data["prefix"] = null;
                    }
                    try {
                        $res = $stmt->execute(array(
                            $extdisplay,
                            $route_name, 
                            $data["perms"],
                            $data["faildest"],
                            $data["prefix"],
                        ));
                    } catch (\PDOException $e) {
                        return false;
                    }
                }
                break;
            case "del":
                try {
                    $stmt = $this->db->prepare("DELETE FROM routepermissions WHERE exten = ?");
                    $stmt->execute(array($extdisplay));
                } catch (\PDOException $e) {
                    return false;
                }
                break;
        }
    }

    public function showPage($request = null)
    {
        $cwd          = dirname(__FILE__);
        $message      = "";
        $errormessage = "";

        if ($request !== null) {
            foreach ($request as $k=>$perm) {
                if (strncmp($k, "permission_", 11) === 0) {
                    $route = substr($k, 11);
                    $redir = "";
                    $prefix = "";
                    switch ($perm) {
                    case "YES":
                        break;
                    case "NO":
                        if (isset($request["goto_$route"])) {
                            $type = $request["goto_$route"];
                            if (isset($request["${type}_$route"])) {
                                $redir = $request["${type}_$route"];
                            }
                        }
                        break;
                    case "REDIRECT":
                        $perm = "NO";
                        $prefix = isset($request["prefix_$route"]) ? trim($request["prefix_$route"]) : "";
                        if (empty($prefix)) {
                            $errormessage .= sprintf(
                                _("Redirect selected but redirect prefix missing for route %s - no action taken"),
                                htmlspecialchars($route)
                            );
                            $errormessage .= "<br/>";
                            continue 2;
                        }
                        break;
                    default:
                        continue 2;
                    }
                    $range = isset($request["range_$route"]) ? $request["range_$route"] : "";
                    try {
                        $result = $this->setRangePermissions($route, $range, $perm, $redir, $prefix);
                        if ($prefix) {
                            $message .= sprintf(
                                _("Route %s set to %s for supplied range %s using redirect prefix %s"),
                                htmlspecialchars($route),
                                $perm,
                                htmlspecialchars($range),
                                htmlspecialchars($prefix)
                            );
                            $message .= "<br/>";
                        } else {
                            $message .= sprintf(
                                _("Route %s set to %s for supplied range %s"),
                                htmlspecialchars($route),
                                $perm,
                                htmlspecialchars($range)
                            );
                            $message .= "<br/>";
                        }
                    } catch (\PDOException $e) {
                        $errormessage .= sprintf(
                            _("Database error, couldn't set permissions for route %s: %s"),
                            htmlspecialchars($route),
                            $e->getMessage()
                        );
                        $errormessage .= "<br/>";
                    }
                } elseif ($k == "update_default") {
                    $dest_type = isset($request["gotofaildest"]) ? $request["gotofaildest"] : "";
                    $dest = isset($request[$dest_type . "faildest"]) ? $request[$dest_type . "faildest"] : "";
                    try {
                        $result = $this->updateDefaultDest($dest);
                        $message = _("Default destination changed");
                    } catch (\PDOException $e) {
                        $errormessage = sprintf(
                            _("Database error, couldn't set default permissions: %s"),
                            $e->getMessage()
                        );
                    }
                }
            }
        }

        $viewdata = array(
            "module"=>self::$module,
            "message"=>$message,
            "errormessage"=>$errormessage,
            "rp"=>$this,
            "routes"=>$this->getRoutes(),
        );
        // Ensure the view file exists before loading
        if (file_exists("$cwd/views/settings13.php")) {
            include "$cwd/views/settings13.php";
        }
    }

    public function getRoutes()
    {
        $sql = "SELECT DISTINCT name FROM outbound_routes JOIN outbound_route_sequence USING (route_id) ORDER BY seq";
        try {
            $result = $this->db->query($sql);
            return $result->fetchAll(\PDO::FETCH_COLUMN, 0);
        } catch (\PDOException $e) {
            return array();
        }
    }

    public function getDefaultDest()
    {
        $sql = "SELECT faildest FROM routepermissions where exten = -1 LIMIT 1";
        try {
            $result = $this->db->query($sql);
            return $result->fetchColumn(0);
        } catch (\PDOException $e) {
            return false;
        }
    }

    public function updateDefaultDest($dest)
    {
        try {
            $sql = "DELETE FROM routepermissions WHERE exten = -1";
            $this->db->exec($sql);
            if (!empty($dest)) {
                $sql = "INSERT INTO routepermissions (exten, routename, faildest, prefix) VALUES ('-1', 'default', ?, '')";
                $stmt = $this->db->prepare($sql);
                $stmt->execute(array($dest));
            }
            return true;
        } catch (\PDOException $e) {
            throw $e;
        }
    }

    public function setRangePermissions($route, $range, $allowed, $faildest = "", $prefix = "") {
        $allowed = (strtoupper($allowed) === "NO") ? "NO" : "YES";
        
        $users_list = core_users_list();
        $sys_ext = is_array($users_list) ? array_map(function($u) {return $u[0];}, $users_list) : array();
        
        $range_extensions = (strtoupper($range) === strtoupper(_("All"))) ? $sys_ext : self::getRange($range);
        
        $extens = array_intersect($sys_ext, $range_extensions);
        
        if (count($extens) === 0) {
            return false;
        }
        try {
            $sql = "DELETE FROM routepermissions WHERE exten=? AND routename=?";
            $stmt1 = $this->db->prepare($sql);
            $sql = "INSERT INTO routepermissions (exten, routename, allowed, faildest, prefix) VALUES (?, ?, ?, ?, ?)";
            $stmt2 = $this->db->prepare($sql);
            foreach ($extens as $ext) {
                $stmt1->execute(array($ext, $route));
                $stmt2->execute(array($ext, $route, $allowed, $faildest, $prefix));
            }
            return true;
        } catch (\PDOException $e) {
            throw $e;
        }
    }

    private static function getRange($range_str) {
        $range_out = array();
        // Fix for PHP 8 passing null to str_replace
        $range_str = (string)$range_str;
        $ranges = explode(",", str_replace(" ", "", $range_str));

        foreach($ranges as $range) {
            if (is_numeric($range)) {
                $range_out[] = $range;
            } elseif (strpos($range, "-")) {
                list($start, $end) = explode("-", $range);
                if (is_numeric($start) && is_numeric($end) && $start < $end) {
                    for ($i = $start; $i <= $end; $i++) {
                        $range_out[] = $i;
                    }
                }
            }
        }
        return array_unique($range_out, SORT_NUMERIC);
    }
}
