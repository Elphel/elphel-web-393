#!/usr/bin/php
<?php
/*!*******************************************************************************
*! FILE NAME  : lwir.php
*! DESCRIPTION: Subcameras initialization and capturing of synchronized scene
*  sequences               
*! Copyright (C) 2021 Elphel, Inc
*! -----------------------------------------------------------------------------**
*!
*!  This program is free software: you can redistribute it and/or modify
*!  it under the terms of the GNU General Public License as published by
*!  the Free Software Foundation, either version 3 of the License, or
*!  (at your option) any later version.
*!
*!  This program is distributed in the hope that it will be useful,
*!  but WITHOUT ANY WARRANTY; without even the implied warranty of
*!  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*!  GNU General Public License for more details.
*!
*!  You should have received a copy of the GNU General Public License
*!  along with this program.  If not, see <http://www.gnu.org/licenses/>.
*! -----------------------------------------------------------------------------**
*!
*/
   set_include_path ( get_include_path () . PATH_SEPARATOR . '/www/pages/include' );
   include 'show_source_include.php';
   include "elphel_functions_include.php"; // includes curl functions
   define('REG_FFC_FRAMES',     'SENSOR_REGS4');     // Register for the number of FFC frames to integrate
   define('REG_FFC_RUN',        'SENSOR_REGS26');    // Register to trigger FFC
   define('SCRIPT_RESET',       'reset_frames.php'); // Reset frame numbers
   define('SCRIPT_WAIT',        'wait_frame.php');   // wait frame
   define('DEFAULT_IPS',        '192.168.0.41,192.168.0.42,192.168.0.43,192.168.0.44,192.168.0.45');
   define('PIPE_CMD',           '/tmp/pipe_cmd');
   define('PIPE_RESPONSE',      '/tmp/pipe_response');
   define('PIPE_MODE',          0600);
   define('CONF_LWIR16',        '/etc/elphel393/lwir16.ini');
   define('EO_DECIMATE',        6);
   // GLOBALS field name
   define('IPS',                'ips');
   define('DURATION',           'duration');
   define('DURATION_EO',        'duration_eo');
   define('PRE_DELAY',          'pre_delay');
   define('FFC',                'ffc'); // use FFC
   define('FFC_PERIOD',         'ffc_period');
   define('FFC_GROUPS',         'ffc_groups');
   define('FFC_FRAMES',         'ffc_frames');
   define('COMPRESSOR_RUN',     'compressor_run'); // after init
   define('DEBUG',              'debug');
   define('DAEMON_RUN',         'daemon_run');  // not read through ini
   define('CAPTURE_RUN',        'capture_run'); // not read through ini
   define('LAST_FFC',           'last_ffc'); // not read through ini
   define('SECUENCE_NUM',       'sequence_num'); // not read through ini
   define('TIME_TO_FFC',        'time_to_ffc'); // not read through ini
   define('DAEMON_CMD',         'CMD'); // command to be executed by a daemon
   define('CMD_INIT',           'INIT'); // passed commands
   define('CMD_START',          'START'); // passed commands
   define('CMD_STOP',           'STOP'); // passed commands
   define('CMD_EXIT',           'EXIT'); // passed commands
   define('CMD_STATUS',         'STATUS'); // passed commands
   define('CMD_REBOOT',         'REBOOT'); // passed commands
   define('CR_EXTRA',           2); // not currenly used - wait extra frames in capture_range
   // daemon control $_GET
   define('DAEMON_CTRL',        'daemon');
   define('DAEMON_CTRL_CMDS',   'cmd'); //comma-separated commands to be sent to the daemon
   
   
   // initializations before reading lwir16.ini
   $GLOBALS[COMPRESSOR_RUN] = 0;
   $GLOBALS[DURATION] = 100;
   $GLOBALS[PRE_DELAY] = 5.0; // seconds
   $GLOBALS[FFC] =       false; // perform FFC before starting a sequence (and before delay? reduce delay ?)
