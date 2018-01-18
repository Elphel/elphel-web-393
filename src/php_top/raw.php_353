#!/usr/local/sbin/php -q
<?php
/*!*******************************************************************************
*! FILE NAME  : raw.php
*! DESCRIPTION:
*! Copyright (C) 2008 Elphel, Inc
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
*!  $Log: raw.php,v $
*!  Revision 1.2  2010/02/01 20:21:46  dzhimiev
*!  1. updated the code
*!  2. added 'stop' option in order not to return the sensor in continuous mode and thus keep the raw frame in buffer
*!  3. added setting 8/16 bits for BITS
*!
*!  Revision 1.1  2009/10/15 19:42:16  oneartplease
*!  added raw demo files
*!
*!  Revision 1.2  2008/08/18 06:00:34  elphel
*!  Made it acquire one image at a  time
*!
*!  Revision 1.1  2008/08/18 03:27:06  kimstig
*!  Raw image access demo
*!
*!
*/

$sensor_port = 0;
if ($_GET ['sensor_port'] != NULL) {
	$sensor_port = $_GET ['sensor_port'];
}

function myval ($s) {
	$s=trim($s,"\" ");
	if (strtoupper(substr($s,0,2))=="0X")   return intval(hexdec($s));
	else return intval($s);
}

$stop=false;
$parsForRaw=array('SENSOR_RUN'=>1,'BITS'=>8);

foreach($_GET as $key=>$value) switch ($key){
	case 'stop':
		$stop=true;
	break;
	default:  /// treat as camera native parameters
		$parsForRaw[$key]=myval($value);
}

$name = "/dev/image_raw";

$parsSaved=elphel_get_P_arr($sensor_port, $parsForRaw);
$thisFrameNumber=elphel_get_frame($sensor_port);

elphel_set_P_arr ($sensor_port, $parsForRaw, $thisFrameNumber);
elphel_wait_frame_abs($sensor_port, $thisFrameNumber);

$fp = fopen($name, 'rb');
fseek($fp, 0, SEEK_END);  /// file pointer at the end of the file (to find the file size)
$fsize = ftell($fp);      /// get file size
fseek($fp, 0, SEEK_SET);  /// rewind to the start of the file
/// send the headers
header("Content-Type: application/octet-stream");
header('Content-Disposition: attachment; '.'filename="image.raw"');
header("Content-Length: ".$fsize."\n");
header("Pragma: no-cache\n");
fpassthru($fp);           /// send the raw data itself
fclose($fp);

if (!$stop) {
	$parsSaved['SENSOR_RUN']=2;
	elphel_set_P_arr ($sensor_port, $parsSaved, $thisFrameNumber);
}

?>