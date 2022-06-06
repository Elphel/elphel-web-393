<?php

include "../include/elphel_functions_include.php";
define('CONF_LWIR16',        '/etc/elphel393/lwir16.ini');
define('IPS',                'ips');
define('PORT_MASKS',         'port_masks');
define('NUM_PORTS',          4);
define('IMGSRV_PORT0',       2323);
define('PORTSPATH',          '/sys/devices/soc0/elphel393-detect_sensors@0');
define('MULTICAM_INI',       'multicam_ini');
define('MULTICAM_DIR',       'multicam_dir');
define('MULTICAM_RPERIOD',   'multicam_rperiod');
define('MULTICAM_SPERIOD',   'multicam_speriod');
define('MULTICAM_CONF',      'multicam_conf');

if (isset($_GET['cmd']))
    $cmd = $_GET['cmd'];
else if (isset($argv[1]))
    $cmd = $argv[1];
        
///$ini =    parse_ini_file(CONF_LWIR16);

//$cmd = "donothing";


//$config = "/var/volatile/html/multicam.xml";

// path to sysfs for port scanning
//$portspath = "/sys/devices/soc0/elphel393-detect_sensors@0";
// total number of ports in 10393
//$nports = 4;
//$port0 = 2323;

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

// does not need configs
switch($cmd){
    case "ports":
        xml_header();
        print(getports());
        exit (0);
}

getIni(); // wil exit OK now 

//print ("<!--");
//print_r($lwir16_ini);
//print ("-->");
//exit(0);


switch($cmd){
  case "ips":
      xml_header();
      print(getIps());
      exit (0);
  case "update": 
      foreach($_GET as $key=>$value) {
          if (array_key_exists($key, $GLOBALS[MULTICAM_INI])){
              $GLOBALS[MULTICAM_INI][$key] = $value;
          }
      }
      writeConfig();
      exitXmlOK();
      exit(0); // 
  case "configs": // return xml with all $GLOBALS[MULTICAM_INI]
      xml_header();
      print(getConfigs());
      exit(0);
      
// obsolete old commands
/*
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
///      print(file_get_contents($config));
 * */
  default:
      exitXmlOK("Unknown");
}


function getIni() {
    $ini=    parse_ini_file(CONF_LWIR16);
    // define parameter type here
    $GLOBALS[MULTICAM_INI][IPS] =               $ini[IPS];
    $GLOBALS[MULTICAM_INI][PORT_MASKS] =        $ini[PORT_MASKS];
    $GLOBALS[MULTICAM_INI][MULTICAM_DIR] =      $ini[MULTICAM_DIR];
    $GLOBALS[MULTICAM_INI][MULTICAM_RPERIOD] =  $ini[MULTICAM_RPERIOD];
    $GLOBALS[MULTICAM_INI][MULTICAM_SPERIOD] =  $ini[MULTICAM_SPERIOD];
    $GLOBALS[MULTICAM_INI][MULTICAM_CONF] =     $ini[MULTICAM_CONF];
    // copy other default parameters
    // try to read xml if exists
    $multi_xml = 0;
    if (file_exists($ini[MULTICAM_CONF])) {
        $multi_xml = simplexml_load_file($ini[MULTICAM_CONF]);
    }
    if ($multi_xml) { // if file exists
        $multi_ini = array();
        foreach ( $multi_xml->children () as $entry ) {
            $GLOBALS[MULTICAM_INI][$entry->getName ()] = (string) $entry;
        }
    } else {
        writeConfig();
    }
    /*
    print ("<!--");
    var_dump($GLOBALS[MULTICAM_INI]);
    print ("-->");
    exit (0);
    */    
    if (isset($GLOBALS[MULTICAM_INI][IPS])) {
        $GLOBALS[IPS] =        explode(',',$GLOBALS[MULTICAM_INI][IPS]);
        if (isset ($GLOBALS[MULTICAM_INI][PORT_MASKS])){
            $masks = explode(',',$GLOBALS[MULTICAM_INI][PORT_MASKS]);
        }
        // Add port masks incdexed by IPs. Changing IPs resets masks
        $GLOBALS[PORT_MASKS] = array();
        for ($i = 0; $i < count($GLOBALS[IPS]); $i++){
            if (isset($masks) && (count($masks) > $i)){
                $GLOBALS[PORT_MASKS][$GLOBALS[IPS][$i]] = (int) $masks[$i];
            } else {
                $GLOBALS[PORT_MASKS][$GLOBALS[IPS][$i]] = (1 << NUM_PORTS) -1;
            }
        }
    }
    /*
    print ("<!--");
    var_dump($GLOBALS[MULTICAM_INI]);
    echo "------------------------";
    var_dump($GLOBALS[IPS]);
    echo "------------------------";
    var_dump($GLOBALS[PORT_MASKS]);
    print ("-->");
    exit (0);
    */
//    exitXmlOK(); // exit for now
}

function writeConfig(){
    $xml="<?xml version=\"1.0\" encoding=\"iso-8859-1\"?>\n<multicam>\n";
    foreach($GLOBALS[MULTICAM_INI] as $key=>$value){
        $xml .= "\t<".$key.'>'.$value.'</'.$key.">\n";
    }
    $xml .= "</multicam>\n";
    file_put_contents($GLOBALS[MULTICAM_INI][MULTICAM_CONF],$xml);
    exec('sync');
}

function printToComment($str) {
    echo "<!--".$str."-->";
}

function write_config($config,$ips){

  $list = "";
  foreach($ips as $ip){
    $list .= "\t<camera>$ip</camera>\n";
  }
  $xml  = "<?xml version='1.0' encoding=\"iso-8859-1\" standalone='yes'?>\n";
  $xml .= "<Document>\n";
  $xml .= $list;
  $xml .= "</Document>\n";

  file_put_contents($config,$xml);

  return 0;
}

