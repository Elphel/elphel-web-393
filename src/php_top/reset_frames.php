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
   include 'include/show_source_include.php';
   $sysfs_all_frames =     '/sys/devices/soc0/elphel393-framepars@0/all_frames'; // read all frames, write - reset frames
   $frame = -1; // positive - wait absolute frame number for the master port, negative - skip frame(s)
   foreach($_GET as $key=>$value) {
       if (($key == 'f')   || ($key == 'frame')){
           $frame = (integer) $value;
       }
   }
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