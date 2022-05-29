<?php

include "../include/elphel_functions_include.php";

$cmd = "donothing";

if (isset($_GET['cmd']))
  $cmd = $_GET['cmd'];
else if (isset($argv[1]))
  $cmd = $argv[1];

$config = "/var/volatile/html/multicam.xml";

// path to sysfs for port scanning
$portspath = "/sys/devices/soc0/elphel393-detect_sensors@0";
// total number of ports in 10393
$nports = 4;
$port0 = 2323;

// extract ip addresses
if ($_SERVER['REQUEST_METHOD']==="POST"){
  $list = file_get_contents("php://input");
}else{
  if (isset($_GET['ip'])){
    $list = $_GET['ip'];
  }else{
    $list = $_SERVER['SERVER_ADDR'];
  }
}

$ips = explode(',',$list);

// allow CORS
header('Access-Control-Allow-Origin: *');

switch($cmd){
  case "ports":
    print(getports());
    break;
  case "write":
    // write
    // use get and post requests
    write_config($config,$ips);
    print("ok");
    break;
  case "snapshot":
    send_zipped_images($ips);
    break;
  case "read":
  default:
    // will never need read - config is in http://camip/var/multicam.xml
    // read
    print(file_get_contents($config));
}

function write_config($config,$ips){

  $list = "";
  foreach($ips as $ip){
    $list .= "\t<camera>$ip</camera>\n";
  }
  $xml  = "<?xml version='1.0' standalone='yes'?>\n";
  $xml .= "<Document>\n";
  $xml .= $list;
  $xml .= "</Document>\n";

  file_put_contents($config,$xml);

  return 0;
}

function getports(){

  global $nports, $portspath, $port0;

  $res = "\t<camera ip='".$_SERVER['SERVER_ADDR']."'>\n";

  for($i=0;$i<$nports;$i++){
    $sensor = $portspath."/sensor{$i}0";
    // the file is always there actually
    if(is_file($sensor)){
      $c = trim(file_get_contents($sensor));
      $p = $port0+$i;
      $res .= "\t\t<port index='$i' port='$p'>$c</port>\n";
    }
  }

  $res .= "\t</camera>\n";

  $xml  = "<?xml version='1.0' standalone='yes'?>\n";
  $xml .= "<Document>\n";
  $xml .= $res;
  $xml .= "</Document>\n";

  return $xml;
}

function send_zipped_images($ips){



}

?>