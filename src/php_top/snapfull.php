#!/usr/local/sbin/php -q
<?php
/*
 * !
 * ! PHP script
 * ! FILE NAME : snapfull.php
 * ! DESCRIPTION: acquiring a single-frame with different frame size than currently used (i.e. for streaming)
 * ! AUTHOR : Elphel, Inc.
 * ! Copyright (C) 2008 Elphel, Inc
 * ! -----------------------------------------------------------------------------**
 * !
 * ! This program is free software: you can redistribute it and/or modify
 * ! it under the terms of the GNU General Public License as published by
 * ! the Free Software Foundation, either version 3 of the License, or
 * ! (at your option) any later version.
 * !
 * ! This program is distributed in the hope that it will be useful,
 * ! but WITHOUT ANY WARRANTY; without even the implied warranty of
 * ! MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * ! GNU General Public License for more details.
 * !
 * ! You should have received a copy of the GNU General Public License
 * ! along with this program. If not, see <http://www.gnu.org/licenses/>.
 * ! -----------------------------------------------------------------------------**
 * ! $Log: snapfull.php,v $
 * ! Revision 1.3 2009/10/12 19:20:24 elphel
 * ! Added "Content-Disposition" support to suggest filenames to save images
 * !
 * ! Revision 1.2 2008/12/14 05:37:11 elphel
 * ! Added turning off autoexposure+white balance during snapshot sequence
 * !
 * ! Revision 1.1.1.1 2008/11/27 20:04:03 elphel
 * !
 * !
 * ! Revision 1.5 2008/11/20 01:48:39 elphel
 * ! added streamer autostart
 * !
 * ! Revision 1.4 2008/11/17 23:42:04 elphel
 * ! changed myval() to accept numbers in ""
 * !
 * ! Revision 1.3 2008/11/15 20:24:39 elphel
 * ! added default left/top
 * !
 * ! Revision 1.2 2008/11/15 20:16:39 elphel
 * ! removed debugging garbage
 * !
 * ! Revision 1.1 2008/11/15 20:10:12 elphel
 * ! Added script to capture single frames of different resolution (usually full) than currently being streamed
 * !
 */
$sensor_port = 0;
if ($_GET ['sensor_port'] != NULL) {
	$sensor_port = myval ( $_GET ['sensor_port'] );
}
$imgsrv_port = 2323 + $sensor_port; // read port from some centralized place
$imgsrv='http://'.$_SERVER['HTTP_HOST'].':'.strval($GLOBALS['imgsrv_port']).'/';

$suggestSave = false;
$ahead = 3;
$delay = 2;
$sensorSize = array (
		'SENSOR_WIDTH' => 0,
		'SENSOR_HEIGHT' => 0 
);
$sensorSize = elphel_get_P_arr ($sensor_port, $sensorSize );
// / Use default width, height, decimation and binning if they are not specified in the HTTP GET parameters
$parsForSnap = array (
		'WB_EN' => 0,
		'AUTOEXP_ON' => 0,
		'WOI_WIDTH' => $sensorSize ['SENSOR_WIDTH'],
		'WOI_HEIGHT' => $sensorSize ['SENSOR_HEIGHT'],
		'WOI_LEFT' => 0,
		'WOI_TOP' => 0,
		'DCM_HOR' => 1,
		'DCM_VERT' => 1,
		'BIN_HOR' => 1,
		'BIN_VERT' => 1 
);
foreach ( $_GET as $key => $value )
	switch ($key) {
		case 'save' :
			$suggestSave = true;
			break;
		case 'ahead' :
			$ahead = myval ( $value );
			break;
		case 'delay' :
			$delay = myval ( $value );
			break;
		default : // / treat as camera native parameters
			$parsForSnap [$key] = myval ( $value );
	}
	// / Add default width, height, decimation and binning if they are not specified in the HTTP GET parameters
$parsSaved = elphel_get_P_arr ($sensor_port, $parsForSnap );
$thisFrameNumber = elphel_get_frame ($sensor_port);
if ($ahead > 5) {
	elphel_wait_frame_abs ($sensor_port, $thisFrameNumber + $ahead - 5 );
	$ahead -= 5;
	$thisFrameNumber = elphel_get_frame ($sensor_port);
}
$pgmFrameNumber = $thisFrameNumber + $ahead;
// /set modified parameters to the camera
elphel_set_P_arr ($sensor_port, $parsForSnap, $pgmFrameNumber );
// /set original parameters ($delay frames later)
elphel_wait_frame_abs ($sensor_port, $thisFrameNumber + $delay );
elphel_set_P_arr ($sensor_port, $parsSaved, $pgmFrameNumber + $delay );
// / Wait for the frame to be acquired
elphel_wait_frame_abs ($sensor_port, $pgmFrameNumber + 2 ); // / the frame should be in circbuf by then
$circbuf_pointers = elphel_get_circbuf_pointers ($sensor_port, 1 ); // / 1 - skip the vary oldest frame
$meta = end ( $circbuf_pointers );
if (! count ( $circbuf_pointers ) || ($meta ['frame'] < $pgmFrameNumber)) {
	echo "compressor is turned off";
	echo "<pre>\n";
	print_r ( $circbuf_pointers );
	echo "</pre>\n";
	exit ( 0 );
}
// / look in the circbuf array (in case we already missed it and it is not the latest)
while ( $meta ['frame'] > $pgmFrameNumber ) {
	if (! prev ( $circbuf_pointers )) { // / failed to find the right frame in circbuf - probably overwritten
		printf ( "<pre>could not find the frame %d(0x%x) in the circbuf:\n", $pgmFrameNumber, $pgmFrameNumber );
		print_r ( $circbuf_pointers );
		echo "\n</pre>";
		exit ( 0 );
	}
	$meta = current ( $circbuf_pointers );
}
// / Redirect browser to the imgsrv with the frame needed. Unfortunately "reload" button in the browser will not work
if ($suggestSave)
	header ( 'Location: ' . $imgsrv . $meta ['circbuf_pointer'] . '/sbimg' );
else
	header ( 'Location: ' . $imgsrv . $meta ['circbuf_pointer'] . '/bimg' );
exit ();
function myval($s) {
	$s = trim ( $s, "\" " );
	if (strtoupper ( substr ( $s, 0, 2 ) ) == "0X")
		return intval ( hexdec ( $s ) );
	else
		return intval ( $s );
}

?>
