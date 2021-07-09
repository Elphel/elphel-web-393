<?php
/*!*******************************************************************************
*! FILE NAME  : wait_frame.php
*! DESCRIPTION: Wait for absolute frame number (frame>0) or skip frames (frames <0),
*!              on the specified port (default sensor_port=0)  
*!              Default frame=-1 (skip 1 frame).
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
   $frame = -1; // positive - wait absolute frame number for the master port, negative - skip frame(s)
   $sensor_port = 0;
   foreach($_GET as $key=>$value) {
       if ($key == 'sensor_port'){
           $sensor_port = (integer) $value;
       } else if (($key == 'f')   || ($key == 'frame')){
           $frame = (integer) $value;
       } else if (($key == 'ip') || ($key == 'ips')){ //  multicamera operation
           $ips = explode(',',$value);
       }
   }
   if (isset ($ips)){
       $urls = array();
       for ($i = 0; $i<count($ips); $i++){
           // $_SERVER[SCRIPT_NAME] STARTS WITH '/'
           $url = 'http://'.$ips[$i].$_SERVER[SCRIPT_NAME].'?frame='.$frame; //
           $urls[] = $url;
       }
       $curl_data = curl_multi_start ($urls);
       $enable_echo = false;
       $results =  curl_multi_finish($curl_data, true, 0, $enable_echo); // Switch true -> false if errors are reported (other output damaged XML)
       $xml = new SimpleXMLElement("<?xml version='1.0'  standalone='yes'?><wait_frames/>");
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
   if ($frame < 0) {
       elphel_skip_frames($sensor_port, -$frame);
   } else if ($frame > 0) {
       elphel_wait_frame_abs ($sensor_port, $frame);
   }
   $this_frame = elphel_get_frame($sensor_port);
   $xml = new SimpleXMLElement("<?xml version='1.0'  standalone='yes'?><wait_frames/>");
   $xml-> addChild ('frame',$this_frame);
   $rslt=$xml->asXML();
   header("Content-Type: text/xml");
   header("Content-Length: ".strlen($rslt)."\n");
   header("Pragma: no-cache\n");
   printf($rslt);
?>