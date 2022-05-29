<?php

// GLOBALS
// path to sysfs for port scanning
$portspath = "/sys/devices/soc0/elphel393-detect_sensors@0";
// total number of ports in 10393
$nports = 4;
$muxports = 3;
$port0 = 2323;

$ports = getports();
$sample_port = get_sample_port($ports);
$master_port = elphel_get_P_value($sample_port,ELPHEL_TRIG_MASTER);

//print("<pre>");
//print_r($ports);
//print("sample port = ".$sample_port);

if (isset($_GET['pointers'])){
  $POINTERS_ONLY = true;
}else{
  $POINTERS_ONLY = false;
}

$res = "";
$res .= "<camera ip='".$_SERVER['SERVER_ADDR']."'>\n";

if (!$POINTERS_ONLY){
  $res .= "\t<master_port>".$master_port."</master_port>\n";
  $res .= "\t<systime>".get_system_time()."</systime>\n";
  $res .= "\t<systimestamp>".time()."</systimestamp>\n";
  $res .= "\t<uptime>".get_uptime()."</uptime>\n";
  $res .= "\t<temperature>".get_temperature()."\t</temperature>\n";
  $res .= "\t<storage>".check_storage()."\t</storage>\n";
  $res .= "\t<recorder name='camogm'>".check_camogm_running()."</recorder>\n";
  $res .= "\t<gps>".check_gps($master_port)."\t</gps>\n";
}

for($i=0;$i<count($ports);$i++){
  $s = implode(', ',$ports[$i]['sensors']);
  $m = $ports[$i]['mux'];
  $res .= "\t<port index='$i' sensor='$s' mux='$m'>".get_port_info($i)."\t</port>\n";
}

$res .= "</camera>\n";

// allow CORS
header('Access-Control-Allow-Origin: *');
$xml  = "<?xml version='1.0' standalone='yes'?>\n";
$xml .= "<Document>\n";
$xml .= $res;
$xml .= "</Document>\n";
print($xml);

//functions

function check_camogm_running(){

  $camogm_running = false;
  exec('ps | grep "camogm"', $arr);
  $check = implode("<br/>",$arr);

  foreach($arr as $line){
    $result = preg_match('/grep/',$line);
    if (!$result) {
      $camogm_running = true;
    }
  }

  if ($camogm_running) $res = "on";
  else                 $res = "off";

  return $res;
}

function getports(){

  global $nports, $muxports, $portspath, $port0;

  $res = array();

  for($i=0;$i<$nports;$i++){
    $subres = array();

    $subres['mux'] = read_port_file($portspath."/port_mux{$i}");
    $subres['sensors'] = array();

    for($j=0;$j<$muxports+1;$j++){
      array_push($subres['sensors'],read_port_file($portspath."/sensor{$i}{$j}"));
    }

    array_push($res,$subres);
  }

  return $res;

}

function read_port_file($file){
  $v = "";
  if(is_file($file)){
    $v = trim(file_get_contents($file));
  }
  return $v;
}

function get_sample_port($a){
  $sample_found = false;
  // errors are not expected here
  //$res = -1;
  $res = 0;
  foreach($a as $k0=>$port){
    $b = $port['sensors'];
    foreach($b as $k1=>$sensor){
      if ($sensor!='none'){
        $sample_found = true;
        break;
      }
    }
    if ($sample_found){
      $res = $k0;
      break;
    }
  }
  return $res;
}

function check_storage(){

  $names = array();
  $regexp = '/([0-9]+) +(sd[a-z0-9]+$)/';
  exec("cat /proc/partitions", $partitions);

  // the first two elements of an array are table header and empty line delimiter, skip them
  for ($i = 2; $i < count($partitions); $i++) {
    // select SATA devices only
    if (preg_match($regexp, $partitions[$i], $name) == 1) {
      $names[$name[2]] = $name[1];
      $j++;
    }
  }

  //print_r($names);
  $res = "";
  foreach($names as $name=>$size){
    if (preg_match('/^sd[a-z]$/',$name,$matches)) {
      $dev = $matches[0];
      $res .= "\n\t\t<device name='$dev' size='$size'>\n";
      foreach($names as $partition=>$psize){
        if (preg_match('/^'.$dev.'[0-9]+$/',$partition)){
          $res .= "\t\t\t<partition name='$partition' size='$psize'></partition>\n";
        }
      }
      $res .= "\t\t</device>\n";

    }
  }

  return $res;
}

