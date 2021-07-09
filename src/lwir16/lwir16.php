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
   define("REG_FFC_FRAMES",     "SENSOR_REGS4");     // Register for the number of FFC frames to integrate
   define("REG_FFC_RUN",        "SENSOR_REGS26");    // Register to trigger FFC
   define("SCRIPT_RESET",       "reset_frames.php"); // Reset frame numbers
   define("SCRIPT_WAIT",        "wait_frame.php");   // wait frame
   $duration = 100;
   $pre_delay = 5.0; // seconds
   $compressor_run =     0; // stop all
   $ffc =        false; // perform FFC before starting a sequence (and before delay? reduce delay ?)
   $ffc_groups = 2; // 1/2/4 - do not run FFC on all channels simultaneously (43 failed)
   $ffc_frames = 8; // read actual?
   $ffc_wait_frames =      10; // extra wait after FFC finished (2xffc_frames)
   
//   public static final String REG_FFC_FRAMES= "SENSOR_REGS4";      // Register for the number of FFC frames to integrate
//   public static final String REG_FFC_RUN=    "SENSOR_REGS26";     // Register to trigger FFC
   
   foreach($_GET as $key=>$value) {
       if (($key == 'ip') || ($key == 'ips')){ //  multicamera operation
           $ips = explode(',',$value);
       } else if (($key == 'lwir16') || ($key == 'cmd')){
           $lswir16cmds = explode(',',$value);
       } else if ($key == 'pre_delay'){
           $pre_delay = (double) $value; // only used with capture
       } else if (($key == 'd')   || ($key == 'duration')){
           $duration = (int) $value; // EO - make 1/6 +1 frames
       } else if (($key == 'de')   || ($key == 'duration_eo')){
           $duration_eo = (int) $value; // EO - make 1/6 +1 frames
       } else if ($key == 'nowait'){
           $nowait = 1;
       } else if ($key == 'run'){
           $compressor_run = 2;
       } else if ($key == 'ffc'){
           
           
           
           $ffc = true;
           if ($value) { // string "0" will also be false
               $v = (int) $value;
               if (($v == 1) || ($v == 2) || ($v == 4)){
                   $ffc_groups = $v;
               }
           }
           
//           printf("<!--\n");
//           printf('$ffc_groups= '.$ffc_groups."\n");
//           printf('$ffc=        '.$ffc."\n");
//           printf("-->\n");
           
           
       }
   }
   if (!isset ($ips)){
       $ips=array();
       $ips[] = '192.168.0.41';
       $ips[] = '192.168.0.42';
       $ips[] = '192.168.0.43';
       $ips[] = '192.168.0.44';
       $ips[] = '192.168.0.45';
   }
   if ($duration < 1){
       $duration = 1;
   }
   if (!isset($duration_eo)){
       $duration_eo =  ($duration/6) + 1; // default
   }
   if ($duration_eo < 1){
       $duration_eo = 1;
   }
   
   if (isset($lswir16cmds)){
//       exit(0);
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
       
       $extra = 2;
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
       
       $lwir_ips= array($ips[0],$ips[1],$ips[2],$ips[3]);
//       $twoIPs= array($ips[0],$ips[4]);
       $twoIPs= $ips; // array($ips[0],$ips[4]); wait all
//       print_r($lswir16cmds);
//       exit(0);
       for ($ncmd = 0; $ncmd < count($lswir16cmds); $ncmd++){
           $cmd = $lswir16cmds[$ncmd];
//           print('cmd='.$cmd);
//           exit(0);
           if ($cmd == 'init'){
//               print_r($twoIPs);
//               exit(0);
               $results0 = skipFrames($twoIPs, 2);
//               print_r($results0);
               $results1 = resetIPs($ips); // sync channels in each subcamera
//               print_r($results1);
               $results2 = skipFrames($twoIPs, 16); // was 1
//               print_r($results2); print("<br/>");
               $urls = array(); // eo - individual
               for ($i = 0; $i < (count($ips) + 3); $i++){
                   $nip = $i;
                   if ($nip >= count($ips)){
                       $nip = count($ips)-1;
                   }
                   $urls[$i] = 'http://'.$ips[$nip].'/parsedit.php?immediate&sensor_port='.($i - $nip);
                   $urls[$i] .= '&TRIG_OUT=0x66555'.
                   '&TRIG_CONDITION=0x95555'.
                   '&TRIG_BITLENGTH=31'.
                   '&EXTERN_TIMESTAMP=1'.
                   '&XMIT_TIMESTAMP=1';
               }
//               print_r($urls); print("<br/>");
//               exit(0);
               for ($i = 0; $i < count($lwir_ips); $i++){
                   $urls[$i] .= '&TRIG_DELAY='.$lwir_trig_dly.'&*TRIG_DELAY=15'. // apply to all ports
                   '&BITS=16&*BITS=15'.
                   '&COLOR='.$COLOR_RAW .'&*COLOR=15'.
                   '&WOI_HEIGHT='.($LWIR_HEIGHT + ($LWIR_TELEMETRY ? $LWIR_TELEMETRY_LINES : 0)).'&*WOI_HEIGHT=15'.
                   '&'.$REG_FFC_FRAMES.'='.$FFC_FRAMES .'&*'.$REG_FFC_FRAMES.'=15'; // apply to all channels
                   $urls[$i] .= '&COMPRESSOR_RUN=2&*COMPRESSOR_RUN=15';
               }
//               print_r($urls); print("<br/>");
               for ($chn = 0; $chn < 4; $chn++){
                   $urls[count($ips)-1 + $chn] .=
                   "&COLOR=".          $COLOR_JP4.
                   "&QUALITY=".        $eo_quality.
                   "&EXPOS=".          $exposure.
                   "&AUTOEXP_EXP_MAX=".$autoExposureMax.
                   "&AUTOEXP_ON=".     $autoExp.
                   "&GAING=".          $gain.
                   "&RSCALE=".         $rScale.//"*0".
                   "&BSCALE=".         $bScale.//"*0".
                   "&GSCALE=".         $gScale.//"*0". // GB/G ratio
                   "&WB_EN=".          $autoWB.//"*0".
                   "&DAEMON_EN_TEMPERATURE=1";//"*0";
                   if (lrp.eo_full_window) {
                       $urls[count($ips)-1 + $chn] .=
                       "&WOI_LEFT=0".
                       "&WOI_TOP=0".
                       "&WOI_WIDTH=2592".
                       "&WOI_HEIGHT=1936";
                   }
                   if ($chn == 0) {
                       $urls[count($ips)-1] .= '&COMPRESSOR_RUN=2&*COMPRESSOR_RUN=15';
                   }
               }
//               print_r($urls);
//               exit(0);
               $curl_data = curl_multi_start ($urls);
               $enable_echo = false;
               $results3= curl_multi_finish($curl_data, true, 0, $enable_echo); // Switch true -> false if errors are reported (other output damaged XML)
               
               $results4 = skipFrames($twoIPs, 16); 
               // set external trigger mode for all LWIR and EO cameras
               $urls = array();
               for ($i = 0; $i<count($ips); $i++){
                   $urls[] = 'http://'.$ips[$i].'/parsedit.php?immediate&sensor_port=0&TRIG=4&*TRIG=15'.
                       '&COMPRESSOR_RUN='.$compressor_run.'*5&*COMPRESSOR_RUN=15'; // delay turning off COMPRESSOR_RUN
               }
               $curl_data = curl_multi_start ($urls);
               $enable_echo = false;
               $results5 = curl_multi_finish($curl_data, true, 0, $enable_echo); // Switch true -> false if errors are reported (other output damaged XML)
               $results6 = skipFrames($twoIPs, 16);  // make sure all previous parameters are applied // waits for both LWIR and EO
               // second reset after cameras running synchronously
               $results7 = resetIPs($ips); // sync channels in each subcamera
               $results8 = skipFrames($twoIPs, 16); // was 2
               
               $results9 = resetIPs($ips); // sync channels in each subcamera
               $results10 = skipFrames($twoIPs, 16); // was 2
               $results = $results10;
               /*
               print("<br/>results0:");print_r($results0);print("<br/>");    
               print("<br/>results1:");print_r($results1);print("<br/>");
               print("<br/>results2:");print_r($results2);print("<br/>");
               print("<br/>results3:");print_r($results3);print("<br/>");
               print("<br/>results4:");print_r($results4);print("<br/>");
               print("<br/>results5:");print_r($results5);print("<br/>");
               print("<br/>results6:");print_r($results6);print("<br/>");
               print("<br/>results7:");print_r($results7);print("<br/>");
               print("<br/>results8:");print_r($results8);print("<br/>");
               */
               $xml = new SimpleXMLElement("<?xml version='1.0'  standalone='yes'?><lwir16_init/>");
               for ($i = 0; $i<count($results); $i++){
                   $xml_ip = $xml->addChild ('ip_'.$ips[$i]);
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
               $sensor_port = 0;
               if ($ffc){ // may move after measuring time, but need to make sure it will be not too late
                   runFFC($lwir_ips, $ffc_groups, $ffc_frames,  $ffc_wait_frames);
               }
               
               $this_frame=elphel_get_frame($sensor_port);
               $this_timestamp=elphel_frame2ts($sensor_port,$this_frame);
               $timestamp = $this_timestamp + $pre_delay; // this will be a delay between capture sequences
//               if ($ffc){ // may be here, then need to check that there is some time left
//                   runFFC($lwir_ips, $ffc_groups, $ffc_frames,  $ffc_wait_frames);
//               }
               
           
               $urls = array();
               for ($i = 0; $i<count($ips); $i++){
                   // $_SERVER[SCRIPT_NAME] STARTS WITH '/'
                   $url = 'http://'.$ips[$i].'/capture_range.php?sensor_port='.$sensor_port; //
                   $url .= '&ts='.$timestamp; // &timestamp" -> ×tamp
                   $url .= '&port_mask=15'; // .$port_mask[$i];
                   $dur = ($i < 4) ? $duration : $duration_eo;
                   $url .= '&duration='. $dur;
//                   $url .= '&maxahead='. $maxahead;
//                   $url .= '&minahead='. $minahead;
                   $url .= '&extra='.    $extra;
                   if ($wait && ($i == (count($ips) - 1))){ // addd to the last ip in a list
                       $url .= '&wait';
                   }
                   $urls[] = $url;
               }
//               print ('URLs:'); print_r($urls); print ('<br/');
//               exit(0);
               $curl_data = curl_multi_start ($urls);
               $enable_echo = false;
               $results =  curl_multi_finish($curl_data, true, 0, $enable_echo); // Switch true -> false if errors are reported (other output damaged XML)
//               print ('results:'); print_r($results); print ('<br/');
//               exit(0);
               $xml = new SimpleXMLElement("<?xml version='1.0'  standalone='yes'?><capture_range/>");
               $xml->addChild ('ffc', $ffc ? 'true':'false');
               $xml->addChild ('ffc_groups',$ffc_groups);
               $xml->addChild ('ffc_frames',$ffc_frames);
               for ($i = 0; $i<count($ips); $i++){
                   $xml_ip = $xml->addChild ('ip_'.$ips[$i]);
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
               for ($i = 0; $i<count($ips); $i++){
                   $xml_ip = $xml->addChild ('ip_'.$ips[$i]);
                   $xml_ip->addChild('reboot','started');
               }
               $rslt=$xml->asXML();
               header("Content-Type: text/xml");
               header("Content-Length: ".strlen($rslt)."\n");
               header("Pragma: no-cache\n");
               printf($rslt);
               // one of the next flushes prevent running reboot
//               ob_flesh();
//               flush();
               exec ( 'autocampars.py ['.implode(',',$ips).'] pyCmd reboot', $output, $retval );
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
//               printf('<!--');
//               var_dump($_GET);
//               printf( '-->');
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
       
       
   }
   
   /*
   $ffc_groups = 2; // 1/2/4 - do not run FFC on all channels simultaneously (43 failed)
   $ffc_frames = 8; // read actual?
   $ffc_wait_frames =      10; // extra wait after FFC finished (2xffc_frames)

   */
   function runFFC($lwir_ips, $ffc_groups, $ffc_frames,  $ffc_wait_frames) { // return number of frames used
       
       $skipped = 0;
       $port_masks =array();
       foreach ($lwir_ips as $l){
           $port_masks[] = 15; // select all 4 ports 
       }
       if      ($ffc_groups == 1) $group_masks = array(15);
       else if ($ffc_groups == 2) $group_masks = array(5, 10);
       else                       $group_masks = array(1, 2, 4, 8);
//       printf("<!--\n");
//       printf('$ffc_groups=     '.$ffc_groups."\n");
//       printf('$ffc_frames=     '.$ffc_frames."\n");
//       printf('$ffc_wait_frames='.$ffc_wait_frames."\n");
//       print_r($group_masks);
//       printf("-->\n");
       for ($ig = 0; $ig < $ffc_groups; $ig++){
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
//           printf("<!--\n");
//           printf('$ig=             '.$ig."\n");
//           print_r($urls);
//           printf("-->\n");
           if ($urls){ // not empty
               $curl_data = curl_multi_start ($urls);
               $enable_echo = false;
               $results =  curl_multi_finish($curl_data, true, 0, $enable_echo); // Switch true -> false if errors are reported (other output damaged XML)
           }
           $skip_frames = 2 * $ffc_frames + $ffc_wait_frames;
           skipFrames(array($lwir_ips[$ip0]),  $skip_frames);
       }
       return $skipped; // better read actual frame after
   }
 
//   define("REG_FFC_FRAMES",     "SENSOR_REGS4");   // Register for the number of FFC frames to integrate
//   define("REG_FFC_RUN",        "SENSOR_REGS26"); // Register to trigger FFC
   
   
   function resetIPs($ips) {
       $frame = -2; // skip 2 frames before reset
       $urls = array();
       for ($i = 0; $i<count($ips); $i++){
           // $_SERVER[SCRIPT_NAME] STARTS WITH '/'
           $url = 'http://'.$ips[$i].'/'.SCRIPT_RESET.'?frame='.$frame; //
           $urls[] = $url;
       }
//       printf("<!--\n");
//       print_r($urls);
//       printf("-->\n");
       
       $curl_data = curl_multi_start ($urls);
       $enable_echo = false;
       return curl_multi_finish($curl_data, true, 0, $enable_echo); // Switch true -> false if errors are reported (other output damaged XML)
   }

   function skipFrames($ips,$skip) {
       $urls = array();
       for ($i = 0; $i<count($ips); $i++){
//           $url = 'http://'.$ips[$i].$_SERVER[SCRIPT_NAME].'?frame='.$frame; //
           $url = 'http://'.$ips[$i].'/'.SCRIPT_WAIT.'?frame='.(-$skip); //
           $urls[] = $url;
       }
       $curl_data = curl_multi_start ($urls);
       $enable_echo = false;
       return curl_multi_finish($curl_data, true, 0, $enable_echo); // Switch true -> false if errors are reported (other output damaged XML)
   }
?>