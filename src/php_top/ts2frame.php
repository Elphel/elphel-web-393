<?php
/*!*******************************************************************************
*! FILE NAME  : ts2frame.php
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
   include 'include/show_source_include.php';
   if (count($_GET) < 1) {
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
    $timestamp=0.0;
    $toWrite=array();
    foreach($_GET as $key=>$value) {
        if ($key == 'sensor_port'){
            $sensor_port = (integer) $value;
        } else if (($key == 'ts') || ($key == 'timestamp')){
            $timestamp = (double) $value;
        } else if (($key == 'f') || ($key == 'frame')){
            $frame = (integer) $value;
        }
    }
    if ($frame > 0){
        $timestamp = elphel_frame2ts($sensor_port,$frame);
    } else if ($timestamp > 0.0){
        $frame = elphel_ts2frame($sensor_port,$timestamp);
    } else {
        $timestamp = elphel_frame2ts($sensor_port,0);
        $frame = elphel_ts2frame($sensor_port,0.0);
    }
    
    $xml = new SimpleXMLElement("<?xml version='1.0'  standalone='yes'?><ts2frame/>");
    $xml->addChild ('frame',$frame);
    $xml->addChild ('timestamp',$timestamp);
    $rslt=$xml->asXML();
    header("Content-Type: text/xml");
    header("Content-Length: ".strlen($rslt)."\n");
    header("Pragma: no-cache\n");
    printf($rslt);
?>
