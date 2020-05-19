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
    case "time":
        cmd_time();
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
    return wrap_into_xml($res);
}

function cmd_time(){

    date_default_timezone_set('UTC');

    $t = elphel_get_fpga_time();
    if (!isset($_GET['ts'])){
        //date +%s will report system time
        //print(exec("date +%s"));
        print("Camera time: $t");
    }else{
        // ts is in ms
        $ts_s  = substr($_GET['ts'],0,10);
        $ts_ms = substr($_GET['ts'],-3);
        //$ts = $_GET['ts']/1000;
        $ts_formatted = date("Y-m-d H:i:s.$ts_ms",$ts_s);
        print("Your time:   $ts_s.$ts_ms ($ts_formatted)\nCamera time: $t\n");

        if (abs($ts_s-$t)>24*3600){
            elphel_set_fpga_time($_GET['ts']/1000);
            exec("date -s $ts_formatted");
            exec("hwclock --systohc");
            print("Timestamps differ by more than 24h. Camera and fpga time updated.\n");
        }
    }
}

function wrap_into_xml($s){
    $xml  = "<?xml version='1.0' standalone='yes'?>\n";
    $xml .= "<Document>\n";
    $xml .= $s;
    $xml .= "</Document>\n";
    return $xml;
}

?>