function getConfigs(){
    $xml .= "<configs>\n";
    // Add more when available. not Using foreach to keep order 
    $xml .= xmlEntry(MULTICAM_DIR,     $GLOBALS[MULTICAM_INI][MULTICAM_DIR],     1);
    $xml .= xmlEntry(MULTICAM_RPERIOD, $GLOBALS[MULTICAM_INI][MULTICAM_RPERIOD], 1);
    $xml .= xmlEntry(MULTICAM_SPERIOD, $GLOBALS[MULTICAM_INI][MULTICAM_SPERIOD], 1);
    $xml .= "</configs>\n";
    return $xml;
}



function getports(){
  $res = "\t<camera ip='".$_SERVER['SERVER_ADDR']."'>\n";
  for($i=0;$i<NUM_PORTS;$i++){
      $sensor = PORTSPATH."/sensor{$i}0";
    // the file is always there actually
    if(is_file($sensor)){
      $c = trim(file_get_contents($sensor));
      $p = IMGSRV_PORT0+$i;
      $res .= "\t\t<port index='$i' port='$p'>$c</port>\n";
    }
  }
  $res .= "\t</camera>\n";
  $xml .= "<Document>\n";
  $xml .= $res;
  $xml .= "</Document>\n";
  return $xml;
}

function getIps(){
    //$GLOBALS[IPS]
    $xml = "<cameras>\n";
    for($i=0;$i< count($GLOBALS[IPS]);$i++){
        $xml .= "\t<ip index='$i'>".$GLOBALS[IPS][$i]."</ip>\n";
    }
    $xml .= "</cameras>\n";
    return $xml;
}

function exitXmlOK($str="ok"){
    xml_header();
    print ('<'.$str.'/>');
    exit(0);
}

function xml_header() {
    header("Content-type: text/xml");
    header("Pragma: no-cache\n");
    // allow CORS: needed for multi cams unified control
    header('Access-Control-Allow-Origin: *');
    echo "<?xml version=\"1.0\" encoding=\"iso-8859-1\"?>\n";
}

function xmlEntry($key,$value,$ind=0){
    $res = '';
    for ($i = 0; $i < $ind; $i++) $res.="\t";
    $res .= '<'.$key.'>'.$value.'</'.$key.">\n";
    return $res;
}

function applyConf($arr){
    //port_masks=  "15,15,15,15,15"
    if (isset($arr[IPS])) {
        $GLOBALS[IPS] =        explode(',',$arr[IPS]);
        if (isset ($arr[PORT_MASKS])){
            $masks = explode(',',$arr[PORT_MASKS]);
        }
        // Add port masks incdexed by IPs. Changing IPs resets masks
        $GLOBALS[PORT_MASKS] = array();
        for ($i = 0; $i < count($GLOBALS[IPS]); $i++){
            if (isset($masks) && (count($masks) > $i)){
                $GLOBALS[PORT_MASKS][$GLOBALS[IPS][$i]] = (int) $masks[$i];
            } else {
                $GLOBALS[PORT_MASKS][$GLOBALS[IPS][$i]] = (1 << NUM_PORTS) -1;
            }
        }
    }
    /*
    if (isset($arr[DURATION])){
        $GLOBALS[DURATION] =   (int)       $arr[DURATION];
        $GLOBALS[DURATION_EO] = (int) ($GLOBALS[DURATION]/EO_DECIMATE+1);
    }
    if (isset($arr[DURATION_EO]))     $GLOBALS[DURATION_EO] = (int)      $arr[DURATION_EO];
    if (isset($arr[PRE_DELAY]))       $GLOBALS[PRE_DELAY] =  (double)    $arr[PRE_DELAY];
    if (isset($arr[FFC_PERIOD]))      $GLOBALS[FFC_PERIOD] = (double)    $arr[FFC_PERIOD];
    if (isset($arr[FFC_GROUPS]))      $GLOBALS[FFC_GROUPS] = (int)       $arr[FFC_GROUPS];
    if (isset($arr[FFC_FRAMES]))      $GLOBALS[FFC_FRAMES] = (int)       $arr[FFC_FRAMES];
    if (isset($arr[DAEMON_CMD]))      $GLOBALS[DAEMON_CMD] =             $arr[DAEMON_CMD];
    if (isset($arr[DEBUG]))           $GLOBALS[DEBUG] = (int)            $arr[DEBUG];
    if (isset($arr[COMPRESSOR_RUN]))  $GLOBALS[COMPRESSOR_RUN] = (int)   $arr[COMPRESSOR_RUN]; // only after INIT
    if (isset($arr[FFC]))             $GLOBALS[FFC] =                    $arr[FFC]?1:0;
    if (isset($arr[TIFF_TELEM]))      $GLOBALS[TIFF_TELEM] = (int)       $arr[TIFF_TELEM];
    if (isset($arr[TIFF_MN]))         $GLOBALS[TIFF_MN] = (int)          $arr[TIFF_MN];
    if (isset($arr[TIFF_MX]))         $GLOBALS[TIFF_MX] = (int)          $arr[TIFF_MX];
    if (isset($arr[TIFF_BIN_SHIFT]))  $GLOBALS[TIFF_BIN_SHIFT] = (int)   $arr[TIFF_BIN_SHIFT];
    if (isset($arr[TIFF_AUTO]))       $GLOBALS[TIFF_AUTO] = (int)        $arr[TIFF_AUTO];
    */
}



function send_zipped_images($ips){



}

?>