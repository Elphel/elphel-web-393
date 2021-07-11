<?php
/*!*******************************************************************************
*! FILE NAME  : reset_frames.php
*! DESCRIPTION: reset all sensor channels frame numbers (including hardware sequencer)
*               May be needed at least once before (and after?) switching from
*               free-running mode to all channels triggered from the common source.
*               Optional parameter frame: if frame >0 - wait for the specified absolute
*               frame number on a master port, frame <= 0 - skip frames before resetting
*               Default is frame = -1, it waits fro the new frame (on master port) before
*               resetting all channels.
*               Resetting disturbs frame sequencer, so it is better not to apply any 
*               frame commands 16 frames before this command as they may be lost.  
*               
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
 // TODO set include path, like in set_include_path ( get_include_path () . PATH_SEPARATOR . '/www/pages/include' );
   include 'include/show_source_include.php';
   include "include/elphel_functions_include.php"; // includes curl functions
   $sysfs_all_frames =     '/sys/devices/soc0/elphel393-framepars@0/all_frames'; // read all frames, write - reset frames
   $frame = -1; // positive - wait absolute frame number for the master port, negative - skip frame(s)
   $duration = 100;
   $pre_delay = 5.0; // seconds
   $compressor_run =     0; // stop all
   
   foreach($_GET as $key=>$value) {
       if (($key == 'f')   || ($key == 'frame')){
           $frame = (integer) $value;
       } else if (($key == 'ip') || ($key == 'ips')){ //  multicamera operation
           $ips = explode(',',$value);
       } else if ($key == 'lwir16'){
           $lswir16cmds = explode(',',$value);
       } else if ($key == 'pre_delay'){
           $pre_delay = (double) $value; // only used with capture
       } else if (($key == 'd')   || ($key == 'duration')){
           // TODO: make durations optionally a list, same as port mask (for EO - different)
           // wait - apply to the first IP in a list? or to the last?
           //            $duration = (integer) $value;
           $duration = (int) $value; // EO - make 1/6 +1 frames
       } else if ($key == 'nowait'){
           $nowait = 1;
       } else if ($key == 'run'){
           $compressor_run = 2;
       }
   }
//   print_r($lswir16cmds);
//   print('done');
//   exit(0);
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
       if (!isset ($ips)){
           $ips=array();
           $ips[] = '192.168.0.41';
           $ips[] = '192.168.0.42';
           $ips[] = '192.168.0.43';
           $ips[] = '192.168.0.44';
           $ips[] = '192.168.0.45';
       }
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
//               $duration = 100;
//               $pre_delay = 5.0; // seconds
               $sensor_port = 0;
               $this_frame=elphel_get_frame($sensor_port);
               $this_timestamp=elphel_frame2ts($sensor_port,$this_frame);
               $timestamp = $this_timestamp + $pre_delay; // this will be a delay between capture sequences
           
               $urls = array();
               for ($i = 0; $i<count($ips); $i++){
                   // $_SERVER[SCRIPT_NAME] STARTS WITH '/'
                   $url = 'http://'.$ips[$i].'/capture_range.php?sensor_port='.$sensor_port; //
                   $url .= '&ts='.$timestamp; // &timestamp" -> ×tamp
                   $url .= '&port_mask=15'; // .$port_mask[$i];
                   $dur = ($i < 4) ? $duration :((int) ($duration/6) + 1);
                   if ($duration <= 0){
                       $dur = $duration; // for negaive (start) and zero (stop) 
                   }
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
           }
       }
   }
//   print("bug!");
//   exit(0);
   
   if (isset($ips)){ // start parallel requests to all cameras ($ips should include this one too), collect responses and exit
       $results = resetIPs($ips);
       $xml = new SimpleXMLElement("<?xml version='1.0'  standalone='yes'?><reset_frames/>");
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
   }
 
   function resetIPs($ips) {
       $urls = array();
       for ($i = 0; $i<count($ips); $i++){
           // $_SERVER[SCRIPT_NAME] STARTS WITH '/'
           $url = 'http://'.$ips[$i].$_SERVER[SCRIPT_NAME].'?frame='.$frame; //
           $urls[] = $url;
       }
       $curl_data = curl_multi_start ($urls);
       $enable_echo = false;
       return curl_multi_finish($curl_data, true, 0, $enable_echo); // Switch true -> false if errors are reported (other output damaged XML)
   }

   function skipFrames($ips,$skip) {
       $urls = array();
       for ($i = 0; $i<count($ips); $i++){
           // $_SERVER[SCRIPT_NAME] STARTS WITH '/'
//           $url = 'http://'.$ips[$i].$_SERVER[SCRIPT_NAME].'?frame='.$frame; //
           $url = 'http://'.$ips[$i].'/wait_frame.php?frame='.(-$skip); //
           $urls[] = $url;
       }
       $curl_data = curl_multi_start ($urls);
       $enable_echo = false;
       return curl_multi_finish($curl_data, true, 0, $enable_echo); // Switch true -> false if errors are reported (other output damaged XML)
   }
/*
       $urls = array();
       for ($i = 0; $i<count($ips); $i++){
           // $_SERVER[SCRIPT_NAME] STARTS WITH '/'
           $url = 'http://'.$ips[$i].$_SERVER[SCRIPT_NAME].'?frame='.$frame; //
           $urls[] = $url;
       }
       $curl_data = curl_multi_start ($urls);
       $enable_echo = false;
       $results =  curl_multi_finish($curl_data, true, 0, $enable_echo); // Switch true -> false if errors are reported (other output damaged XML)

 */   
   
   $f = @fopen($sysfs_all_frames, "w");
   if ($f===false) {
       $xml = new SimpleXMLElement("<?xml version='1.0'  standalone='yes'?><error/>");
       $xml->addChild ('error',print_r(error_get_last(),1));
   } else {
       $fw=@fwrite($f, strval($frame));
       if ($fw===false){
           $xml = new SimpleXMLElement("<?xml version='1.0'  standalone='yes'?><error/>");
           $xml->addChild ('error',print_r(error_get_last(),1));
       } else {
           fclose($f);
           $xml = new SimpleXMLElement("<?xml version='1.0'  standalone='yes'?><reset_frames/>");
           $xml->addChild ('frame',$frame);
       }
   }
   $rslt=$xml->asXML();
   header("Content-Type: text/xml");
   header("Content-Length: ".strlen($rslt)."\n");
   header("Pragma: no-cache\n");
   printf($rslt);
?>