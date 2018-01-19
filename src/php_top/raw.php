<?php

$help = <<<HELP
Description:
  This script gets raw pixel data from sensors. It does 2 things:
  * captures raw data (controls the videomem driver to copy data from fpga
    memory to the system memory)
  * transfers captured data over network

  It can capture images for multiple ports at a time, but downloads only
  a single image (the lowest in the mask).

Parameters:
  cmd - (run|get)
        run - captures
        get  - prints out raw data
  port - 4-bit mask for sensor ports

Usage examples:
  1. For ports 0 and 1:
     // capture
     http://{$_SERVER['SERVER_ADDR']}/raw.php?cmd=run&ports=0x3
     // download
     http://{$_SERVER['SERVER_ADDR']}/raw.php?cmd=get&ports=0x1
     http://{$_SERVER['SERVER_ADDR']}/raw.php?cmd=get&ports=0x2
HELP;


$BUF_SIZE = 4096;
$VPATH = "/sys/devices/soc0/elphel393-videomem@0";
$MEM_PRE     = "$VPATH/membridge_start";
$VFRAME_PRE  = "$VPATH/video_frame_number";
$RAWINFO_PRE = "$VPATH/raw_frame_info";

if (isset($argv[1])){
  $_GET['cmd'] = $argv[1];
}

if (isset($argv[2])){
  $_GET['ports'] = $argv[2];
}

if (isset($_GET['cmd'])){
  $cmd = $_GET['cmd'];
}else{
  $cmd = "donothing";
}

// ports are set by bitmask
// bit 0..3 - port 0..3
// 3 = 0x3  - sets ports 0 and 1
// 12 = 0xc - sets ports 2 and 3
if (isset($_GET['ports'])){
  $sensor_ports = $_GET['ports'];
}else{
  $sensor_ports = 0;
}

$sensor_ports = hexdec($sensor_ports);

//parse $sensor_ports
$ports = array();

// store each index
for($i=0;$i<4;$i++){
  if (($sensor_ports&(0x1<<$i))>>$i){
    array_push($ports,$i);
  }
}

// setup and copy membridge data
if($cmd=="run"){

  print("<pre>\n");
  print("Ports requested: ".implode(",",$ports)."\n");

  // get master
  $frame_num = elphel_get_frame($ports[0]);
  print("Frame number at the time of request: ".$frame_num."\n");

  $master_port = elphel_get_P_value($ports[0],ELPHEL_TRIG_MASTER,0);

  // STEP 1: set size
  for($i=0;$i<count($ports);$i++){
      set_buf_size($ports[$i],$BUF_SIZE);
  }

  // STEP 2: run
  for($i=0;$i<count($ports);$i++){
    // second argument: 0 or 1 - location in video buffer
    // setting to 0 here and waiting frame_num+1, but
    // if frame_num+2 is also of interest - it needs to be set to 1
    set_vbuf_position($ports[$i],0);
    // waiting for frame is built-in in the driver
    copy_vbuf_to_sbuf($ports[$i],$frame_num+1);
  }

  // Print links here
  print("\nDownload:");
  for($i=0;$i<count($ports);$i++){
    $tmpval = dechex(pow(2,$ports[$i]));
    echo " <a target=\"_blank\" href=\"?cmd=get&ports=0x{$tmpval}\">raw {$ports[$i]}</a>";
  }
  print("\n");

  // Print image data from sysfs
  print("\nDebug info:\n\n");
  for($i=0;$i<count($ports);$i++){
    $data = file_get_contents("{$RAWINFO_PRE}{$ports[$i]}");

    echo "  port {$ports[$i]}:\n";
    echo "    ".implode("\n    ",explode("\n",$data));

    $id = parse_sysfs_data($data);
    $raw_size = $id['fullwidth']*$id['height']*$id['bpp']/8;

    echo "Actual raw image size is $raw_size bytes\n";
  }

  print("OK\n");
}

// download raw data
if($cmd=="get"){
  // assuming $ports has only one element
  $data = file_get_contents("{$RAWINFO_PRE}{$ports[0]}");
  //get data from sysfs
  $id = parse_sysfs_data($data);

  $raw_size = $id['fullwidth']*$id['height']*$id['bpp']/8;

  // send headers like filename and size?

  // get data here
  $raw_data = file_get_contents("/dev/image_raw{$ports[0]}", NULL, NULL, 0, $raw_size);

  header("Content-Type: application/octet-stream");
  header('Content-Disposition: attachment; '.'filename="port'.$ports[0].'.raw"');
  header("Content-Length: ".$raw_size."\n");
  header("Pragma: no-cache\n");

  echo $raw_data;
}

if($cmd=="donothing"){
  print("<pre>");
  echo $help;
}

die();

// read frame parameters from sysfs
// echo short header
// echo pixels

function set_buf_size($sensor_port,$size){
  //exec("echo $size > /sys/devices/soc0/elphel393-mem@0/buffer_pages_raw_chn$sensor_port");
  file_put_contents("/sys/devices/soc0/elphel393-mem@0/buffer_pages_raw_chn$sensor_port",$size);
}

function set_vbuf_position($sensor_port,$pos){
  global $VFRAME_PRE;
  //exec("echo $pos > {$VFRAME_PRE}");
  file_put_contents($VFRAME_PRE,$pos);
}

function copy_vbuf_to_sbuf($sensor_port,$frame_num){
  global $MEM_PRE;
  //exec("echo $frame_num > {$MEM_PRE}{$sensor_port}");
  file_put_contents($MEM_PRE.$sensor_port,$frame_num);
}

function parse_sysfs_data($data){

  $res = array(
    'fullwidth' => 0,
    'width' => 0,
    'height' => 0,
    'bpp' => 0
  );

  $lines = explode("\n",$data);
  foreach($lines as $line){
    $pv = explode("=",$line);
    if (count($pv)==2){
      $name = trim($pv[0]);
      $value = trim($pv[1]);
      switch($name){
        case "fullwidth":
          $res['fullwidth']=intval($value);
        break;
        case "width":
          $res['width']=intval($value);
        break;
        case "height":
          $res['height']=intval($value);
        break;
        case "bits per pixel":
          $res['bpp']=intval($value);
        break;
      }
    }
  }

  return $res;
}

?>
