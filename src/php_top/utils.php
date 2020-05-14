<?php
/**
 * @copyright Copyright (C) 2020 Elphel, Inc.
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * @author Oleg Dzhimiev <oleg@elphel.com>
 * @brief Access info or perform actions using GET requests
 */

include "include/elphel_functions_include.php";

$cmd = "donothing";

if (isset($_GET['cmd']))
  $cmd = $_GET['cmd'];
else if (isset($argv[1]))
  $cmd = $argv[1];

// allow CORS
header('Access-Control-Allow-Origin: *');

switch($cmd){
  case "sensors":
    print(cmd_sensors());
    break;
  default:
    print("OK");
}

function cmd_sensors(){
    $p0 = 2323;
    $sensors = get_sensors();

    $res = "\t<camera ip='".$_SERVER['SERVER_ADDR']."'>\n";
    foreach($sensors as $i => $sensor){
        $p = $p0+$i;
        $res .= "\t\t<port index='$i' port='$p'>$sensor</port>\n";
    }
    $res .= "\t</camera>\n";

    $xml  = "<?xml version='1.0' standalone='yes'?>\n";
    $xml .= "<Document>\n";
    $xml .= $res;
    $xml .= "</Document>\n";

    return $xml;
}

?>