//   $ffc =        false; 
   $GLOBALS[FFC_GROUPS] = 2; // 1/2/4 - do not run FFC on all channels simultaneously (43 failed)
   $GLOBALS[FFC_FRAMES] = 8; // read actual?
   
   $ini =    parse_ini_file(CONF_LWIR16);
   applyConf($ini);
   
   $ffc_wait_frames =      10; // extra wait after FFC finished (2xffc_frames)
   $compressor_run =       0; // stop all
   
   if (!($_SERVER ['REQUEST_METHOD'] == "GET")){
       lwir16_daemon();
       exit (0);
   }
   
   if (isset($_GET[DAEMON_CTRL])){
       daemon_control($_GET['daemon']);
       exit(0);
   }
   
   
   
   unset($duration);unset($duration_eo);
   foreach($_GET as $key=>$value) {
       if (($key == 'ip') || ($key == 'ips')){ //  multicamera operation
           $GLOBALS[IPS] = explode(',',$value);
       } else if (($key == 'lwir16') || ($key == 'cmd')){
           $lswir16cmds = explode(',',$value);
       } else if ($key == 'pre_delay'){
           $GLOBALS[PRE_DELAY] = (double) $value; // only used with capture
       } else if (($key == 'd')   || ($key == 'duration')){
          $duration = (int) $value; // EO - make 1/6 +1 frames
       } else if (($key == 'de')   || ($key == 'duration_eo')){
           $duration_eo = (int) $value; // EO - make 1/6 +1 frames
       } else if ($key == 'nowait'){
           $nowait = 1;
       } else if ($key == 'run'){
           $GLOBALS[COMPRESSOR_RUN] = 2;
       } else if ($key == 'ffc'){
           $GLOBALS[FFC] = true;
           if ($value) { // string "0" will also be false
               $v = (int) $value;
               if (($v == 1) || ($v == 2) || ($v == 4)){
                   $$GLOBALS[FFC_GROUPS] = $v;
               }
           }
       }
   }
   if (isset($duration) && !isset($duration_eo)){
       $duration_eo = (int) ($duration/EO_DECIMATE + 1);
   }
   if (isset($duration))    $GLOBALS[DURATION] = $duration;
   if (isset($duration_eo)) $GLOBALS[DURATION_EO] = $duration_eo;
   if ($GLOBALS[DURATION] < 1)    $GLOBALS[DURATION] = 1;
   if ($GLOBALS[DURATION_EO] < 1) $GLOBALS[DURATION_EO] = 1;

   if (isset($lswir16cmds)){
       $lwir_trig_dly =       0;
       $eo_quality =         97;
       $exposure =         1000; // 1 ms
       $autoExposureMax= 500000;
       $autoExp=              1;
       $gain=            2*0x10000;
       $rScale =         1*0x10000;
       $bScale =         1*0x10000;
       $gScale =         1*0x10000;
       $autoWB =              1;
       
///       $extra = 2;
       $wait = 1;
       if ($nowait){
           $wait = 0;
       }
       $COLOR_JP4 = 5;
       $COLOR_RAW = 15;
       $LWIR_HEIGHT = 512;
       $LWIR_TELEMETRY_LINES = 1;
       $LWIR_TELEMETRY = 1;
       $FRAMES_SKIP = 4;
       $FFC_FRAMES = 8;
       $REG_FFC_FRAMES= 'SENSOR_REGS4';      // Register for the number of FFC frames to integrate
       $REG_FFC_RUN=    'SENSOR_REGS26';     // Register to trigger FFC
//       print("1");
//       print("2");
//       exit(0);
       
       $lwir_ips= array($GLOBALS[IPS][0],$GLOBALS[IPS][1],$GLOBALS[IPS][2],$GLOBALS[IPS][3]);
//       $twoIPs= array($GLOBALS[IPS][0],$GLOBALS[IPS][4]);
       $twoIPs= $GLOBALS[IPS]; // array($GLOBALS[IPS][0],$GLOBALS[IPS][4]); wait all
//       print_r($lswir16cmds);
 
       for ($ncmd = 0; $ncmd < count($lswir16cmds); $ncmd++){
           $cmd = $lswir16cmds[$ncmd];
           if ($cmd == 'init'){
               $results = runInit();
               $xml = new SimpleXMLElement("<?xml version='1.0'  standalone='yes'?><lwir16_init/>");
               for ($i = 0; $i<count($results); $i++){
                   $xml_ip = $xml->addChild ('ip_'.$GLOBALS[IPS][$i]);
                   foreach ($results[$i] as $key=>$value){
                       $xml_ip->addChild($key,$value);
                   }
               }
               $rslt=$xml->asXML();
               header("Content-Type: text/xml");
               header("Content-Length: ".strlen($rslt)."\n");
               header("Pragma: no-cache\n");
               printf($rslt);
               exit(0);
           } else if ($cmd == 'capture'){
               $results =  runCapture($GLOBALS[FFC], $nowait); // runCapture($run_ffc, $nowait = 0, $debug=0)
               $xml = new SimpleXMLElement("<?xml version='1.0'  standalone='yes'?><capture_range/>");
               $xml->addChild ('ffc', $GLOBALS[FFC] ? 'true':'false');
               $xml->addChild (FFC_GROUPS,$GLOBALS[FFC_GROUPS]);
               $xml->addChild (FFC_FRAMES,$GLOBALS[FFC_FRAMES]);
               for ($i = 0; $i<count($GLOBALS[IPS]); $i++){
                   $xml_ip = $xml->addChild ('ip_'.$GLOBALS[IPS][$i]);
                   foreach ($results[$i] as $key=>$value){
                       $xml_ip->addChild($key,$value);
                   }
               }
               $rslt=$xml->asXML();
               header("Content-Type: text/xml");
               header("Content-Length: ".strlen($rslt)."\n");
               header("Pragma: no-cache\n");
               printf($rslt);
               exit(0);
           } else if ($cmd == 'reboot'){
               //log_msg('running autocampars.py ['.implode(',',$IPs).'] pyCmd reboot');
               $xml = new SimpleXMLElement("<?xml version='1.0'  standalone='yes'?><lwir16_reboot/>");
               for ($i = 0; $i<count($GLOBALS[IPS]); $i++){
                   $xml_ip = $xml->addChild ('ip_'.$GLOBALS[IPS][$i]);
                   $xml_ip->addChild('reboot','started');
               }
               $rslt=$xml->asXML();
               header("Content-Type: text/xml");
               header("Content-Length: ".strlen($rslt)."\n");
               header("Pragma: no-cache\n");
               printf($rslt);
               exec ( 'autocampars.py ['.implode(',',$GLOBALS[IPS]).'] pyCmd reboot', $output, $retval );
               exit(0); // no time to close log
           } else if ($cmd == 'state'){
               // TODO: read all subcameras
               $state = file('/var/state/camera');
               $xml = new SimpleXMLElement("<?xml version='1.0'  standalone='yes'?><lwir16_state/>");
               foreach ($state as $line){
                   $kv = explode('=',$line);
                   if (count ($kv) > 1){
                        $xml->addChild (trim($kv[0]),trim($kv[1]));
                   }
               }
               $rslt=$xml->asXML();
               header("Content-Type: text/xml");
               header("Content-Length: ".strlen($rslt)."\n");
               header("Pragma: no-cache\n");
               printf($rslt);
               exit (0);
           }
       }
   } else { // Just output usage?
       echo <<<EOT
        <pre>
        This script supports initialization of the LWIR16 camera (16 LWIR 640x512 sensors and 4 2592x1936 color ones)
        and capturing of short image sequences (100 frames fit into 64MB per-channel image buffer) for (relatively)
        slow recording with camogm. Untill videocompression for 16-bit TIFFs is not implemented, recording is not fast
        enough for continupous recording. This script should be launched in the 'master' subcamera only, it will
        communidate with the other ones.
        
        URL parameters:
        <b>lwir16=init</b> - syncronize all 5 cameras, set acquisition parameters
        <b>lwir16=capture</b> - wait specified time, synchronously turn on compressors in each channel of each subcamera,
                                acquire specified number of frames in each channel (reduced, and turn compressors off.
         
        </pre>
EOT;
// Watch EOT w/o any spaces! 
   }
   function runInit($debug = 0) {
        // TODO: use lwir16.ini
        $eo_quality = 97;
        $exposure = 1000; // 1 ms
        $autoExposureMax = 500000;
        $autoExp = 1;
        $gain = 2 * 0x10000;
        $rScale = 1 * 0x10000;
        $bScale = 1 * 0x10000;
        $gScale = 1 * 0x10000;
        $autoWB = 1;
///        $extra = 2;
//        $wait = 1;
//        if ($nowait) {
//            $wait = 0;
//        }
        $COLOR_JP4 = 5;
        $COLOR_RAW = 15;
        $LWIR_HEIGHT = 512;
        $LWIR_TELEMETRY_LINES = 1;
        $LWIR_TELEMETRY = 1;
        $FRAMES_SKIP = 4;
        $FFC_FRAMES = 8;
        $REG_FFC_FRAMES = 'SENSOR_REGS4'; // Register for the number of FFC frames to integrate
        $REG_FFC_RUN = 'SENSOR_REGS26'; // Register to trigger FFC
        $lwir_ips = array(
            $GLOBALS[IPS][0],
            $GLOBALS[IPS][1],
            $GLOBALS[IPS][2],
            $GLOBALS[IPS][3]
        );
        $twoIPs = $GLOBALS[IPS]; // array($GLOBALS[IPS][0],$GLOBALS[IPS][4]); wait all
        if ($debug) {
            printf("--- twoIPs:\n");
            print_r($twoIPs);
        }
        
        $results0 = skipFrames($twoIPs, 2, $debug);
        
        
        // print_r($results0);
        $results1 = resetIPs($GLOBALS[IPS],$debug); // sync channels in each subcamera
                                             // print_r($results1);
        $results2 = skipFrames($twoIPs, 16,$debug); // was 1
                                             // print_r($results2); print("<br/>");
        $urls = array(); // eo - individual
        for ($i = 0; $i < (count($GLOBALS[IPS]) + 3); $i ++) {
            $nip = $i;
            if ($nip >= count($GLOBALS[IPS])) {
                $nip = count($GLOBALS[IPS]) - 1;
            }
            $urls[$i] = 'http://' . $GLOBALS[IPS][$nip] . '/parsedit.php?immediate&sensor_port=' . ($i - $nip);
            $urls[$i] .= '&TRIG_OUT=0x66555' . '&TRIG_CONDITION=0x95555' . '&TRIG_BITLENGTH=31' . '&EXTERN_TIMESTAMP=1' . '&XMIT_TIMESTAMP=1';
        }
        // print_r($urls); print("<br/>");
        // exit(0);
        for ($i = 0; $i < count($lwir_ips); $i ++) {
            $urls[$i] .= '&TRIG_DELAY=' . $lwir_trig_dly . '&*TRIG_DELAY=15' . // apply to all ports
            '&BITS=16&*BITS=15' . '&COLOR=' . $COLOR_RAW . '&*COLOR=15' . '&WOI_HEIGHT=' . ($LWIR_HEIGHT + ($LWIR_TELEMETRY ? $LWIR_TELEMETRY_LINES : 0)) . '&*WOI_HEIGHT=15' . '&' . $REG_FFC_FRAMES . '=' . $FFC_FRAMES . '&*' . $REG_FFC_FRAMES . '=15'; // apply to all channels
            $urls[$i] .= '&COMPRESSOR_RUN=2&*COMPRESSOR_RUN=15';
        }
        if ($debug) {
            print_r($GLOBALS[IPS]);
            print_r($urls);
        }
        for ($chn = 0; $chn < 4; $chn ++) {
            $urls[count($GLOBALS[IPS]) - 1 + $chn] .=
            "&COLOR=" . $COLOR_JP4 .
            "&QUALITY=" . $eo_quality .
            "&EXPOS=" . $exposure .
            "&AUTOEXP_EXP_MAX=" . $autoExposureMax .
            "&AUTOEXP_ON=" . $autoExp .
            "&GAING=" . $gain .
            "&RSCALE=" . $rScale . // "*0".
            "&BSCALE=" . $bScale . // "*0".
            "&GSCALE=" . $gScale . // "*0". // GB/G ratio
            "&WB_EN=" . $autoWB . // "*0".
            "&DAEMON_EN_TEMPERATURE=1"; // "*0";
            if (lrp . eo_full_window) {
                $urls[count($GLOBALS[IPS]) - 1 + $chn] .= "&WOI_LEFT=0" . "&WOI_TOP=0" . "&WOI_WIDTH=2592" . "&WOI_HEIGHT=1936";
            }
            if ($chn == 0) {
                $urls[count($GLOBALS[IPS]) - 1] .= '&COMPRESSOR_RUN=2&*COMPRESSOR_RUN=15';
            }
        }
        if ($debug) {
            printf("--- setting camera parameters, urls:\n");
            print_r($urls);
        }
        // print_r($urls);
        // exit(0);
        $curl_data = curl_multi_start($urls);
        $enable_echo = false;
        $results3 = curl_multi_finish($curl_data, true, 0, $enable_echo); // Switch true -> false if errors are reported (other output damaged XML)
        if ($debug) {
            printf("--- results3:\n");
            print_r($results3);
        }
        
        
        
        $results4 = skipFrames($twoIPs, 16,$debug);
        // set external trigger mode for all LWIR and EO cameras
        $urls = array();
        for ($i = 0; $i < count($GLOBALS[IPS]); $i ++) {
            $urls[] = 'http://' . $GLOBALS[IPS][$i] .
            '/parsedit.php?immediate&sensor_port=0&TRIG=4&*TRIG=15' .
            '&COMPRESSOR_RUN=' . $GLOBALS[COMPRESSOR_RUN] . '*5&*COMPRESSOR_RUN=15'; // delay turning off COMPRESSOR_RUN
        }
        if ($debug) {
            printf("--- finally setting camera parameters (see COMPRESSOR_RUN), urls:\n");
            print_r($urls);
        }
        
        $curl_data = curl_multi_start($urls);
        $enable_echo = false;
        $results5 = curl_multi_finish($curl_data, true, 0, $enable_echo); // Switch true -> false if errors are reported (other output damaged XML)
        if ($debug) {
            printf("--- results5:\n");
            print_r($results5);
        }
        
        $results6 = skipFrames($twoIPs, 16,$debug); // make sure all previous parameters are applied // waits for both LWIR and EO
                                             // second reset after cameras running synchronously
        $results7 = resetIPs($GLOBALS[IPS]); // sync channels in each subcamera
        $results8 = skipFrames($twoIPs, 16,$debug); // was 2
    
        $results9 = resetIPs($GLOBALS[IPS]); // sync channels in each subcamera
        $results10 = skipFrames($twoIPs, 16,$debug); // was 2
        $results = $results10;
        return $results;
   }

   function runCapture($run_ffc, $nowait = 0, $debug=0) {
       // TODO: use lwir16.ini
//       $eo_quality = 97;
//       $exposure = 1000; // 1 ms
//       $autoExposureMax = 500000;
//       $autoExp = 1;
//       $gain = 2 * 0x10000;
//       $rScale = 1 * 0x10000;
//       $bScale = 1 * 0x10000;
//       $gScale = 1 * 0x10000;
//       $autoWB = 1;
//       $extra = 2;
       $wait = 1;
       if ($nowait) {
           $wait = 0;
       }
       $COLOR_JP4 = 5;
       $COLOR_RAW = 15;
       $LWIR_HEIGHT = 512;
       $LWIR_TELEMETRY_LINES = 1;
       $LWIR_TELEMETRY = 1;
       $FRAMES_SKIP = 4;
       $FFC_FRAMES = 8;
       $REG_FFC_FRAMES = 'SENSOR_REGS4'; // Register for the number of FFC frames to integrate
       $REG_FFC_RUN = 'SENSOR_REGS26'; // Register to trigger FFC
       $lwir_ips = array(
           $GLOBALS[IPS][0],
           $GLOBALS[IPS][1],
           $GLOBALS[IPS][2],
           $GLOBALS[IPS][3]
       );
       $twoIPs = $GLOBALS[IPS]; // array($GLOBALS[IPS][0],$GLOBALS[IPS][4]); wait all
       $sensor_port = 0;
       if ($debug) {
           printf("--- runCapture: run_ffc=%d, wait=%d\n",$run_ffc,$wait);
           print_r($lwir_ips);
       }
       
       if ($run_ffc){ // may move after measuring time, but need to make sure it will be not too late
           runFFC($lwir_ips, $ffc_wait_frames, $debug);
       }
       
       $this_frame=elphel_get_frame($sensor_port);
       $this_timestamp=elphel_frame2ts($sensor_port,$this_frame);
       $timestamp = $this_timestamp + $GLOBALS[PRE_DELAY]; // this will be a delay between capture sequences
       
       $urls = array();
       for ($i = 0; $i<count($GLOBALS[IPS]); $i++){
           // $_SERVER[SCRIPT_NAME] STARTS WITH '/'
           $url = 'http://'.$GLOBALS[IPS][$i].'/capture_range.php?sensor_port='.$sensor_port; //
           $url .= '&ts='.$timestamp; // &timestamp" -> ×tamp
           $url .= '&port_mask=15'; // .$port_mask[$i];
           $dur = ($i < 4) ? $GLOBALS[DURATION] : $GLOBALS[DURATION_EO];
           $url .= '&duration='. $dur;
           //                   $url .= '&maxahead='. $maxahead;
           //                   $url .= '&minahead='. $minahead;
           $url .= '&extra='.    CR_EXTRA; // $extra;
           if ($wait && ($i == (count($GLOBALS[IPS]) - 1))){ // addd to the last ip in a list
               $url .= '&wait';
           }
           $urls[] = $url;
       }
       if ($debug) {
           printf("--- runCapture: URLs:\n");
           print_r($urls);
       }
       
       $curl_data = curl_multi_start ($urls);
       $enable_echo = false;
       $results =  curl_multi_finish($curl_data, true, 0, $enable_echo); // Switch true -> false if errors are reported (other output damaged XML)
       return $results;       
   }
   
   function runFFC($lwir_ips, $ffc_wait_frames, $debug=0) { // return number of frames used
       
       $skipped = 0;
       $port_masks =array();
       foreach ($lwir_ips as $l){
           $port_masks[] = 15; // select all 4 ports 
       }
       if      ($GLOBALS[FFC_GROUPS] == 1) $group_masks = array(15);
       else if ($GLOBALS[FFC_GROUPS] == 2) $group_masks = array(5, 10);
       else                       $group_masks = array(1, 2, 4, 8);
       if ($debug) {
           printf('$GLOBALS[FFC_GROUPS]=     '.$GLOBALS[FFC_GROUPS]."\n");
           printf('$GLOBALS[FFC_FRAMES]=     '.$GLOBALS[FFC_FRAMES]."\n");
           printf('$ffc_wait_frames='.$ffc_wait_frames."\n");
           print_r($group_masks);
       }
       for ($ig = 0; $ig < $GLOBALS[FFC_GROUPS]; $ig++){
           $urls = array();
           $ip0 = -1;
           for ($i = 0; $i < count($lwir_ips); $i++) { // start urls for 4 of lwir port0
               $mask = $port_masks[$i] & $group_masks[$ig];
               if ($mask != 0) {
                   $p=0;
                   for (;((1 << $p) & $mask) == 0; $p++); // find lowest non-zero bit
                   $urls[] = sprintf("http://%s/parsedit.php?immediate&sensor_port=%d&%s=1&*%s=%d",$lwir_ips[$i],$p,REG_FFC_RUN,REG_FFC_RUN,$mask);
                   if ($ip0 < 0) {
                       $ip0 = $i; // first used ip index
                   }
               }
               
           }
           if ($debug) {
               printf('$ig=    '.$ig."\n");
               print_r($urls);
           }
           if ($urls){ // not empty
               $curl_data = curl_multi_start ($urls);
               $enable_echo = false;
               $results =  curl_multi_finish($curl_data, true, 0, $enable_echo); // Switch true -> false if errors are reported (other output damaged XML)
           }
           $skip_frames = 2 * $GLOBALS[FFC_FRAMES] + $ffc_wait_frames;
           skipFrames(array($lwir_ips[$ip0]),  $skip_frames);
           if ($debug) {
               printf("FFC done, result:\n");
               print_r($results);
           }
       }
       return $skipped; // better read actual frame after
   }
 
//   define("REG_FFC_FRAMES",     "SENSOR_REGS4");   // Register for the number of FFC frames to integrate
//   define("REG_FFC_RUN",        "SENSOR_REGS26"); // Register to trigger FFC
   
   
   function resetIPs($ips,$debug=0) {
       $frame = -2; // skip 2 frames before reset
       $urls = array();
       for ($i = 0; $i<count($ips); $i++){
           // $_SERVER[SCRIPT_NAME] STARTS WITH '/'
           $url = 'http://'.$ips[$i].'/'.SCRIPT_RESET.'?frame='.$frame; //
           $urls[] = $url;
       }
       if ($debug) {
           printf("--- resetIPs(), urls=\n");
           print_r($urls);
       }
       
       $curl_data = curl_multi_start ($urls);
       $enable_echo = false;
       return curl_multi_finish($curl_data, true, 0, $enable_echo); // Switch true -> false if errors are reported (other output damaged XML)
   }

   function skipFrames($ips, $skip, $debug=0) {
       $urls = array();
       for ($i = 0; $i<count($ips); $i++){
//           $url = 'http://'.$ips[$i].$_SERVER[SCRIPT_NAME].'?frame='.$frame; //
           $url = 'http://'.$ips[$i].'/'.SCRIPT_WAIT.'?frame='.(-$skip); //
           $urls[] = $url;
       }
       if ($debug) {
           printf("--- skipFrames(%d), urls=\n",$skip);
           print_r($urls);
       }
       $curl_data = curl_multi_start ($urls);
       $enable_echo = false;
       return curl_multi_finish($curl_data, true, 0, $enable_echo); // Switch true -> false if errors are reported (other output damaged XML)
   }
   
   function lwir16_daemon() {
       // ini parameters are already read in
       declare(ticks = 1);
       set_time_limit(0); // no limit - run forever
       pcntl_signal(SIGTERM, "signal_handler");
       pcntl_signal(SIGINT, "signal_handler");
       
       if(file_exists(PIPE_CMD)){
           unlink(PIPE_CMD); //delete pipe if it was already there - waiting prevents signal handling!
       }
       $GLOBALS[DAEMON_RUN] = 1;
       $GLOBALS[CAPTURE_RUN] = 0; // until command
       $GLOBALS[LAST_FFC] = 0; // overdue
       $GLOBALS[SECUENCE_NUM] = 0;
       if ($GLOBALS[DEBUG > 1])  {
           printf("--- GLOBALS: ---\n");
           print_r($GLOBALS);
       }
//       exit(0);
       $from_pipe = false; // first commands are form INI
       while ($GLOBALS[DAEMON_RUN]){
           if (isset($GLOBALS[DAEMON_CMD])){ // execute command (INIT, RUN, STOP)
               $commands = explode(',',$GLOBALS[DAEMON_CMD]);
               unset($GLOBALS[DAEMON_CMD]);
               if ($GLOBALS[DEBUG])  {
                   printf("--- executing commands: ---\n");
                   print_r($commands);
               }
               
               foreach($commands as $cmd){
                   if ($cmd == CMD_INIT){
                       if ($GLOBALS[DEBUG])  printf("--- got command: INIT ---\n");
                       $result = runInit($GLOBALS[DEBUG]); // debug
                       if ($GLOBALS[DEBUG]){
                           printf("--- command: INIT done, result ---\n");
                           print_r($result);
                       }
                   } else if ($cmd == CMD_START){
                       $GLOBALS[CAPTURE_RUN] = 1;
                   } else if ($cmd == CMD_STOP){
                       $GLOBALS[CAPTURE_RUN] = 0;
                   } else if ($from_pipe && ($cmd == CMD_STATUS)){ // generate status data and send over response pipe
                       $state = file('/var/state/camera');
                       //TODO: add daemon status itself
                       $xml = new SimpleXMLElement("<?xml version='1.0'  standalone='yes'?><lwir16_status/>");
                       $xml_state = $xml->addChild ('state');
                       foreach ($state as $line){
                           $kv = explode('=',$line);
                           if (count ($kv) > 1){
                               $xml_state->addChild (trim($kv[0]),trim($kv[1]));
                           }
                       }
                       $xml->addChild (SECUENCE_NUM, $GLOBALS[SECUENCE_NUM]);
                       if ($GLOBALS[FFC]) {
                           $GLOBALS[TIME_TO_FFC] = $GLOBALS[LAST_FFC] + $GLOBALS[FFC_PERIOD] - time();
                           $xml->addChild (TIME_TO_FFC, $GLOBALS[TIME_TO_FFC]);
                       }
                       $xml->addChild (IPS, implode(',',$GLOBALS[IPS]));
                       $xml->addChild (DURATION,        $GLOBALS[DURATION]);
                       $xml->addChild (DURATION_EO,     $GLOBALS[DURATION_EO]);
                       $xml->addChild (PRE_DELAY,       $GLOBALS[PRE_DELAY]);
                       $xml->addChild (FFC,             $GLOBALS[FFC]);
                       $xml->addChild (FFC_PERIOD,      $GLOBALS[FFC_PERIOD]);
                       $xml->addChild (FFC_GROUPS,      $GLOBALS[FFC_GROUPS]);
                       $xml->addChild (FFC_FRAMES,      $GLOBALS[FFC_FRAMES]);
                       $xml->addChild (COMPRESSOR_RUN,  $GLOBALS[COMPRESSOR_RUN]);
                       $xml->addChild (DEBUG,           $GLOBALS[DEBUG]);
                       $xml->addChild (DAEMON_RUN,      $GLOBALS[DAEMON_RUN]);
                       $xml->addChild (CAPTURE_RUN,     $GLOBALS[CAPTURE_RUN]);
                       $xml->addChild (LAST_FFC,        $GLOBALS[LAST_FFC]);
                       $rslt=$xml->asXML();
                       if(!file_exists(PIPE_RESPONSE)) {
                           // create the pipe
                           umask(0);
                           posix_mkfifo(PIPE_RESPONSE,PIPE_MODE);
                       }
                       $fr = fopen(PIPE_RESPONSE,"w");
                       fwrite($fr,$rslt);
                       fclose ($fr);
                       if ($GLOBALS[DEBUG]){
                           printf('--- command: Sent status report ---\n');
                           printf($rslt);
                       }
                   } else if ($cmd == CMD_EXIT){
                       $GLOBALS[CAPTURE_RUN] = 0;
                       unset($GLOBALS[DAEMON_RUN]);
                       continue;
                   } else if ($cmd == CMD_REBOOT){
                       exec ( 'autocampars.py ['.implode(',',$GLOBALS[IPS]).'] pyCmd reboot', $output, $retval );
                       exit(0);
                   }
               }
               continue; // try read new commands befor capturing
           }
           // Read / execute commands
           if(file_exists(PIPE_CMD)) {
               if ($GLOBALS[DEBUG]){
                   printf("--- got from pipe: ---\n");
               }
               /*
               $f = fopen(PIPE_CMD,"r");
               if ($GLOBALS[DEBUG]) echo "(r) opened cmd\n";
               $cmd_lines = fgets($f);
               fclose ($f);
               */
               $cmd_lines = file_get_contents(PIPE_CMD);
               if ($GLOBALS[DEBUG]) echo $cmd_lines."\n";
               if ($GLOBALS[DEBUG]) echo "(r) closed cmd\n";
               unlink(PIPE_CMD); //delete pipe
               $ini = parse_ini_string($cmd_lines); // parse_ini_file(PIPE_CMD); does not work!
               if ($GLOBALS[DEBUG]){
                   print_r($ini);
               }
               unset     ($GLOBALS[DAEMON_CMD]);
               applyConf ($ini); // update $GLOBALS
               $from_pipe = true;
               if ($GLOBALS[DEBUG] > 1){
                   printf("--- update GLOBALS: ---\n");
                   print_r($GLOBALS);
               }
               continue; // will execute commands
           }
           if ($GLOBALS[DEBUG] > 1){
               printf("--- capture_run: ---%d \n",$GLOBALS[CAPTURE_RUN]);
           }
           if ($GLOBALS[CAPTURE_RUN]){
               $ffc_due = $GLOBALS[LAST_FFC] + $GLOBALS[FFC_PERIOD];
               $run_ffc = false;
               $now = time();
               if ($GLOBALS[FFC] && ($now > $ffc_due)){
                   $run_ffc = true;
                   $GLOBALS[LAST_FFC] = $now;
               }
               $result =  runCapture($run_ffc, false, $GLOBALS[DEBUG]);
               $GLOBALS[SECUENCE_NUM]++;
               if ($GLOBALS[DEBUG] > 1){
                   printf("--- capture_run: ---\n");
                   print_r($result);
               }
           } else {
               // jusrt skip a frame
               $ips0 = array($GLOBALS[IPS][0]); // just master camera IP
               $skip = 10; // OK if more
               $rslt = skipFrames($ips0,$skip);
           }
           
           
       }
       if ($GLOBALS[DEBUG]){
           printf("--- Exiting ...\n");
           if ($GLOBALS[DEBUG]>1){
               print_r($GLOBALS);
           }
       }
   }
   
   function signal_handler($signal) {
       switch($signal) {
           case SIGTERM:
               print "Caught SIGTERM\n";
               $GLOBALS[DAEMON_RUN] = 0;
               return; //  exit;
           case SIGKILL:
               print "Caught SIGKILL\n";
               $GLOBALS[DAEMON_RUN] = 0;
               return; //  exit;
           case SIGINT:
               print "Caught SIGINT\n";
               $GLOBALS[DAEMON_RUN] = 0;
               return; //  exit;
       }
   }
   
   function applyConf($arr){
       if (isset($arr[IPS]))         $GLOBALS[IPS] =        explode(',',$arr[IPS]);
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
       if (isset($arr[FFC]))             $GLOBALS[FFC] =                    $arr[FFC]?true:false; // only after INIT
   }

/**
 * start/stop/restart/status/pars daemon
 *
 * @param string $cmd
 */
    function daemon_control($cmd)
    {
       /*
        echo "<pre>\n";
        print_r($_SERVER);
        echo "</pre>\n";
        exit(0);
        */
        // see if it already running
        $max_wait = 10; // seconds
//        $sript_name = substr($_SERVER['SCRIPT_NAME'], 1); // remove leading '/'
        $sript_path = $_SERVER['SCRIPT_FILENAME'];
        $sript_name = basename($sript_path);
        $pids = getPIDByName($sript_name, 'php', $active_only = false);
        // Stop if needed
        if ($pids && (($cmd == 'restart') || ($cmd == 'stop'))) {
            $mode = 0600;
            if (! file_exists(PIPE_CMD)) {
                // create the pipe
                umask(0);
                posix_mkfifo(PIPE_CMD, $mode);
            }
            $f = fopen(PIPE_CMD, "w+"); // make it non-blocking as the receiver may be hang
            fwrite($f, "CMD=EXIT");
            fclose($f);
            for ($i = 0; $i < $max_wait; $i ++) {
                $pids = getPIDByName($sript_name, 'php', $active_only = false);
                if (! $pid)
                    break;
            }
            if ($pids) { // did not exit
                foreach ($pids as $proc) {
                    exec('kill -9 ' . $proc['pid'], $output, $retval);
                }
            }
        }
        $pids = getPIDByName($sript_name, 'php', $active_only = false);
        if (!$pids && ($cmd != 'stop')) {
            exec($sript_path . ' > /dev/null 2>&1 &'); // "> /dev/null 2>&1 &" makes sure it is really really run as a background job that does not wait for input
// wait it to run
            for ($i = 0; $i < $max_wait; $i ++) {
                $pids = getPIDByName($sript_name, 'php', $active_only = false);
                if ($pid) break;
            }
            
        }
        if (($cmd == 'restart') || ($cmd == 'start') || ($cmd == 'stop') || !pids) { // nothing else to do if it is not running
            // just respond with $pids xml
            $xml = new SimpleXMLElement("<?xml version='1.0'  standalone='yes'?><lwir16_daemon_status/>");
            if ($pids) {
                /*
                echo "<pre>\n";
                print_r($pids);
                echo "</pre>\n";
                exit(0);
                */
                foreach ($pids[0] as $key => $value) {
                    if (is_array($value)){
                        $xml_arr= $xml->addChild($key);
                        for ($i = 0; $i < count($value); $i++) {
                            $xml_arr->addChild($key.$i, $value[$i]);
                        }
                       // $value = serialize($value);
                    } else {
                        $xml->addChild($key, $value);
                    }
                    
                }
                if (count($pids) > 1) { // abnormal
                    $arr = array();
                    for ($i = 1; $i < count($pids); $i ++) {
                        $arr[] = $pids[$i]['pid'];
                    }
                    $xml->addChild('other_pids', implode(',', $arr));
                }
            } else {
                $xml->addChild($sript_name, 'not running');
            }
            $rslt=$xml->asXML();
            header("Content-Type: text/xml");
            header("Content-Length: ".strlen($rslt)."\n");
            header("Pragma: no-cache\n");
            printf($rslt);
            exit(0);
        }
        // process other commands (all but status are 'silent'
        $parameters = array();
        unset ($daemon_cmds);
        foreach($_GET as $key=>$value) {
            if ($key != DAEMON_CTRL){ // already processed
                if (($key == DAEMON_CTRL_CMDS) || ($key == DAEMON_CMD)){ // CMD/cmd confusion
                    $daemon_cmds = $value;
                } else {
                    $parameters[$key]= $value;
                }
            }
        }
        if ($cmd == 'status'){
            if (!isset($daemon_cmds)){
                $daemon_cmds=CMD_STATUS;
            } else if (strstr($daemon_cmds,CMD_STATUS) === false){
                $daemon_cmds .= ','.CMD_STATUS;
            }
        }
        $cmds_to_send = "";
        foreach ($parameters as $key=>$value){
            $cmds_to_send .= sprintf("%s = %s\n",$key,$value);
        }
        $cmds_to_send .= sprintf("%s = %s\n",DAEMON_CMD,$daemon_cmds);
        $has_status= strstr($daemon_cmds,CMD_STATUS) !== false;
        
        $mode=0600;
        if(!file_exists(PIPE_CMD)) {
            // create the pipe
            umask(0);
            posix_mkfifo(PIPE_CMD,$mode);
        }
        if (file_exists(PIPE_RESPONSE)){
            unlink(PIPE_RESPONSE); //delete old pipe
        }
        /*
        echo "<pre>\n";
        print_r($cmds_to_send);
        echo 'has_status='.$has_status."\n";
        echo 'daemon_cmds='.$daemon_cmds."\n";
        echo "parameters\n";
        print_r($parameters);
        echo "</pre>\n";
        exit(0);
        */
        $f = fopen(PIPE_CMD,"w");
        fwrite($f,$cmds_to_send);
//        echo "(w) sent commands:\n".$cmds."\n";
        fclose ($f);
//        echo "(w) closed\n";
        if ($has_status) {
            while (!file_exists(PIPE_RESPONSE)); // just wait
//            echo "(w) got PIPE_RESPONSE\n";
            $rslt = file_get_contents(PIPE_RESPONSE);
//            var_dump($fl);
            unlink(PIPE_RESPONSE); //delete pipe
            header("Content-Type: text/xml");
            header("Content-Length: ".strlen($rslt)."\n");
            header("Pragma: no-cache\n");
            printf($rslt);
            exit(0);
        } else {
            $xml = new SimpleXMLElement("<?xml version='1.0'  standalone='yes'?><lwir16_daemon_input/>");
            foreach ($parameters as $key => $value) {
                $xml->addChild($key, $value);
            }
            if ($daemon_cmds) {
                $xml->addChild(DAEMON_CMD, $daemon_cmds);
            }
            $rslt=$xml->asXML();
            header("Content-Type: text/xml");
            header("Content-Length: ".strlen($rslt)."\n");
            header("Pragma: no-cache\n");
            printf($rslt);
            exit(0);
        }
    }
   
?>