function check_gps($port){
  $circbuf_pointers = elphel_get_circbuf_pointers($port,1);
  $pointer = $circbuf_pointers[count($circbuf_pointers)-1];
  $exif = elphel_get_exif_elphel($port,$pointer['exif_pointer']);
  if ((isset($exif['GPSLongitude']))&&(isset($exif['GPSLatitude']))){
    $res = "\n";
    $res .= "\t\t<lat>".$exif['GPSLatitude']."</lat>\n";
    $res .= "\t\t<lon>".$exif['GPSLongitude']."</lon>\n";
  }else{
    $res = "N/A";
  }
  return $res;
}

function get_system_time(){
  $res = exec('date');
  return $res;
}

function get_uptime(){
  $res = exec('uptime');
  $res = trim($res);
  $res = preg_replace('/\s+/',' ',$res);
  $res = explode(' ',$res);
  $out = trim($res[2],',');
  return $out;
}

function get_temperature(){

  $t_cpu = round(floatval(trim(file_get_contents("/tmp/core_temp"))),1);
  $t_10389 = "";
  $t_sda = "";
  $t_sdb = "";

  $temp1_input = "/sys/devices/soc0/amba@0/e0004000.ps7-i2c/i2c-0/0-001a/hwmon/hwmon0/temp1_input";

  if (is_file($temp1_input)){
    $t_10389 = trim(file_get_contents($temp1_input));
    $t_10389 = intval($t_10389)/1000;
  }

  $t_sda = exec("smartctl -A /dev/sda | egrep ^194 | awk '{print $10}'");
  if ($t_sda=="") $t_sda = "-";
  else            $t_sda = intval($t_sda);

  $t_sdb = exec("smartctl -A /dev/sdb | egrep ^194 | awk '{print $10}'");
  $t_sdb = "";
  if ($t_sdb=="") $t_sdb = "-";
  else            $t_sdb = intval($t_sdb);

  $res = "\n\t\t<cpu>$t_cpu</cpu>\n";
  $res .= "\t\t<b10389>$t_10389</b10389>\n";
  $res .= "\t\t<sda>$t_sda</sda>\n";
  $res .= "\t\t<sdb>$t_sdb</sdb>\n";

  return $res;
}

function get_port_info($port){

  global $POINTERS_ONLY;

  $pars_res = "";
  $ts_res = "";

  $pars = array(
    'WB_EN' => 0,
    'AUTOEXP_ON' => 0,
    'COMPRESSOR_RUN'=> 0,
    'SENSOR_RUN'=> 0,
    'COLOR' => 0,
    'QUALITY' => 0,
    'EXPOS' => 0,
    'WOI_WIDTH' => 0,
    'WOI_HEIGHT' => 0,
    'TRIG' => 0,
    'TRIG_MASTER' => 0,
    'TRIG_PERIOD' => 0,
    'TRIG_DECIMATE' => 0,
    'TRIG_CONDITION' => 0,
    'TRIG_OUT' => 0,
    'GAINR' => 0,
    'GAING' => 0,
    'GAINB' => 0,
    'GAINGB' => 0,
  );

  $pars_res .= "\n";

  if (!$POINTERS_ONLY){
    $ps = elphel_get_P_arr($port,$pars);

    $pars_res .= "\t\t<parameters>\n";
    foreach($ps as $k=>$v){
      $pars_res .= "\t\t\t<".strtolower($k).">$v</".strtolower($k).">\n";
    }
    $pars_res .= "\t\t</parameters>\n";
  }

  // get recent timestamps
  $circbuf_pointers = elphel_get_circbuf_pointers($port,1);
  $meta = array();
  foreach($circbuf_pointers as $k=>$v){
    $meta[$k] = array (
      'circbuf_pointer' => $v['circbuf_pointer'],
      'meta' => elphel_get_interframe_meta($port,$v['circbuf_pointer']),
      'Exif' => elphel_get_exif_elphel($port, $v['exif_pointer'])
    );
  }

  $ts_res .= "\t\t<timestamps>\n";

  foreach($meta as $m){
    $sec  = $m['meta']['timestamp_sec'];
    $usec = sprintf("%06d", $m['meta']['timestamp_usec']);
    $ptr = $m['circbuf_pointer'];
    $ts_res .= "\t\t\t<ts frame='{$m['Exif']['FrameNumber']}' ts='$sec.$usec' ptr='$ptr'>$sec.$usec</ts>\n";
  }

  $ts_res .= "\t\t</timestamps>\n";

  $res = $pars_res.$ts_res;

  return $res;
}

?>
