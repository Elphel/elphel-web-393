#!/usr/bin/php
<?php
/*!*******************************************************************************
*! FILE NAME  : capture_range.php
*! DESCRIPTION: Return frame number when the specified timestamp whil be reached 
*               or expected timestamp for the specified frame.
*               Useful to synchronize multiple camera triggered by one of them
*               when the master timestamp is sent to each camera. Normally
*               used with just the master sensor port.
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
   set_include_path ( get_include_path () . PATH_SEPARATOR . '/www/pages/include' );
   include 'show_source_include.php';
   include "elphel_functions_include.php"; // includes curl functions
   $minahead = 2;
   $PARS_FRAMES = 16;
   $maxahead = $PARS_FRAMES - 4; // 3;
   $flags = 0;
   
   if (!($_SERVER ['REQUEST_METHOD'] == "GET")){
       //Maybe re-write this in C or as a PHP function? 
       // CLI mode will be spawned bu the CGI and run in a background after request will be over
       $sensor_port = $argv[1]; // $argv[0] - script name
       $port_mask =   $argv[2];
       $frame =       $argv[3]; // absolute frame to start compressor
       $duration =    $argv[4]; // number of frames to run compressor
       
       if ($frame > 0){
           $frame +=1; // seem there is a bug - actual compressed frames numbers (and last compressed frame number) are 1 less than expected
       }
       // If 'duration'==0 - just stop compressor, if 'duration' <0 - only start, if duration >0 - start+stop
       
//       $f = fopen ( "/var/log/capture_range.log", 'a' );
//       fwrite($f,"elphel_capture_range($sensor_port, $port_mask, $frame, $duration);\n");
//       fclose ( $f );
       elphel_capture_range($sensor_port, $port_mask, $frame, $duration);
//       $f = fopen ( "/var/log/capture_range.log", 'a' );
//       fwrite($f,"elphel_capture_range DONE\n");
//       fclose ( $f );
       exit(0);
   }
//   $output=null;
   $retval=-1; // not used
   // kill CLI mode if it was running (e.g. waiting for 100 years)
//   exec("killall capture_range.php", $output, $retval);
//   exec("/www/pages/capture_range.php 1 2 3 4 > /dev/null 2>&1 &");
   
   if (count($_GET) < 1) {
       print ("CGI mode");
       echo <<<USAGE
   <p>This script returns the timestamp for specified frame (frame=?) or expected frame number
      when the specified timestamp (timestamp = ?.?) will be reached. If none of the frame number
      and timestamp are specified - return last compressed frame number and timestamp.</p>
   <p>Uses TRIG_PERIOD (in 10ns increments) for calculations.</p>
   <p>sensor_port=0..3 - specify which sensor port to use, default is sensor_port=0</p>
USAGE;
      exit (0);
    }
    $sensor_port=0;
    $frame = 0;
    $timestamp = 0.0;
    $PARS_FRAMES = 16;
    $minahead = 2;
//    $port_mask = 15; // not set - same as ts2frame.php
//    $duration =  1; // not set - end, set - start 
//    $wait = false;
    $extra = 2; // wait extra frames after stop to be sure compressor is flashed?
    foreach($_GET as $key=>$value) {
        if ($key == 'sensor_port'){
            $sensor_port = (integer) $value;
        } else if (($key == 'a')  || ($key == 'ahead')){ // will overwrite timestamp with this value (in seconds) from now
            $ahead_now = (double) $value;
        } else if (($key == 'ts')  || ($key == 'timestamp')){
            $timestamp = (double) $value;
        } else if (($key == 'f')   || ($key == 'frame')){
            $frame = (integer) $value;
        } else if (($key == 'm')   || ($key == 'port_mask')){
//            $port_mask = (integer) $value;
            $masklist = $value;
        } else if (($key == 'd')   || ($key == 'duration')){
// TODO: make durations optionally a list, same as port mask (for EO - different)            
 // wait - apply to the first IP in a list? or to the last?           
//            $duration = (integer) $value;
            $duration_list = $value;
        } else if (($key == 'mxa') || ($key == 'maxahead')){
            $maxahead = (integer) $value;
        } else if (($key == 'mna') || ($key == 'minahead')){
            $minahead = (integer) $value;
        } else if (($key == 'w')   || ($key == 'wait')){ // wait all done
            $wait = true;
        } else if (($key == 'e')   || ($key == 'extra')){
            $extra = (integer) $value; // not used?
        } else if (($key == 'ip') || ($key == 'ips')){ //  multicamera operation
            $ips = explode(',',$value);
        }
    }
    if (isset($ahead_now)){
        $this_frame=elphel_get_frame($sensor_port);
        $this_timestamp=elphel_frame2ts($sensor_port,$this_frame);
        $timestamp = $this_timestamp + $ahead_now;
    }
    // if ips are provided, treat port mask as commaseparated list
    //            $port_mask = (integer) $value;
    if (isset($ips)){
        $pml = explode(',', $masklist);
        $port_mask = array ();
        for ($i = 0; $i<count($ips); $i++){
            if ($i < count($pml)){
                $port_mask[] = (integer) $pml[$i];
            } else {
                $port_mask[] = 15; // default - all sensors
            }
        }
        $durl = explode(',', $duration_list);
        $duration = array();
        for ($i = 0; $i<count($ips); $i++){
            if ($i < count($durl)){
                $duration[] = (integer) $durl[$i];
            } else {
                $duration[] = $duration[0]; // at least one duration should be provided
            }
        }
    } else {
        $port_mask = (integer) $masklist;      // single valude
        $duration =  (integer) $duration_list; // single valude
    }
    
    // convert provided timestamp to even number of frame timestamp
    if (($frame !=0) || ($timestamp !=0.0)) {
        if (($frame <=0) && ($timestamp > 0.0)){
            $frame = elphel_ts2frame($sensor_port,$timestamp);
        }
        $timestamp = elphel_frame2ts($sensor_port,$frame); // update, even if provided to fit better integer number of frames
    }
    
    if (isset($ips)){ // start parallel requests to all cameras ($ips should include this one too), collect responses and exit
        // prepare URLs
        $urls = array();
        for ($i = 0; $i<count($ips); $i++){
            // $_SERVER[SCRIPT_NAME] STARTS WITH '/'
            $url = 'http://'.$ips[$i].$_SERVER[SCRIPT_NAME].'?sensor_port='.$sensor_port; //
            $url .= '&ts='.$timestamp; // &timestamp" -> ×tamp
            $url .= '&port_mask='.$port_mask[$i];
            $url .= '&duration='. $duration[$i];
            $url .= '&maxahead='. $maxahead;
            $url .= '&minahead='. $minahead;
            $url .= '&extra='.    $extra;
            if ($wait && ($i == (count($ips) - 1))){ // addd to the last ip in a list
                $url .= '&wait';
            }
            $urls[] = $url;
        }
//        print_r($urls);
//        exit(0);
        $curl_data = curl_multi_start ($urls);
        $enable_echo = false;
        $results =  curl_multi_finish($curl_data, true, 0, $enable_echo); // Switch true -> false if errors are reported (other output damaged XML)
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
    
    
    $xml = new SimpleXMLElement("<?xml version='1.0'  standalone='yes'?><capture_range/>");
    $flags = 0;
    if (isset($port_mask)){ // start or stop compressor
        $this_frame = elphel_get_frame($sensor_port);
        if ($frame == 0){ // e.g. to stop ASAP
            if (isset ($duration)) {
                elphel_compressor_run($sensor_port, 0, $flags, $port_mask); // turn on ASAP
                $xml->addChild ('compressor', 1);
            } else {
                elphel_compressor_stop($sensor_port, 0, $flags, $port_mask);// turn off ASAP
                $xml->addChild ('compressor', 0);
            }
            $frame = $this_frame + $minahead;
        }
        $ahead = $frame - $this_frame;
        if ($ahead < $minahead) {
            $xml->addChild ('error','TOO LATE');
            $xml->addChild ('ahead',$ahead);
        } else { // OK, enough time to program
            if (!isset($duration)) {
                $duration = 0;
            }
            // kill CLI mode if it was running (e.g. waiting for 100 years)
            exec("killall capture_range.php", $output, $retval);
            // spawn CLI program in background duration <0 - start only, ==0 - stop only, >0 - start+stop
//            $f = fopen ( "/var/log/capture_range.log", 'a' );
//            fwrite($f,"exec(/www/pages/capture_range.php $sensor_port $port_mask $frame $duration > /dev/null 2>&1 &\n");
//            fclose ( $f );
            exec("/www/pages/capture_range.php $sensor_port $port_mask $frame $duration > /dev/null 2>&1 &");
//            $f = fopen ( "/var/log/capture_range.log", 'a' );
//            fwrite($f,"DONE exec\n");
//            fclose ( $f );
            if ($duration > 0){
                $frame += $duration; // xml will contain end frame
            }
            if ($wait){
                elphel_wait_frame_abs($sensor_port, $frame + 1);
            }
        }
    } else { // just ts2frame.php mode
        if ($frame == 0){
            $timestamp = elphel_frame2ts($sensor_port,0);
            $frame = elphel_ts2frame($sensor_port,0.0);
        }
    }
    $xml->addChild ('retval', $retval);
    $xml->addChild ('frame',$frame);
    $xml->addChild ('timestamp',$timestamp);
    $this_frame=elphel_get_frame($sensor_port);
    $xml->addChild ('this_frame',$this_frame);
    $xml->addChild ('this_timestamp',elphel_frame2ts($sensor_port,$this_frame));
//    $xml->addChild ('frame',$frame);
//    $xml->addChild ('timestamp',$timestamp);
    
    $rslt=$xml->asXML();
    header("Content-Type: text/xml");
    header("Content-Length: ".strlen($rslt)."\n");
    header("Pragma: no-cache\n");
    printf($rslt);
?>
