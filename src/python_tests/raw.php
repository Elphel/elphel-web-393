<?php

$BUF_SIZE = 4096;
$VPATH = "/sys/devices/soc0/elphel393-videomem@0"
$MEM_PRE     = "$VPATH/membridge_start"
$VFRAME_PRE  = "$VPATH/video_frame_number"
$RAWINFO_PRE = "$VPATH/raw_frame_info"

if (isset($argv[1])){
  $_GET['sensor_port'] = $argv[1];
}

if (isset($_GET['sensor_port'])){
  $sensor_port = $_GET['sensor_port'];
}else{
  $sensor_port = 0;
}

// get master
$frame_num = elphel_get_frame($sensor_port);

$master_port = elphel_get_P_value($sensor_port,ELPHEL_TRIG_MASTER)),0);

set_buf_size($sensor_port,$BUF_SIZE);
set_vbuf_position($sensor_port,0);
// waiting for frame is built-in in the driver
copy_vbuf_to_sbuf($sensor_port,$frame_num+1);

// read frame parameters from sysfs
// echo short header
// echo pixels

function set_buf_size($sensor_port,$size){
  exec("echo $size > /sys/devices/soc0/elphel393-mem@0/buffer_pages_raw_chn$sensor_port");
}

function set_vbuf_position($sensor_port,$pos){
  global $VRAME_PRE;
  exec("echo $pos > {$VFRAME_PRE}{$sensor_port}");
}

function copy_vbuf_to_sbuf($port,$frame_num){
  global $MEM_PRE
  exec("echo $frame_num > {$MEM_PRE}{$sensor_port}");
}

?>
