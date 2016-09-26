<?php
/*!*******************************************************************************
*! FILE NAME  : framepars.php
*! DESCRIPTION: Provides control of FPGA compressor/image acquisition
*!              to the circular buffer (circbuf)
*! Copyright (C) 2007-2008 Elphel, Inc
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
*!  $Log: framepars.php,v $
*!  Revision 1.3  2010/06/01 08:30:36  elphel
*!  support for the FPGA code 03534022  with optional master time stamp over the inter-camera sync line(s)
*!
*!  Revision 1.2  2010/05/13 03:35:28  elphel
*!  updates to reflect current drivers
*!
*!  Revision 1.1.1.1  2008/11/27 20:04:03  elphel
*!
*!
*!  Revision 1.26  2008/11/27 09:29:22  elphel
*!  new file - diag_utils.php that includes somewhat cleaned-up version of utilities from framepars.php. That file installation  is moved to attic
*!
*!  Revision 1.25  2008/11/17 23:42:04  elphel
*!  changed myval() to accept numbers in ""
*!
*!  Revision 1.24  2008/11/13 05:40:45  elphel
*!  8.0.alpha16 - modified histogram storage, profiling
*!
*!  Revision 1.23  2008/11/04 17:45:08  elphel
*!  added interface for testing new functions: elphel_gamma(), elphel_reverse_gamma(), elphel_histogram(), elphel_reverse_histogram(); links to individual gamma pages in the cache from "framepars.php?gamma_structure"
*!
*!  Revision 1.22  2008/10/31 18:26:32  elphel
*!  Adding support for constants like SENSOR_REGS32 (defined constant plus 32 to simplify referencing sensor registers from PHP
*!
*!  Revision 1.21  2008/10/29 04:18:28  elphel
*!  v.8.0.alpha10 made a separate structure for global parameters (not related to particular frames in a frame queue)
*!
*!  Revision 1.20  2008/10/28 07:03:03  elphel
*!  reports error when wrong constant name is entered (before in silently used 0)
*!
*!  Revision 1.19  2008/10/23 18:26:15  elphel
*!  Fixed percentile calculations in histograms
*!
*!  Revision 1.18  2008/10/23 08:11:58  elphel
*!  support for histograms testing
*!
*!  Revision 1.17  2008/10/22 03:47:45  elphel
*!  minor
*!
*!  Revision 1.16  2008/10/21 21:29:15  elphel
*!  using support fro xml output in imgsrv
*!
*!  Revision 1.15  2008/10/21 04:22:57  elphel
*!  XTRA_FPGA=1000 is included in cmd=init
*!
*!  Revision 1.14  2008/10/19 07:01:24  elphel
*!  updated to use  elphel_skip_frames();
*!
*!  Revision 1.13  2008/10/18 06:16:49  elphel
*!  changing test modes, init does not start compressor
*!
*!  Revision 1.12  2008/10/15 22:28:56  elphel
*!  snapshot 8.0.alpha2
*!
*!  Revision 1.11  2008/10/13 16:57:18  elphel
*!  switched to ELPHEL_* defined constants from locally defined ones
*!
*!  Revision 1.10  2008/10/13 04:48:25  elphel
*!  added sample gamma initialization to cmd=init
*!
*!  Revision 1.8  2008/10/11 18:46:07  elphel
*!  snapshot
*!
*!  Revision 1.7  2008/10/10 17:06:59  elphel
*!  just a snapshot
*!
*!  Revision 1.6  2008/10/06 08:31:08  elphel
*!  snapshot, first images
*!
*!  Revision 1.5  2008/10/05 05:13:33  elphel
*!  snapshot003
*!
*!  Revision 1.4  2008/10/04 16:10:12  elphel
*!  snapshot
*!
*!  Revision 1.3  2008/09/28 00:31:57  elphel
*!  snapshot
*!
*!  Revision 1.2  2008/09/25 00:58:12  elphel
*!  snapshot
*!
*!  Revision 1.1  2008/09/22 22:55:50  elphel
*!  snapshot
*!
*/

function myval($s) {
	$s = trim ( $s, "\" " );
	if (strtoupper ( substr ( $s, 0, 2 ) ) == "0X")
		return intval ( hexdec ( $s ) );
	else
		return intval ( $s );
}
$imgsrv = "http://" . $_SERVER ['SERVER_ADDR'] . ":8081";
if (count ( $_GET ) == 0) {
	echo <<<USAGE
   <p>This is a collection of tools for the 8.0 software development/testing, you can provide parameters in HTTP GET request.</p>
   <p>At this alpha stage very few programs/pages are working, among them:</p>
   <ul>
    <li><a href="tuneseq.php">tuneseq.php</a> - run-time tuning of sequencer latencies;</li>
    <li><a href="$imgsrv">imgsrv</a> getting images from the camera internal buffer (you have to acquire them there first)</li>
    <li><a href="var/klog.txt">var/klog.txt</a> - read kernel messages (you first need to telnet to the camera and run "printk_mod &amp;" and enable some of the DEBUG bits)</li>
   </ul>
   <h4>Make sure to run "printk_mod &amp;" before enabling debug bits - some are from interrupt service routine, and default printk() output to console can really mess up things.</h4>
   <p>Here are some of the current options</p>
   <ul>
    <li><b>cmd=</b> - execute one of the following commands:
       <ul>
         <li><a href="?cmd=init">init</a> - initialize camera, detect sensor, put some gamma-table, start compressor. After that you may acquire image with
             <a href="$imgsrv/img">$imgsrv/img</a></li>
         <li><a href="?cmd=constants">constants</a> - list all Elphel-defined constants available in PHP scripts </li>
         <li><a href="?cmd=gamma">gamma</a> - change gamma table (no gamma settings yet)</li>
         <li><a href="?cmd=0">0</a> - show current frame (sensor frame - it is incrementead even if compressor is off), frame8 - hardware modulo 8 sequencer counter</li>
         <li><a href="?cmd=jpegheader">jpegheader</a> - show current frame JPEG header</li>
      </ul>
      <p> There are some other options left out in the code, but they are not needed anymore - some others may be added
    </li>
    <li><b>a=A&amp;d=D&amp;=N[&amp;frame=F[&amp;flags=G]]</b> - Set parameter <b>A</b> with data<b>D</b> (hex data may be used).
               Address can also use defined constant names (w/o "ELPHEL_" prefix). <b>F</b> - absolute frame number (most likely will fail) or one of
               <b>this</b>, <b>next</b>, <b>next2</b>, <b>next3</b>, <b>next4</b>, <b>next5</b>, <b>next6</b> (next4 is usually safe to use). <G> - flags/modifiers - numeric value or "s" - static/not frame-related parameter or "n" - new parameter (force to be treated as new even if the value is the same as was before) </li>
    <li><b>na=A&amp;d=D&amp;=N[&amp;frame=F[&amp;test=1]]</b> - shortcut for the previous command with the flag "n" (force new).      test=1 - show a series of images around test command, i.e. <a href="?na=WOI_WIDTH&d=0xa20&frame=next3&test=1">na=WOI_WIDTH&amp;d=0xa20&amp;frame=next3&amp;test=1</a> would run compressor, skip 2 frames, change WOI_WIDTH 3 frames in the future, wait 4 frames and stop compressor. Then show last acquired images in a table.</li>
    <li><b>sa=A&amp;d=D&amp;=N</b> -  shortcut with the flag "s" (static), i.e. <a href="?sa=DEBUG&d=0">sa=DEBUG&amp;d=0</a> - turn debug logging off<br/></li>
    <li><b>frame=F</b> - Print raw frame parameters data for frame <b>F</b>. Valid values 0..7 and -1 (that will show function triggers instead of the parameter values). Frame 0 also hold "static" parameters - when written with "s" flags they only go there other parameters propagate to the future frames during frame interrupts.</li>
    <li><b>"gamma_structure</b> - show structure of the gamma tables cache. Gamma cache consists of 255 pages and index one, based on linked lists. It is designed to minimize floating point calcualions (made outside of the kernel) and facilitate scaling (integer) needed fro white balancing. So the "prototype" (non-scaled curves are cached in the top list with connected lists of scaled derivatives. For each table there are several sub-tables that can be requested (and cached after that) - reverse table (for histogram processing) and FPGA table (formatted to be written to the FPGA). Each gamma "type" is marked with a 16-bit hash, by default calculated as gamma value (in percents, 8 bits) and black level shift (fat zero), but it is possible to load arbitrary table and mark it with unique 16-bit hash16. When the curve is loaded it can be send to any of the 4 color channels with arbitrary 16-bit scale (0x400 is 1.0) by writing to one of GTAB_R, GTAB_G, GTAB_GB or GTAB_B registers (16 higher bits - hash16, lower 16 - scale, it is possible to change any set of bytes of the 32-bit word adding byte masks to the flags     ELPHEL_CONST_FRAMEPAIR_BYTE0, ELPHEL_CONST_FRAMEPAIR_BYTE1, ELPHEL_CONST_FRAMEPAIR_BYTE2, ELPHEL_CONST_FRAMEPAIR_BYTE3, ELPHEL_CONST_FRAMEPAIR_WORD0,FRAMEPAIR_WORD1.</li>
    <li><b>"gamma=G</b> - show raw data of the gamma page <b>G</b> (page 0 is a root)</li>
  </ul>
USAGE;
	exit ( 0 );
}
	// $_SERVER["SERVER_ADDR"] . ":8081
$sensor_port = 0; // / TODO: NC393 - add sensor port control, initially will use $sensor_port=0 for all php functions that require it
$sensor_subchn = 0; // / TODO: NC393 - add sensor port control, initially will use $sensor_port=0 for all php functions that require it
$address = "";
$data = "";
$frame = "";
$gamma_page = "";
$flags = 0;
$elp_const = get_defined_constants ( true );
$elp_const = $elp_const ["elphel"];
$test = 0;
$hist_needed = 0;
$port_mask = 0;
$SENSOR_PORTS = 4;
$framepars_paths =   array ("/dev/frameparsall0",
							"/dev/frameparsall1",
							"/dev/frameparsall2",
							"/dev/frameparsall3");
// Next is just to turn on/off compressor interrupts (new in NC393)
//circbuf0
$circbuf_paths =   array ("/dev/circbuf0",
						  "/dev/circbuf1",
						  "/dev/circbuf2",
						  "/dev/circbuf3");


if (array_key_exists ( 'sensor_port', $_GET )) {
	$sensor_port = (myval($_GET ['sensor_port']));
}
if (array_key_exists ( 'sensor_subchn', $_GET )) {
	$sensor_port = (myval($_GET ['sensor_subchn']));
}
if ($sensor_port >= 0){
	$sensor_port &= $SENSOR_PORTS-1;
	$port_mask = 1 << $sensor_port;
} else {
	$port_mask = (-$sensor_port);
	if ($port_mask >= (1 << $SENSOR_PORTS)) $port_mask = (1 << $SENSOR_PORTS) -1;
	$sensor_port = 0;
	for ($i = 0; $i< $SENSOR_PORTS; $i++) if ($port_mask & (1<<i)){
		$sensor_port = $i;
		break;
	}
}


$framepars_path = $framepars_paths[$sensor_port]; 
$circbuf_path =   $circbuf_paths[$sensor_port]; 
foreach ( $_GET as $key => $value ) {
	switch ($key) {
		case "profile" :
			$num_entries = myval ( $value );
			// echo "<pre>";
			$prof_template = array (
					"PROFILE00" => 0,
					"PROFILE01" => 0,
					"PROFILE02" => 0,
					"PROFILE03" => 0,
					"PROFILE04" => 0,
					"PROFILE05" => 0,
					"PROFILE06" => 0,
					"PROFILE07" => 0,
					"PROFILE08" => 0,
					"PROFILE09" => 0,
					"PROFILE10" => 0,
					"PROFILE11" => 0,
					"PROFILE12" => 0,
					"PROFILE13" => 0,
					"PROFILE14" => 0,
					"PROFILE15" => 0 
			);
			$now = elphel_get_frame ($GLOBALS['sensor_port']) - 2; // / data is available 2 frames behind
			$time_start = elphel_get_fpga_time ();
			$prof_raw = array ();
			for($i = $now - $num_entries - 1; $i <= $now; $i ++) {
				$prof_raw [$i] = elphel_get_P_arr ($GLOBALS['sensor_port'], $prof_template, $i );
			}
			$time_end = elphel_get_fpga_time ();
			$prof = array ();
			for($i = $now - $num_entries; $i <= $now; $i ++) {
				$prof [$i] = array (
						"dt0" => ($prof_raw [$i] ["PROFILE00"] - $prof_raw [$i - 1] ["PROFILE00"]) * 1000000 + ($prof_raw [$i] ["PROFILE01"] - $prof_raw [$i - 1] ["PROFILE01"]),
						"dt1" => ($prof_raw [$i] ["PROFILE02"] - $prof_raw [$i] ["PROFILE00"]) * 1000000 + ($prof_raw [$i] ["PROFILE03"] - $prof_raw [$i] ["PROFILE01"]),
						"dt2" => ($prof_raw [$i] ["PROFILE04"] - $prof_raw [$i] ["PROFILE00"]) * 1000000 + ($prof_raw [$i] ["PROFILE05"] - $prof_raw [$i] ["PROFILE01"]),
						"dt3" => ($prof_raw [$i] ["PROFILE06"] - $prof_raw [$i] ["PROFILE00"]) * 1000000 + ($prof_raw [$i] ["PROFILE07"] - $prof_raw [$i] ["PROFILE01"]),
						"dt4" => ($prof_raw [$i] ["PROFILE08"] - $prof_raw [$i] ["PROFILE00"]) * 1000000 + ($prof_raw [$i] ["PROFILE09"] - $prof_raw [$i] ["PROFILE01"]),
						"dt5" => ($prof_raw [$i] ["PROFILE10"] - $prof_raw [$i] ["PROFILE00"]) * 1000000 + ($prof_raw [$i] ["PROFILE11"] - $prof_raw [$i] ["PROFILE01"]),
						"dt6" => ($prof_raw [$i] ["PROFILE12"] - $prof_raw [$i] ["PROFILE00"]) * 1000000 + ($prof_raw [$i] ["PROFILE13"] - $prof_raw [$i] ["PROFILE01"]),
						"dt7" => ($prof_raw [$i] ["PROFILE14"] - $prof_raw [$i] ["PROFILE00"]) * 1000000 + ($prof_raw [$i] ["PROFILE15"] - $prof_raw [$i] ["PROFILE01"]) 
				);
				foreach ( $prof [$i] as $key => $value )
					if ($prof [$i] [$key] < 0)
						$prof [$i] [$key] = "";
			}
			
			echo <<<CAPTION
<p>reading profile time start=$time_start </p>
<p>reading profile time end=$time_end </p>
<ol>Profiling interrupt/tasklet execution time in microseconds, starting from the start of the frame
  <li>after updating frame pointers, Exif, parameters structures (IRQ service)</li>
  <li>start of the tasklet</li>
  <li>after Y histogram (G1) load from the FPGA (if enabled)</li>
  <li>after processing parameters (actions triggered by the parameter changes), </li>
  <li>after C histograms (R,G2,B) load from the FPGA (if enabled)</li>
  <li>When parameters are started to be written by appliaction(s) - overwritten if several calls take place during the same frame</li>
  <li>When parameters are finished to be written by appliaction(s) (may be  overwritten)</li>
</oul>
<br/><br/>
CAPTION;
			printf ( "<table border='1'><tr><td>Frame</td><td>(hex)</td><td>Period</td><td>1</td><td>2</td><td>3</td><td>4</td><td>5</td><td>6</td><td>7</td></tr>\n" );
			for($i = $now - $num_entries; $i <= $now; $i ++) {
				printf ( "<tr style='align:right'><td>%d</td><td>%08x</td><td>%d</td>", $i, $i, $prof [$i] ["dt0"] );
				for($j = 1; $j < 8; $j ++) {
					if ($prof [$i] ["dt" . $j])
						printf ( "<td>%d</td>", $prof [$i] ["dt" . $j] );
					else
						printf ( "<td>&nbsp;</td>" );
				}
				printf ( "</tr>\n" );
			}
			printf ( "</table>" );
			// echo "<pre>";print_r($prof_raw);echo"</pre>\n";
			
			exit ( 0 );
		case "histogram_direct" :
		case "histogram_reverse" :
		case "gamma_direct" :
		case "gamma_reverse" :
			$xml = new SimpleXMLElement ( "<?xml version='1.0'?><framepars/>" );
			$value = floatval ( $value );
			for ($sensor_port = 0; $sensor_port < $GLOBALS['SENSOR_PORTS'];$sensor_port++) if ($GLOBALS['port_mask'] & (1 << $sensor_port))  {
					switch ($key) {
						case "histogram_direct" :
							$xml->addChild ( 'histogram_direct_r'.strval($sensor_port), elphel_histogram ( $sensor_port, $GLOBALS ['$sensor_subchn'], 0, $value ) );
							$xml->addChild ( 'histogram_direct_g'.strval($sensor_port), elphel_histogram ( $sensor_port, $GLOBALS ['$sensor_subchn'], 1, $value ) );
							$xml->addChild ( 'histogram_direct_gb'.strval($sensor_port), elphel_histogram ( $sensor_port, $GLOBALS ['$sensor_subchn'], 2, $value ) );
							$xml->addChild ( 'histogram_direct_b'.strval($sensor_port), elphel_histogram ( $sensor_port, $GLOBALS ['$sensor_subchn'], 3, $value ) );
							break;
						case "histogram_reverse" :
							$xml->addChild ( 'histogram_reverse_r'.strval($sensor_port), elphel_reverse_histogram ( $sensor_port, $GLOBALS ['$sensor_subchn'], 0, $value ) );
							$xml->addChild ( 'histogram_reverse_g'.strval($sensor_port), elphel_reverse_histogram ( $sensor_port, $GLOBALS ['$sensor_subchn'], 1, $value ) );
							$xml->addChild ( 'histogram_reverse_gb'.strval($sensor_port), elphel_reverse_histogram ( $sensor_port, $GLOBALS ['$sensor_subchn'], 2, $value ) );
							$xml->addChild ( 'histogram_reverse_b'.strval($sensor_port), elphel_reverse_histogram ( $sensor_port, $GLOBALS ['$sensor_subchn'], 3, $value ) );
							break;
						case "gamma_direct" :
							$xml->addChild ( 'gamma_direct_r'.strval($sensor_port), elphel_gamma ( $sensor_port, $GLOBALS ['$sensor_subchn'], 0, $value ) );
							$xml->addChild ( 'gamma_direct_g'.strval($sensor_port), elphel_gamma ( $sensor_port, $GLOBALS ['$sensor_subchn'], 1, $value ) );
							$xml->addChild ( 'gamma_direct_gb'.strval($sensor_port), elphel_gamma ( $sensor_port, $GLOBALS ['$sensor_subchn'], 2, $value ) );
							$xml->addChild ( 'gamma_direct_b'.strval($sensor_port), elphel_gamma ( $sensor_port, $GLOBALS ['$sensor_subchn'], 3, $value ) );
							break;
						case "gamma_reverse" :
							$xml->addChild ( 'gamma_reverse_r'.strval($sensor_port), elphel_reverse_gamma ( $sensor_port, $GLOBALS ['$sensor_subchn'], 0, $value ) );
							$xml->addChild ( 'gamma_reverse_g'.strval($sensor_port), elphel_reverse_gamma ( $sensor_port, $GLOBALS ['$sensor_subchn'], 1, $value ) );
							$xml->addChild ( 'gamma_reverse_gb'.strval($sensor_port), elphel_reverse_gamma ( $sensor_port, $GLOBALS ['$sensor_subchn'], 2, $value ) );
							$xml->addChild ( 'gamma_reverse_b'.strval($sensor_port), elphel_reverse_gamma ( $sensor_port, $GLOBALS ['$sensor_subchn'], 3, $value ) );
							break;
					}
			}
			$rslt = $xml->asXML ();
			header ( "Content-Type: text/xml" );
			header ( "Content-Length: " . strlen ( $rslt ) . "\n" );
			header ( "Pragma: no-cache\n" );
			printf ( $rslt );
			exit ( 0 );
		case "cmd" :
		case "seek" :
		case "fseek" :
		case "lseek":
			$xml = new SimpleXMLElement ( "<?xml version='1.0'?><framepars/>" );
			if ($value == "gamma57") {
				$gamma = 57;
				$black = 10;
				$scale_r = ( int ) (1.0 * 1024);
				$scale_g = ( int ) (1.0 * 1024);
				$scale_b = ( int ) (1.0 * 1024);
				$scale_gb = ( int ) (1.0 * 1024);
				elphel_gamma_add ( 0.01 * $gamma, $black ); // does not depend on $GLOBALS['sensor_port']
				// define P_GTAB_R 138 // combines (P_PIXEL_LOW<<24) | (P_GAMMA <<16) and 16-bit (6.10) scale for gamma tables, individually for each color.
				// 16Msbs are also "hash16" and do not need to be black level/gamma, just uniquely identify the table for applications
				$gamma_pars = array (
						"GTAB_R" => ($black << 24) | ($gamma << 16) | ($scale_r & 0xffff),
						"GTAB_G" => ($black << 24) | ($gamma << 16) | ($scale_g & 0xffff),
						"GTAB_B" => ($black << 24) | ($gamma << 16) | ($scale_b & 0xffff),
						"GTAB_GB" => ($black << 24) | ($gamma << 16) | ($scale_gb & 0xffff)
				);
				for($sensor_port = 0; $sensor_port < $GLOBALS ['SENSOR_PORTS']; $sensor_port ++)
					if ($GLOBALS ['port_mask'] & (1 << $sensor_port)) {
						$frame = elphel_get_frame ( $sensor_port ); // 0
						elphel_set_P_arr ( $sensor_port, $gamma_pars, $frame + 3, 0 );
					}
			} else if ($value == "gamma") {
				$gamma = 60;
				$black = 10;
//				$scale_r = ( int ) (1.0 * 1024);
//				$scale_g = ( int ) (0.9 * 1024);
//				$scale_b = ( int ) (1.1 * 1024);
//				$scale_gb = ( int ) (0.9 * 1024);
				$scale_r = ( int ) (1.0 * 1024);
				$scale_g = ( int ) (1.0 * 1024);
				$scale_b = ( int ) (1.0 * 1024);
				$scale_gb = ( int ) (1.0 * 1024);
				elphel_gamma_add ( 0.01 * $gamma, $black );
				// define P_GTAB_R 138 // combines (P_PIXEL_LOW<<24) | (P_GAMMA <<16) and 16-bit (6.10) scale for gamma tables, individually for each color.
				// 16Msbs are also "hash16" and do not need to be black level/gamma, just uniquely identify the table for applications
				$gamma_pars = array (
						"GTAB_R" => ($black << 24) | ($gamma << 16) | ($scale_r & 0xffff),
						"GTAB_G" => ($black << 24) | ($gamma << 16) | ($scale_g & 0xffff),
						"GTAB_B" => ($black << 24) | ($gamma << 16) | ($scale_b & 0xffff),
						"GTAB_GB" => ($black << 24) | ($gamma << 16) | ($scale_gb & 0xffff)
				);
				for($sensor_port = 0; $sensor_port < $GLOBALS ['SENSOR_PORTS']; $sensor_port ++)
					if ($GLOBALS ['port_mask'] & (1 << $sensor_port)) {
						$frame = elphel_get_frame ( $sensor_port ); // 0
						elphel_set_P_arr ( $sensor_port, $gamma_pars, $frame + 3, 0 );
					}
			} else if ($value == "linear") {
				$gamma = 100;
				$black = 0;
				$scale_r = ( int ) (1.0 * 1024);
				$scale_g = ( int ) (1.0 * 1024);
				$scale_b = ( int ) (1.0 * 1024);
				$scale_gb = ( int ) (1.0 * 1024);
				elphel_gamma_add ( 0.01 * $gamma, $black ); // does not depend on $GLOBALS['sensor_port']
				                                            // define P_GTAB_R 138 // combines (P_PIXEL_LOW<<24) | (P_GAMMA <<16) and 16-bit (6.10) scale for gamma tables, individually for each color.
				                                            // 16Msbs are also "hash16" and do not need to be black level/gamma, just uniquely identify the table for applications
				$gamma_pars = array (
						"GTAB_R" => ($black << 24) | ($gamma << 16) | ($scale_r & 0xffff),
						"GTAB_G" => ($black << 24) | ($gamma << 16) | ($scale_g & 0xffff),
						"GTAB_B" => ($black << 24) | ($gamma << 16) | ($scale_b & 0xffff),
						"GTAB_GB" => ($black << 24) | ($gamma << 16) | ($scale_gb & 0xffff) 
				);
				for($sensor_port = 0; $sensor_port < $GLOBALS ['SENSOR_PORTS']; $sensor_port ++)
					if ($GLOBALS ['port_mask'] & (1 << $sensor_port)) {
						$frame = elphel_get_frame ( $sensor_port ); // 0
						elphel_set_P_arr ( $sensor_port, $gamma_pars, $frame + 3, 0 );
					}
			} else if ($value == "constants") {
				echo "<pre>\n";
				print_r ( $elp_const );
				echo "</pre>\n";
				exit ( 0 );
			} else
				for($sensor_port = 0; $sensor_port < $GLOBALS ['SENSOR_PORTS']; $sensor_port ++)
					if ($GLOBALS ['port_mask'] & (1 << $sensor_port)) {
						if (! $value) {
							$xml->addChild ( 'frame' . strval ( $sensor_port ), elphel_get_frame ( $sensor_port ) );
							$xml->addChild ( 'compressed' . strval ( $sensor_port ), elphel_get_compressed_frame ( $sensor_port ) );
						} else if ($value == "time") {
							$xml->addChild ( 'fpga_time' . strval ( $sensor_port ), elphel_get_fpga_time ( $sensor_port ) );
						} else if ($value == "irqoff") {
							$framepars_file = fopen ( $GLOBALS ['framepars_paths'] [$sensor_port], "w+" );
							$xml->addChild ( 'irqoff_result' . strval ( $sensor_port ), fseek ( $framepars_file, ELPHEL_LSEEK_INTERRUPT_OFF, SEEK_END ) ); // #define LSEEK_INTERRUPT_OFF 0x23 /// disable camera interrupts
							fclose ( $framepars_file );
						} else if ($value == "irqon") {
							$framepars_file = fopen ( $GLOBALS ['framepars_paths'] [$sensor_port], "w+" );
							$xml->addChild ( 'irqon_result' . strval ( $sensor_port ), fseek ( $framepars_file, ELPHEL_LSEEK_INTERRUPT_ON, SEEK_END ) ); // #define LSEEK_INTERRUPT_ON 0x24 /// enable camera interrupts
							fclose ( $framepars_file );
						} else if ($value == "compressor_irqoff") {
							$framepars_file = fopen ( $GLOBALS ['circbuf_paths'] [$sensor_port], "w+" );
							$xml->addChild ( 'compressor_irqoff_result' . strval ( $sensor_port ), fseek ( $framepars_file, ELPHEL_LSEEK_INTERRUPT_OFF, SEEK_END ) ); // #define LSEEK_INTERRUPT_OFF 0x23 /// disable camera interrupts
							fclose ( $framepars_file );
						} else if ($value == "compressor_irqon") {
							$framepars_file = fopen ( $GLOBALS ['circbuf_paths'] [$sensor_port], "w+" );
							$xml->addChild ( 'compressor_irqon_result', fseek ( $framepars_file, ELPHEL_LSEEK_INTERRUPT_ON, SEEK_END ) ); // #define LSEEK_INTERRUPT_ON 0x24 /// enable camera interrupts
							fclose ( $framepars_file );
						} else if ($value == "min_init") {
							$framepars_file = fopen ( $GLOBALS ['framepars_paths'] [$sensor_port], "w+"); //r" );
							$xml->addChild ( 'frame_before' . strval ( $sensor_port ),  elphel_get_frame($sensor_port));
							$xml->addChild ('DEBUG_01_'. strval ( $sensor_port ), $GLOBALS ['framepars_paths'] [$sensor_port]);
							$xml->addChild ( 'LSEEK_FRAMEPARS_INIT' . strval ( $sensor_port ), fseek ( $framepars_file, ELPHEL_LSEEK_FRAMEPARS_INIT, SEEK_END ) );
							elphel_set_P_value ( $sensor_port, ELPHEL_MULTI_CFG, 1); // cy22393 does not work on 10359. Not enough 3.3V?
							$xml->addChild ( 'elphel_set_P_value' . strval ( $sensor_port ), elphel_set_P_value ( $sensor_port, ELPHEL_SENSOR, 0x00, 0, ELPHEL_CONST_FRAMEPAIR_FORCE_NEWPROC ) ); // / will start detection
// Copied from "init" 
							$frame = elphel_get_frame($sensor_port);
							$xml->addChild ( 'frame' . strval ( $sensor_port ),  $frame);
							elphel_set_P_value ( $sensor_port, ELPHEL_MAXAHEAD, 2, 0, 8 ); // / When servicing interrupts, try programming up to 2 frames ahead of due time)
// 2016/09/09: Seems that with defualt 63, even on a single-channel autoexposure+ moving WOI breaks acquisition)
// With increased delay - seems OK
// Resizing - still breaks, probably for different reason
							elphel_set_P_value ( $sensor_port, ELPHEL_MEMSENSOR_DLY, 1024, $frame + 2, ELPHEL_CONST_FRAMEPAIR_FORCE_NEWPROC );
								
							elphel_set_P_value ( $sensor_port, ELPHEL_FPGA_XTRA, 1000, $frame + 3, ELPHEL_CONST_FRAMEPAIR_FORCE_NEWPROC ); // /compressor needs extra 1000 cycles to compress a frame (estimate)
//							elphel_set_P_value ( $sensor_port, ELPHEL_EXTERN_TIMESTAMP, 1, $frame + 3, ELPHEL_CONST_FRAMEPAIR_FORCE_NEWPROC ); // / only used with async trigger
							                                                                                                                   
							// / good (latency should be changed, but for now that will make a correct initialization - maybe obsolete)
							// SESNOR_PHASE for parallel sensors means quadrants (bits 1:0 - data, 3:2 - hact, 5:4 vact)
							elphel_set_P_value ( $sensor_port, ELPHEL_SENSOR_PHASE, 3, $frame + 3, ELPHEL_CONST_FRAMEPAIR_FORCE_NEWPROC );
								
							elphel_set_P_value ( $sensor_port, ELPHEL_BITS, 8, $frame + 3, ELPHEL_CONST_FRAMEPAIR_FORCE_NEWPROC );
							elphel_set_P_value ( $sensor_port, ELPHEL_QUALITY, 80, $frame + 4, ELPHEL_CONST_FRAMEPAIR_FORCE_NEWPROC );
							elphel_set_P_value ( $sensor_port, ELPHEL_COLOR, ELPHEL_CONST_COLORMODE_COLOR, $frame + 4, ELPHEL_CONST_FRAMEPAIR_FORCE_NEWPROC );
							elphel_set_P_value ( $sensor_port, ELPHEL_COLOR_SATURATION_BLUE, 200, $frame + 4, ELPHEL_CONST_FRAMEPAIR_FORCE_NEWPROC );
							elphel_set_P_value ( $sensor_port, ELPHEL_COLOR_SATURATION_RED, 200, $frame + 4, ELPHEL_CONST_FRAMEPAIR_FORCE_NEWPROC );
							elphel_set_P_value ( $sensor_port, ELPHEL_BAYER, 0, $frame + 4, ELPHEL_CONST_FRAMEPAIR_FORCE_NEWPROC );
							elphel_set_P_value ( $sensor_port, ELPHEL_GAING, 0x10000, $frame + 4, ELPHEL_CONST_FRAMEPAIR_FORCE_NEWPROC );
							elphel_set_P_value ( $sensor_port, ELPHEL_GAINGB, 0x10000, $frame + 4, ELPHEL_CONST_FRAMEPAIR_FORCE_NEWPROC );
							elphel_set_P_value ( $sensor_port, ELPHEL_GAINR, 0x10000, $frame + 4, ELPHEL_CONST_FRAMEPAIR_FORCE_NEWPROC );
							elphel_set_P_value ( $sensor_port, ELPHEL_GAINB, 0x10000, $frame + 4, ELPHEL_CONST_FRAMEPAIR_FORCE_NEWPROC );
							elphel_set_P_value ( $sensor_port, ELPHEL_VIGNET_C, 0x8000, $frame + 4, ELPHEL_CONST_FRAMEPAIR_FORCE_NEWPROC );
							elphel_set_P_value ( $sensor_port, ELPHEL_VIGNET_SHL, 1, $frame + 4, ELPHEL_CONST_FRAMEPAIR_FORCE_NEWPROC );
							elphel_set_P_value ( $sensor_port, ELPHEL_SENSOR_RUN, 2, $frame + 4, ELPHEL_CONST_FRAMEPAIR_FORCE_NEWPROC );
//							elphel_set_P_value ( $sensor_port, ELPHEL_COMPRESSOR_RUN, 2, $frame + 4, ELPHEL_CONST_FRAMEPAIR_FORCE_NEWPROC ); // / run compressor
							
							elphel_set_P_value ( $sensor_port, ELPHEL_DCM_HOR, 1, $frame + 4, ELPHEL_CONST_FRAMEPAIR_FORCE_NEWPROC );
							elphel_set_P_value ( $sensor_port, ELPHEL_DCM_VERT,1, $frame + 4, ELPHEL_CONST_FRAMEPAIR_FORCE_NEWPROC );
							elphel_set_P_value ( $sensor_port, ELPHEL_BIN_HOR, 1, $frame + 4, ELPHEL_CONST_FRAMEPAIR_FORCE_NEWPROC );
							elphel_set_P_value ( $sensor_port, ELPHEL_BIN_VERT, 1, $frame + 4, ELPHEL_CONST_FRAMEPAIR_FORCE_NEWPROC );
							
//							elphel_set_P_value ( $sensor_port, ELPHEL_COMPRESSOR_RUN, 2, $frame + 5, ELPHEL_CONST_FRAMEPAIR_FORCE_NEWPROC ); // / run compressor
//							elphel_set_P_value ( $sensor_port, ELPHEL_COMPRESSOR_RUN, 2, $frame + 8, ELPHEL_CONST_FRAMEPAIR_FORCE_NEWPROC ); // / run compressor
							elphel_set_P_value ( $sensor_port, ELPHEL_COMPRESSOR_RUN, 2, $frame + 9, ELPHEL_CONST_FRAMEPAIR_FORCE_NEWPROC ); // / run compressor
//							elphel_set_P_value ( $sensor_port, ELPHEL_COMPRESSOR_RUN, 2, $frame + 13, ELPHEL_CONST_FRAMEPAIR_FORCE_NEWPROC ); // / run compressor
											
// Turn external trigger at 4fps
/*
							elphel_set_P_value ( $sensor_port, ELPHEL_TRIG_PERIOD, 25000000, $frame + 4, ELPHEL_CONST_FRAMEPAIR_FORCE_NEWPROC ); //0.25 s
							elphel_set_P_value ( $sensor_port, ELPHEL_TRIG_BITLENGTH,    31, $frame + 4, ELPHEL_CONST_FRAMEPAIR_FORCE_NEWPROC ); //0.32 usec
							elphel_set_P_value ( $sensor_port, ELPHEL_TRIG_DELAY,         0, $frame + 4, ELPHEL_CONST_FRAMEPAIR_FORCE_NEWPROC ); // no delay
							elphel_set_P_value ( $sensor_port, ELPHEL_EXTERN_TIMESTAMP,   1, $frame + 4, ELPHEL_CONST_FRAMEPAIR_FORCE_NEWPROC ); // use external timestamp when available
							elphel_set_P_value ( $sensor_port, ELPHEL_XMIT_TIMESTAMP,     1, $frame + 4, ELPHEL_CONST_FRAMEPAIR_FORCE_NEWPROC ); // transmit timestamps, not just pulse
							elphel_set_P_value ( $sensor_port, ELPHEL_TRIG_OUT,     0x65555, $frame + 4, ELPHEL_CONST_FRAMEPAIR_FORCE_NEWPROC ); // 0x56555 - ext connector, 0x65555  - internal connector 0x66555 - both, 0x55555 - none
							/// change to "internal" (0x8000) when wired
							elphel_set_P_value ( $sensor_port, ELPHEL_TRIG_CONDITION,     0, $frame + 4, ELPHEL_CONST_FRAMEPAIR_FORCE_NEWPROC ); // 0x0 - from FPGA, 0x80000 - ext, 0x8000 - int, 0x88000 - any, 0x95555 - add ext, 0x59999 - add int
//							elphel_set_P_value ( $sensor_port, ELPHEL_TRIG,             0x4, $frame + 6, ELPHEL_CONST_FRAMEPAIR_FORCE_NEWPROC ); // 0 - free running, 4 - extrnal ERS, 5 - external GRR
 */
							$xml->addChild ( 'frame_after' . strval ( $sensor_port ),  elphel_get_frame($sensor_port));
							
							fclose ( $framepars_file );
							$gamma = 57;
							$black = 10;
							$scale_r = ( int ) (1.0 * 1024);
							$scale_g = ( int ) (1.0 * 1024);
							$scale_b = ( int ) (1.0 * 1024);
							$scale_gb = ( int ) (1.0 * 1024);
							elphel_gamma_add ( 0.01 * $gamma, $black ); // does not depend on $GLOBALS['sensor_port']
							                                            // define P_GTAB_R 138 // combines (P_PIXEL_LOW<<24) | (P_GAMMA <<16) and 16-bit (6.10) scale for gamma tables, individually for each color.
							                                            // 16Msbs are also "hash16" and do not need to be black level/gamma, just uniqualy identify the table for applications
							$gamma_pars = array (
									"GTAB_R" => ($black << 24) | ($gamma << 16) | ($scale_r & 0xffff),
									"GTAB_G" => ($black << 24) | ($gamma << 16) | ($scale_g & 0xffff),
									"GTAB_B" => ($black << 24) | ($gamma << 16) | ($scale_b & 0xffff),
									"GTAB_GB" => ($black << 24) | ($gamma << 16) | ($scale_gb & 0xffff) 
							);
							$frame = elphel_get_frame ( $sensor_port ); // 0
							elphel_set_P_arr ( $sensor_port, $gamma_pars, $frame + 3, 0 );
							$xml->addChild ( 'frame_final' . strval ( $sensor_port ),  elphel_get_frame($sensor_port));
						
						} else if ($value == "init") {
							$framepars_file = fopen ( $GLOBALS ['framepars_paths'] [$sensor_port], "w+"); //r" );
							// NC393 - added IRQs ON 
//							$xml->addChild ( 'irqon_result' . strval ( $sensor_port ), fseek ( $framepars_file, ELPHEL_LSEEK_INTERRUPT_ON, SEEK_END ) ); // #define LSEEK_INTERRUPT_ON 0x24 /// enable camera interrupts
//							$circbuf_file = fopen ( $GLOBALS ['circbuf_paths'] [$sensor_port], "w+" );
//							$xml->addChild ( 'compressor_irqon_result', fseek ( $circbuf_file, ELPHEL_LSEEK_INTERRUPT_ON, SEEK_END ) ); // #define LSEEK_INTERRUPT_ON 0x24 /// enable camera interrupts
//							fclose ( $circbuf_file );
// Init turn on cmdseq interrupts, but not compressor ones							
							$xml->addChild ( 'frame_before' . strval ( $sensor_port ),  elphel_get_frame($sensor_port));
							$xml->addChild ('DEBUG_01_'. strval ( $sensor_port ), $GLOBALS ['framepars_paths'] [$sensor_port]);
							$xml->addChild ( 'LSEEK_FRAMEPARS_INIT' . strval ( $sensor_port ), fseek ( $framepars_file, ELPHEL_LSEEK_FRAMEPARS_INIT, SEEK_END ) );
							$xml->addChild ( 'elphel_set_P_value' . strval ( $sensor_port ), elphel_set_P_value ( $sensor_port, ELPHEL_SENSOR, 0x00, 0, ELPHEL_CONST_FRAMEPAIR_FORCE_NEWPROC ) ); // / will start detection
							$frame = elphel_get_frame($sensor_port);
							$xml->addChild ( 'frame' . strval ( $sensor_port ),  $frame);
							elphel_set_P_value ( $sensor_port, ELPHEL_MAXAHEAD, 2, 0, 8 ); // / When servicing interrupts, try programming up to 2 frames ahead of due time)
// 2016/09/09: Seems that with defualt 63, even on a single-channel autoexposure+ moving WOI breaks acquisition)
// With increased delay - seems OK
// Resizing - still breaks, probably for different reason
							elphel_set_P_value ( $sensor_port, ELPHEL_MEMSENSOR_DLY, 1024, $frame + 2, ELPHEL_CONST_FRAMEPAIR_FORCE_NEWPROC );
								
							elphel_set_P_value ( $sensor_port, ELPHEL_FPGA_XTRA, 1000, $frame + 3, ELPHEL_CONST_FRAMEPAIR_FORCE_NEWPROC ); // /compressor needs extra 1000 cycles to compress a frame (estimate)
//							elphel_set_P_value ( $sensor_port, ELPHEL_EXTERN_TIMESTAMP, 1, $frame + 3, ELPHEL_CONST_FRAMEPAIR_FORCE_NEWPROC ); // / only used with async trigger
							                                                                                                                   
							// / good (latency should be changed, but for now that will make a correct initialization - maybe obsolete)
							
							elphel_set_P_value ( $sensor_port, ELPHEL_BITS, 8, $frame + 3, ELPHEL_CONST_FRAMEPAIR_FORCE_NEWPROC );
							elphel_set_P_value ( $sensor_port, ELPHEL_QUALITY, 80, $frame + 4, ELPHEL_CONST_FRAMEPAIR_FORCE_NEWPROC );
							elphel_set_P_value ( $sensor_port, ELPHEL_COLOR, ELPHEL_CONST_COLORMODE_COLOR, $frame + 4, ELPHEL_CONST_FRAMEPAIR_FORCE_NEWPROC );
							elphel_set_P_value ( $sensor_port, ELPHEL_COLOR_SATURATION_BLUE, 200, $frame + 4, ELPHEL_CONST_FRAMEPAIR_FORCE_NEWPROC );
							elphel_set_P_value ( $sensor_port, ELPHEL_COLOR_SATURATION_RED, 200, $frame + 4, ELPHEL_CONST_FRAMEPAIR_FORCE_NEWPROC );
							elphel_set_P_value ( $sensor_port, ELPHEL_BAYER, 0, $frame + 4, ELPHEL_CONST_FRAMEPAIR_FORCE_NEWPROC );
							elphel_set_P_value ( $sensor_port, ELPHEL_GAING, 0x10000, $frame + 4, ELPHEL_CONST_FRAMEPAIR_FORCE_NEWPROC );
							elphel_set_P_value ( $sensor_port, ELPHEL_GAINGB, 0x10000, $frame + 4, ELPHEL_CONST_FRAMEPAIR_FORCE_NEWPROC );
							elphel_set_P_value ( $sensor_port, ELPHEL_GAINR, 0x10000, $frame + 4, ELPHEL_CONST_FRAMEPAIR_FORCE_NEWPROC );
							elphel_set_P_value ( $sensor_port, ELPHEL_GAINB, 0x10000, $frame + 4, ELPHEL_CONST_FRAMEPAIR_FORCE_NEWPROC );
							elphel_set_P_value ( $sensor_port, ELPHEL_VIGNET_C, 0x8000, $frame + 4, ELPHEL_CONST_FRAMEPAIR_FORCE_NEWPROC );
							elphel_set_P_value ( $sensor_port, ELPHEL_VIGNET_SHL, 1, $frame + 4, ELPHEL_CONST_FRAMEPAIR_FORCE_NEWPROC );
							elphel_set_P_value ( $sensor_port, ELPHEL_SENSOR_RUN, 2, $frame + 4, ELPHEL_CONST_FRAMEPAIR_FORCE_NEWPROC );
							elphel_set_P_value ( $sensor_port, ELPHEL_COMPRESSOR_RUN, 2, $frame + 4, ELPHEL_CONST_FRAMEPAIR_FORCE_NEWPROC ); // / run compressor
							$xml->addChild ( 'frame_after' . strval ( $sensor_port ),  elphel_get_frame($sensor_port));
							fclose ( $framepars_file );
							$gamma = 57;
							$black = 10;
							$scale_r = ( int ) (1.0 * 1024);
							$scale_g = ( int ) (1.0 * 1024);
							$scale_b = ( int ) (1.0 * 1024);
							$scale_gb = ( int ) (1.0 * 1024);
							elphel_gamma_add ( 0.01 * $gamma, $black ); // does not depend on $GLOBALS['sensor_port']
							                                            // define P_GTAB_R 138 // combines (P_PIXEL_LOW<<24) | (P_GAMMA <<16) and 16-bit (6.10) scale for gamma tables, individually for each color.
							                                            // 16Msbs are also "hash16" and do not need to be black level/gamma, just uniqualy identify the table for applications
							$gamma_pars = array (
									"GTAB_R" => ($black << 24) | ($gamma << 16) | ($scale_r & 0xffff),
									"GTAB_G" => ($black << 24) | ($gamma << 16) | ($scale_g & 0xffff),
									"GTAB_B" => ($black << 24) | ($gamma << 16) | ($scale_b & 0xffff),
									"GTAB_GB" => ($black << 24) | ($gamma << 16) | ($scale_gb & 0xffff) 
							);
							$frame = elphel_get_frame ( $sensor_port ); // 0
							elphel_set_P_arr ( $sensor_port, $gamma_pars, $frame + 3, 0 );
							$xml->addChild ( 'frame_final' . strval ( $sensor_port ),  elphel_get_frame($sensor_port));

						} else if ($value == "initgamma") {
							$gammas_file = fopen ( "/dev/gamma_cache", "w+" );
							$xml->addChild ( 'LSEEK_GAMMA_INIT' . strval ( $sensor_port ), fseek ( $gammas_file, ELPHEL_LSEEK_GAMMA_INIT, SEEK_END ) );
							fclose ( $gammas_file );
						} else if ($value == "jpegheader") {
							$circbuf_file = fopen ( "/dev/circbuf", "w+" );
							fseek ( $circbuf_file, ELPHEL_LSEEK_CIRC_LAST, SEEK_END );
							$jpeg_start = ftell ( $circbuf_file );
							$xml->addChild ( 'circbuf_pointer' . strval ( $sensor_port ), sprintf ( "0x%x (0x%x)", $jpeg_start, $jpeg_start >> 2 ) );
							fclose ( $circbuf_file );
							$header_file = fopen ( "/dev/jpeghead", "w+" );
							// / Now select right frame (different frames may have different header sizes)
							fseek ( $header_file, $jpeg_start + 1, SEEK_END ); // / selects frame, creates header
							fseek ( $header_file, 0, SEEK_END ); // / positions to the end
							$header_size = ftell ( $header_file ); // /
							$xml->addChild ( 'header_size' . strval ( $sensor_port ), $header_size );
							fseek ( $header_file, 0, SEEK_SET ); // / positions to the beginning
							$header = fread ( $header_file, 8192 );
							$xml->addChild ( 'header_read_length' . strval ( $sensor_port ), strlen ( $header ) );
							fclose ( $header_file );
							$aheader = unpack ( 'C*', $header );
							for($i = 0; $i < count ( $aheader ); $i += 16) {
								$d = "";
								for($j = $i; ($j < $i + 16) && ($j < count ( $aheader )); $j ++)
									$d .= sprintf ( " %02x", $aheader [$j + 1] );
								$xml->addChild ( sprintf ( 'header%03x' . strval ( $sensor_port ), $i ), $d );
							}
						} else if ($value == "reset") {
							$framepars_file = fopen ( $GLOBALS ['framepars_paths'] [$sensor_port], "w+" );
							$xml->addChild ( 'LSEEK_DMA_STOP' . strval ( $sensor_port ), fseek ( $framepars_file, ELPHEL_LSEEK_DMA_STOP, SEEK_END ) );
							$xml->addChild ( 'LSEEK_DMA_INIT' . strval ( $sensor_port ), fseek ( $framepars_file, ELPHEL_LSEEK_DMA_INIT, SEEK_END ) );
							$xml->addChild ( 'LSEEK_COMPRESSOR_RESET' . strval ( $sensor_port ), fseek ( $framepars_file, ELPHEL_LSEEK_COMPRESSOR_RESET, SEEK_END ) );
							fclose ( $framepars_file );
						} else {
							$framepars_file = fopen ( $GLOBALS ['framepars_paths'] [$sensor_port], "w+" );
							$xml->addChild ( 'lseek_' . $value . "_" . strval ( $sensor_port ), fseek ( $framepars_file, $value, SEEK_END ) );
							fclose ( $framepars_file );
						}
					}
			$rslt = $xml->asXML ();
			header ( "Content-Type: text/xml" );
			header ( "Content-Length: " . strlen ( $rslt ) . "\n" );
			header ( "Pragma: no-cache\n" );
			printf ( $rslt );
			exit ( 0 );
		case "a" : // / Make it recognize P_* constants?
		case "adr" :
		case "na" : // / add flag "new"
		case "sa" : // / add flag "static"
			$address = myval ( $value );
			if (($address == 0) && (strlen ( $value ) > 3)) { // suspect constant
				$address = $elp_const ["ELPHEL_" . $value];
			}
			switch ($key) {
				case "na" : // / flags defined in upper half of 32-bit word and can be OR-ed with address. When provided as "flags=" 0x3 is the same as 0x30000 ( internally flags = (flags | (flags <<16)) & 0xffff0000)
					$address |= ELPHEL_CONST_FRAMEPAIR_FORCE_NEWPROC;
					;
					break;
				case "sa" :
					$address |= ELPHEL_CONST_FRAMEPAIR_FRAME_ZERO;
					break;
			}
			if (($address & 0xffff) == 0) { // / wrong address - probably mistyped constant name
				$xml = new SimpleXMLElement ( "<?xml version='1.0'?><framepars/>" );
				$xml->addChild ( 'ERROR', '"Wrong address==0, probably misspelled constant: \'' . $value . '\'"' );
				$rslt = $xml->asXML ();
				header ( "Content-Type: text/xml" );
				header ( "Content-Length: " . strlen ( $rslt ) . "\n" );
				header ( "Pragma: no-cache\n" );
				printf ( $rslt );
				exit ( 0 );
			}
			break;
		case "d" :
		case "data" :
			$data = myval ( $value );
			break;
		case "hist_raw" :
			$hist_needed ++;
		case "hist" :
		case "histogram" :
			$hist_needed ++; // / and fall to "frame"
		case "f" :
		case "frame" :
			$current_frame = elphel_get_frame ($GLOBALS['sensor_port']);
			switch ($value) {
				case "this" :
					$frame = $current_frame;
					break;
				case "next" :
					$frame = $current_frame + 1;
					break;
				case "next2" :
					$frame = $current_frame + 2;
					break;
				case "next3" :
					$frame = $current_frame + 3;
					break;
				case "next4" :
					$frame = $current_frame + 4;
					break;
				case "next5" :
					$frame = $current_frame + 5;
					break;
				case "next6" :
					$frame = $current_frame + 6;
					break;
					break;
				case "prev" :
					$frame = $current_frame - 1;
					break;
				case "prev2" :
					$frame = $current_frame - 2;
					break;
				case "prev3" :
					$frame = $current_frame - 3;
					break;
				case "prev4" :
					$frame = $current_frame - 4;
					break;
				case "prev5" :
					$frame = $current_frame - 5;
					break;
				case "prev6" :
					$frame = $current_frame - 6;
					break;
				default :
					$frame = myval ( $value );
			}
			break;
		case "flags" : // / simple flags - "z(ero)"/"(s(tatic), "n(new)",+ constants
		case "flag" :
			switch ($value) {
				case "z" :
				case "zero" :
				case "s" :
				case "static" :
					$flags = ELPHEL_CONST_FRAMEPAIR_FRAME_ZERO;
					break;
				case "n" :
				case "new" :
					$flags = ELPHEL_CONST_FRAMEPAIR_FORCE_NEWPROC;
					break;
				default :
					$flags = myval ( $value );
			}
			break;
		case "t" :
		case "test" :
			$test = myval ( $value );
			break;
		case "gamma" :
		case "gamma_page" :
			$gamma_page = myval ( $value );
			break;
		case "gamma_structure" :
			// / later add value - list of pages to process
			printGammaStructure ();
			/*
			 * echo "<pre>\n";
			 * var_dump(getGammaStructure());
			 * echo "</pre>\n";
			 */
			exit ( 0 );
	}
}
if ($address === "") {
	if ($frame === "") {
		if ($gamma_page === "") {
			echo <<<USAGE
   <p>read/fwrite frame parameters, execute commands</p>
   <ul>
    <li><b>framepars.php?cmd=<i>command number</i></b> -  execute <i>register_address</i></li>
    <li><b>framepars.php?a[dr]=<i>register_address</i>[&f[rame]=<i>frame_number</i>]</b> -  read frame parameter <i>register_address</i> from <i>frame_number</i></li>
    <li><b>framepars.php?a[dr]=<i>register_address</i>&d[ata]=<i>register_data</i>[&f[rame]=<i>frame_number</i>][&flag[s]=<i>flags</i>]</b> -  write frame parameter <i>register_address</i> with data <i>register_data</i> to frame <i>frame_number</i>, optionally use <i>flags</i></li>
  </ul>
USAGE;
			exit ( 0 );
		} else {
			// /read raw gamma page
			printRawGamma ( $gamma_page );
			exit ( 0 );
		}
	} else { // /$frame !== ""
		switch ($hist_needed) {
			case 2 :
				printRawHistogram ( 0xfff, $frame );
				exit ( 0 );
			case 1 :
				printHistogram ( $frame );
				exit ( 0 );
			default :
				// / read raw framepars
				printRawFrame ( $frame );
				exit ( 0 );
		}
	}
}
$xml = new SimpleXMLElement ( "<?xml version='1.0'?><framepars/>" );
$xml->addChild ( 'frame', $frame );
$xml->addChild ( 'hex_frame', sprintf ( "0x%x", $frame ) );
$xml->addChild ( 'address', $address );
$xml->addChild ( 'hex_address', sprintf ( "0x%x", $address ) );
if ($data === "") {
	if ($frame === '')
		$data = elphel_get_P_value ($GLOBALS['sensor_port'], $address );
	else
		$data = elphel_get_P_value ($GLOBALS['sensor_port'], $address, $frame );
	$xml->addChild ( 'read', $data );
	$xml->addChild ( 'hex_read', sprintf ( "0x%x", $data ) );
} else {
	if (($frame === '') && flags)
		$frame = 0;
	$xml->addChild ( 'frame_was', $frame );
	// /***************************************************************************
	if ($test > 0) {
		ob_flush ();
		flush ();
		
		elphel_set_P_value ($GLOBALS['sensor_port'], ELPHEL_COMPRESSOR_RUN | ELPHEL_CONST_FRAMEPAIR_FORCE_NEWPROC, ELPHEL_CONST_COMPRESSOR_RUN_CONT, $current_frame + 2 );
		$frame += 2;
		$current_frame += 2;
		elphel_skip_frames ($GLOBALS['sensor_port'], 2 );
	}
	if ($frame === '')
		elphel_set_P_value ($GLOBALS['sensor_port'], $address, $data );
	else
		elphel_set_P_value ($GLOBALS['sensor_port'], $address, $data, $frame, $flags );
	if ($test > 0) {
		printf ( "<p><a href=\"%s/prev/prev/prev/prev/prev/prev/prev/prev/meta/next/meta/next/meta/next/meta/next/meta/next/meta/next/meta/next/meta/next/meta\">meta data for the last 9 frames</a></p>\n", $imgsrv );
		ob_flush ();
		flush (); // /OK here
		$circbuf_file = fopen ( "/dev/circbuf", "w+" );
		$current_frame = $frame;
		printf ( "<p>frame=%d (0x%x), time=%d </p>\n", elphel_get_frame ($GLOBALS['sensor_port']), elphel_get_frame ($GLOBALS['sensor_port']), time () );
		ob_flush ();
		flush ();
		elphel_skip_frames ($GLOBALS['sensor_port'], 2 );
		printf ( "<p>target frame=%d (0x%x) frame=%d(0x%x), time=%d </p>\n", $frame, $frame, elphel_get_frame ($GLOBALS['sensor_port']), elphel_get_frame ($GLOBALS['sensor_port']), time () );
		ob_flush ();
		flush ();
		// printf ("<p>start_frame2=0x%x</p>\n",$current_frame);
		
		elphel_set_P_value ($GLOBALS['sensor_port'], ELPHEL_COMPRESSOR_RUN | ELPHEL_CONST_FRAMEPAIR_FORCE_NEWPROC, ELPHEL_CONST_COMPRESSOR_RUN_STOP, $current_frame + 4 );
		printf ( "<p>frame=%d (0x%x), time=%d </p>\n", elphel_get_frame ($GLOBALS['sensor_port']), elphel_get_frame ($GLOBALS['sensor_port']), time () );
		ob_flush ();
		flush ();
		$current_frame += 6; // / to be sure - less is enough?
		for($i = 0; $i < 6; $i ++) {
			elphel_skip_frames ($GLOBALS['sensor_port'], 1 );
			printf ( "<p>skipped 1 frame - frame=%d (0x%x), time=%d </p>\n", elphel_get_frame ($GLOBALS['sensor_port']), elphel_get_frame ($GLOBALS['sensor_port']), time () );
			ob_flush ();
			flush ();
		}
		// elphel_skip_frames($GLOBALS['sensor_port'],6);
		// printf ("<p>after skip 6 frames - frame=0x%x, time=%d </p>\n",elphel_get_frame($GLOBALS['sensor_port']),time()); ob_flush(); flush();
		fseek ( $circbuf_file, LSEEK_CIRC_TOWP, SEEK_END );
		// /stuck before here?
		printf ( "<p>frame=%d (0x%x) time=%d </p>\n", elphel_get_frame ($GLOBALS['sensor_port']), elphel_get_frame ($GLOBALS['sensor_port']), time () );
		ob_flush ();
		flush ();
		elphel_skip_frames ($GLOBALS['sensor_port'], 1 );
		fseek ( $circbuf_file, LSEEK_CIRC_TOWP, SEEK_END );
		printf ( "<p>frame=%d (0x%x) time=%d </p>\n", elphel_get_frame ($GLOBALS['sensor_port']), elphel_get_frame ($GLOBALS['sensor_port']), time () );
		ob_flush ();
		flush ();
		elphel_skip_frames ($GLOBALS['sensor_port'], 1 );
		fseek ( $circbuf_file, LSEEK_CIRC_TOWP, SEEK_END );
		printf ( "<p>frame=%d (0x%x) time=%d </p>\n", elphel_get_frame ($GLOBALS['sensor_port']), elphel_get_frame ($GLOBALS['sensor_port']), time () );
		ob_flush ();
		flush ();
		elphel_skip_frames ($GLOBALS['sensor_port'], 1 );
		fseek ( $circbuf_file, LSEEK_CIRC_TOWP, SEEK_END );
		printf ( "<p>frame=%d (0x%x) time=%d </p>\n", elphel_get_frame ($GLOBALS['sensor_port']), elphel_get_frame ($GLOBALS['sensor_port']), time () );
		ob_flush ();
		flush ();
		
		// / Strange - frame normal delay sometimes wrong images in the
		// printf ("<p>end_frame2=0x%x</p>\n",elphel_get_frame($GLOBALS['sensor_port']));
		fclose ( $circbuf_file );
		
		image_table8 ();
		exit ( 0 );
		$xml->addChild ( 'test', $test );
	}
	$xml->addChild ( 'frame', $frame );
	$xml->addChild ( 'written', $data );
	$xml->addChild ( 'hex_written', sprintf ( "0x%x", $data ) );
	$xml->addChild ( 'flags', sprintf ( "0x%x", $flags ) );
}
$rslt = $xml->asXML ();
header ( "Content-Type: text/xml" );
header ( "Content-Length: " . strlen ( $rslt ) . "\n" );
header ( "Pragma: no-cache\n" );
printf ( $rslt );
exit ( 0 );
function printGammaStructure() {
	$gammaStructure = getGammaStructure ();
	printf ( "<table \"border=1\">\n" );
	printf ( "<tr><td>oldest_non_scaled</td><td><b>%d</b></td></tr>\n" . "<tr><td>newest_non_scaled</td><td><b>%d</b></td></tr>\n" . "<tr><td>oldest_all</td><td><b>%d</b></td></tr>\n" . "<tr><td>newest_all</td><td><b>%d</b></td></tr>\n" . "<tr><td>non_scaled_length</td><td><b>%d</b></td></tr>\n" . "<tr><td>num_locked</td><td><b>%d</b></td></tr>\n" . "<tr><td>locked_col 0</td><td><b>%d</b></td>\n" . "<tr><td>locked_col 1</td><td><b>%d</b></td>\n" . "<tr><td>locked_col 2</td><td><b>%d</b></td>\n" . "<tr><td>locked_col 3</td><td><b>%d</b></td>\n" . "</table>\n", $gammaStructure ["oldest_non_scaled"], $gammaStructure ["newest_non_scaled"], $gammaStructure ["oldest_all"], $gammaStructure ["newest_all"], $gammaStructure ["non_scaled_length"], $gammaStructure ["num_locked"], $gammaStructure ["locked_col"] [0], $gammaStructure ["locked_col"] [1], $gammaStructure ["locked_col"] [2], $gammaStructure ["locked_col"] [3] );
	printf ( "<br/><br/>\n" );
	
	printf ( "<table \"border=1\">\n" );
	// printf("<tr><td>index </td>\n"); foreach ($gammaStructure["entries"] as $entry) printf ("<td><b>%d</b></td>",$entry["index"]);printf("</tr>\n");
	printf ( "<tr><td>index           </td>\n" );
	foreach ( $gammaStructure ["entries"] as $entry )
		printf ( "<td><a href='?gamma_page=%d'><b>%d</b></a></td>", $entry ["index"], $entry ["index"] );
	printf ( "</tr>\n" );
	
	printf ( "<tr><td>hash32           </td>\n" );
	foreach ( $gammaStructure ["entries"] as $entry )
		printf ( "<td><b>%08x</b></td>", $entry ["hash32"] );
	printf ( "</tr>\n" );
	printf ( "<tr><td>scale           </td>\n" );
	foreach ( $gammaStructure ["entries"] as $entry )
		printf ( "<td><b>%01.3f</b></td>", $entry ["scale"] );
	printf ( "</tr>\n" );
	printf ( "<tr><td>gamma           </td>\n" );
	foreach ( $gammaStructure ["entries"] as $entry )
		printf ( "<td><b>%01.3f</b></td>", $entry ["gamma"] );
	printf ( "</tr>\n" );
	printf ( "<tr><td>black           </td>\n" );
	foreach ( $gammaStructure ["entries"] as $entry )
		printf ( "<td><b>%d</b></td>", $entry ["black"] );
	printf ( "</tr>\n" );
	printf ( "<tr><td>valid           </td>\n" );
	foreach ( $gammaStructure ["entries"] as $entry )
		printf ( "<td><b>0x%x</b></td>", $entry ["valid"] );
	printf ( "</tr>\n" );
	printf ( "<tr><td>locked          </td>\n" );
	foreach ( $gammaStructure ["entries"] as $entry )
		printf ( "<td><b>0x%8x</b></td>", $entry ["locked"] );
	printf ( "</tr>\n" );
	printf ( "<tr><td>this_non_scaled </td>\n" );
	foreach ( $gammaStructure ["entries"] as $entry )
		printf ( "<td><b>%d</b></td>", $entry ["this_non_scaled"] );
	printf ( "</tr>\n" );
	printf ( "<tr><td>newer_non_scaled</td>\n" );
	foreach ( $gammaStructure ["entries"] as $entry )
		printf ( "<td><b>%d</b></td>", $entry ["newer_non_scaled"] );
	printf ( "</tr>\n" );
	printf ( "<tr><td>older_non_scaled</td>\n" );
	foreach ( $gammaStructure ["entries"] as $entry )
		printf ( "<td><b>%d</b></td>", $entry ["older_non_scaled"] );
	printf ( "</tr>\n" );
	printf ( "<tr><td>newer_all       </td>\n" );
	foreach ( $gammaStructure ["entries"] as $entry )
		printf ( "<td><b>%d</b></td>", $entry ["newer_all"] );
	printf ( "</tr>\n" );
	printf ( "<tr><td>older_all       </td>\n" );
	foreach ( $gammaStructure ["entries"] as $entry )
		printf ( "<td><b>%d</b></td>", $entry ["older_all"] );
	printf ( "</tr>\n" );
	printf ( "<tr><td>oldest_scaled   </td>\n" );
	foreach ( $gammaStructure ["entries"] as $entry )
		printf ( "<td><b>%d</b></td>", $entry ["oldest_scaled"] );
	printf ( "</tr>\n" );
	printf ( "<tr><td>newest_scaled   </td>\n" );
	foreach ( $gammaStructure ["entries"] as $entry )
		printf ( "<td><b>%d</b></td>", $entry ["newest_scaled"] );
	printf ( "</tr>\n" );
	printf ( "<tr><td>newer_scaled    </td>\n" );
	foreach ( $gammaStructure ["entries"] as $entry )
		printf ( "<td><b>%d</b></td>", $entry ["newer_scaled"] );
	printf ( "</tr>\n" );
	printf ( "<tr><td>older_scaled    </td>\n" );
	foreach ( $gammaStructure ["entries"] as $entry )
		printf ( "<td><b>%d</b></td>", $entry ["older_scaled"] );
	printf ( "</tr>\n" );
	printf ( "</table>\n" );
}
function getGammaStructure() {
	$gammas_file = fopen ( "/dev/gamma_cache", "w+" );
	fseek ( $gammas_file, 0, SEEK_END );
	$numberOfEntries = ftell ( $gammas_file );
	fclose ( $gammas_file );
	$gammaStructure = array ();
	$g_raw = elphel_gamma_get_raw ( 0 );// Does not depend on $GLOBALS['sensor_port']
	$g_raw_ul = unpack ( 'V*', $g_raw );
	$gammaStructure ["oldest_non_scaled"] = $g_raw_ul [5];
	$gammaStructure ["newest_non_scaled"] = $g_raw_ul [6];
	$gammaStructure ["oldest_all"] = $g_raw_ul [7];
	$gammaStructure ["newest_all"] = $g_raw_ul [8];
	$gammaStructure ["non_scaled_length"] = $g_raw_ul [9];
	$gammaStructure ["num_locked"] = $g_raw_ul [10];
	$gammaStructure ["locked_col"] = array (
			$g_raw_ul [11],
			$g_raw_ul [12],
			$g_raw_ul [13],
			$g_raw_ul [14] 
	);
	$gammaStructure ["entries"] = array ();
	for($i = 1; $i < $numberOfEntries; $i ++) {
		$g_raw = elphel_gamma_get_raw ( $i ); // Does not depend on $GLOBALS['sensor_port']
		$g_raw_ul = unpack ( 'V*', $g_raw );
		if ($g_raw_ul [4] >= 0) { // / >=0 if ever used. This field seems to do nothing in the code.
			$hash32 = $g_raw_ul [1];
			$gammaStructure ["entries"] [$i] = array (
					"index" => $i,
					"hash32" => $hash32,
					"scale" => ($hash32 & 0xffff) / 1024.0,
					"gamma" => (($hash32 >> 16) & 0xff) / 100.0,
					"black" => (($hash32 >> 24) & 0xff),
					"valid" => $g_raw_ul [2], // / 0 - table invalid, 1 - table valid +2 for table locked (until sent to FPGA)
					"locked" => $g_raw_ul [3], // / bit frame+ (color<<3) locked for color/frame
					"this_non_scaled" => $g_raw_ul [4], // / 0 for non-scaled, others - (for scaled) - pointer to the corresponding non-scaled
					                                          // / This is non-scaled (gamma data is full 16-bit)
					"newer_non_scaled" => $g_raw_ul [5], // / table type (non-scaled prototype) used later than this one
					"older_non_scaled" => $g_raw_ul [6], // / table type (non-scaled prototype) used before this one
					
					"newer_all" => $g_raw_ul [7], // / newer in a single chain of all scaled tables, regardless of the prototype
					"older_all" => $g_raw_ul [8], // / older in a single chain of all scaled tables, regardless of the prototype
					                                          // /Next two pairs are the same (union)
					"oldest_scaled" => $g_raw_ul [9], // / oldest derivative of this prototype (scaled)
					"newest_scaled" => $g_raw_ul [10], // / newest derivative of this prototype (scaled)
					"newer_scaled" => $g_raw_ul [9], // / table type (non-scaled prototype) used later than this one
					"older_scaled" => $g_raw_ul [10] 
			) // / table type (non-scaled prototype) used before this one
;
		}
	}
	return $gammaStructure;
}
function printRawGamma($page = 0) {
	$g_raw = elphel_gamma_get_raw ( $page ); // Does not depend on $GLOBALS['sensor_port']
	// var_dump()
	$g_raw_ul = unpack ( 'V*', $g_raw );
	echo "<pre>\n";
	printf ( "Gamma cache page %d, length=%d\n", $page, strlen ( $g_raw ) );
	$a = 1; // / unpack started with index 1
	$hash32 = $g_raw_ul [$a ++];
	$scale = ($hash32 & 0xffff) / 1024.0;
	$gamma = (($hash32 >> 16) & 0xff) / 100.0;
	$black = (($hash32 >> 24) & 0xff);
	printf ( "hash32= %08x (scale=%f gamma=%f black=%d)\n", $hash32, $scale, $gamma, $black );
	$valid = $g_raw_ul [$a ++];
	printf ( "valid=%d, locked=%d\n", $valid & 1, $valid & 2 );
	
	$locked = $g_raw_ul [$a ++];
	printf ( "locked= 0x%x (for frame=%d/color=%d)\n", $locked, $locked & 7, ($locked >> 3) & 3 );
	
	$this_non_scaled = $g_raw_ul [$a ++]; // / 0 for non-scaled
	printf ( "this_non_scaled=%d\n", $this_non_scaled );
	if ($page == 0) {
		printf ( "oldest_non_scaled=%d\n", $g_raw_ul [$a ++] );
		printf ( "newest_non_scaled=%d\n", $g_raw_ul [$a ++] );
	} else {
		printf ( "newer_non_scaled=%d\n", $g_raw_ul [$a ++] );
		printf ( "older_non_scaled=%d\n", $g_raw_ul [$a ++] );
	}
	
	if ($page == 0) {
		printf ( "oldest_all=%d\n", $g_raw_ul [$a ++] );
		printf ( "newest_all=%d\n", $g_raw_ul [$a ++] );
	} else {
		printf ( "newer_all=%d\n", $g_raw_ul [$a ++] );
		printf ( "older_all=%d\n", $g_raw_ul [$a ++] );
	}
	
	if ($page == 0) {
		printf ( "non_scaled_length=%d\n", $g_raw_ul [$a ++] ); // / current number of different hash values
		printf ( "num_locked=%d\n", $g_raw_ul [$a ++] ); // / number of nodes locked (until table sent to FPGA)
	} else if ($this_non_scaled == 0) {
		printf ( "oldest_scaled=%d\n", $g_raw_ul [$a ++] );
		printf ( "newest_scaled=%d\n", $g_raw_ul [$a ++] );
	} else {
		printf ( "newer_scaled=%d\n", $g_raw_ul [$a ++] );
		printf ( "older_scaled=%d\n", $g_raw_ul [$a ++] );
	}
	// /data tables
	if ($page == 0) {
		printf ( "\nTable of locked indexes\n" );
		for($color = 0; $color < 4; $color ++) {
			// for ($frame=0;$frame<8; $frame++) {
			printf ( " %4d", $g_raw_ul [$a ++] );
			// }
			// printf ("\n");
		}
		printf ( "\n" );
		// / no need to dump the rest - it is unused in the page 0
		printf ( "\n\nUnused area on page 0:" );
		// for ($i=0; $i<417; $i++) {
		for($i = 0; $i < 445; $i ++) {
			if (($i & 0x0f) == 0)
				printf ( "\n0x%03x:", $i );
			$d = $g_raw_ul [$a ++];
			printf ( " %08x", $d );
		}
	} else {
		printf ( "\nGamma table (direct):" );
		for($i = 0; $i < 129; $i ++) {
			if (($i & 0x07) == 0)
				printf ( "\n0x%03x:", $i * 2 );
			$d = $g_raw_ul [$a ++];
			printf ( " %04x %04x", $d & 0xffff, ($d >> 16) & 0xffff );
		}
		printf ( "\n\nGamma table (reverse):" );
		for($i = 0; $i < 64; $i ++) {
			if (($i & 0x03) == 0)
				printf ( "\n0x%03x:", $i * 4 );
			$d = $g_raw_ul [$a ++];
			printf ( " %02x %02x %02x %02x", $d & 0xff, ($d >> 8) & 0xff, ($d >> 16) & 0xff, ($d >> 24) & 0xff );
		}
		
		printf ( "\n\nFPGA gamma data:" );
		for($i = 0; $i < 256; $i ++) {
			if (($i & 0x0f) == 0)
				printf ( "\n0x%03x:", $i );
			$d = $g_raw_ul [$a ++];
			printf ( " %05x", $d );
		}
	}
	echo "</pre>\n";
}
/*
 * struct histogram_stuct_t {
 * unsigned long frame; /// frame number correspoding to the current histogram
 * unsigned long valid; /// bit mask of valid arrays (0 - hist_r, ... ,4-cumul_hist_r, ..., 11 - percentile_b)
 * /// Direct histograms, loaded from the FPGA
 * union {
 * unsigned long hist[1024] ; /// All 4 histograms
 * struct {
 * unsigned long hist_r [256] ; /// Histogram for the red component
 * unsigned long hist_g [256] ; /// Histogram for the first green component (in the "red" line)
 * unsigned long hist_gb[256] ; /// Histogram for the second green component (in the "blue" line)
 * unsigned long hist_b [256] ; /// Histogram for blue component
 * };
 * };
 * /// Direct cumulative histograms, calculated from the loaded from the FPGA
 * union {
 * unsigned long cumul_hist[1024] ; /// All 4 cumulative histograms
 * struct {
 * unsigned long cumul_hist_r [256] ; /// Cumulative histogram for the red component
 * unsigned long cumul_hist_g [256] ; /// Cumulative histogram for the first green component (in the "red" line)
 * unsigned long cumul_hist_gb[256] ; /// Cumulative histogram for the second green component (in the "blue" line)
 * unsigned long cumul_hist_b [256] ; /// Cumulative histogram for blue component
 * };
 * };
 * /// Calculated reverse cumulative histograms (~percentiles) - for the given 1 byte input X (0 - 1/256 of all pixels, ..., 255 - all pixels)
 * /// returns threshold value P (0..255), so that number of pixels with value less than x is less or equal to (P/256)*total_number_of_pixels,
 * /// and number of pixels with value less than (x+1) is greater than (P/256)*total_number_of_pixels,
 * /// P(0)=0, P(256)=256 /not included in the table/
 * /// percentiles arrays are calculated without division for each element, interpolation (with division) will be done only for the value of interest
 * /// on demand, in the user space.
 * /// NOTE: - argument is _output_ value (after gamma-correction), reverse gamma table is needed to relate percentiles to amount of light (proportional to exposure)
 * union {
 * unsigned char percentile[1024] ; /// All 4 percentiles
 * struct {
 * unsigned char percentile_r [256] ; /// percentile for the red component
 * unsigned char percentile_g [256] ; /// percentile for the first green component (in the "red" line)
 * unsigned char percentile_gb[256] ; /// percentile for the second green component (in the "blue" line)
 * unsigned char percentile_b [256] ; /// percentile for the blue component
 * };
 * };
 * };
 *
 */
function printHistogram($frame) {
	$colors = array (
			0 => "R",
			1 => "G",
			2 => "GB",
			3 => "B" 
	);
	$h_arr = elphel_histogram_get ($GLOBALS['sensor_port'], $GLOBALS['$sensor_subchn'], 0xfff, $frame );
	$a = 0;
	$offset2sum = 1024 + 255; // / last in cumulative histogram for the same color
	echo "<pre>\n";
	for($color = 0; $color < 4; $color ++) {
		printf ( "\nhistogram for color #%d %s, Total number of pixels=%d (0x%x):", $color, $colors [$color], $h_arr [$a + $offset2sum], $h_arr [$a + $offset2sum] );
		for($i = 0; $i < 256; $i ++) {
			if (($i & 0x0f) == 0)
				printf ( "\n0x%03x:", $i );
			printf ( " %05x", $h_arr [$a ++] );
		}
		printf ( "\n" );
	}
	for($color = 0; $color < 4; $color ++) {
		printf ( "\ncumulative histogram for color #%d %s:", $color, $colors [$color] );
		for($i = 0; $i < 256; $i ++) {
			if (($i & 0x0f) == 0)
				printf ( "\n0x%03x:", $i );
			printf ( " %08x", $h_arr [$a ++] );
		}
		printf ( "\n" );
	}
	for($color = 0; $color < 4; $color ++) {
		printf ( "\npercentile for color #%d %s:", $color, $colors [$color] );
		for($i = 0; $i < 256; $i ++) {
			if (($i & 0x01f) == 0)
				printf ( "\n0x%03x:", $i );
			printf ( " %02x", $h_arr [$a ++] );
		}
		printf ( "\n" );
	}
	echo "</pre>\n";
}
function printRawHistogram($needed, $frame) {
	$percentile_start = 8232;
	$colors = array (
			0 => "R",
			1 => "G",
			2 => "GB",
			3 => "B" 
	);
	$h_raw = elphel_histogram_get_raw ($GLOBALS['sensor_port'], $GLOBALS['$sensor_subchn'],  $needed, $frame );
	// var_dump()
	$h_raw_ul = unpack ( 'V*', substr ( $h_raw, 0, $percentile_start ) );
	echo "<pre>\n";
	$a = 1; // / unpack started with index 1
	$hframe = $h_raw_ul [$a ++];
	$valid = $h_raw_ul [$a ++];
	$hash32_r = $h_raw_ul [$a ++];
	$hash32_g = $h_raw_ul [$a ++];
	$hash32_gb = $h_raw_ul [$a ++];
	$hash32_b = $h_raw_ul [$a ++];
	
	printf ( "Histogram for frame= %d (0x%x), valid mask=0x%x, requested=0x%x, data length=%d (0x%x)\n", $hframe, $hframe, $valid, $needed, strlen ( $h_raw ), strlen ( $h_raw ) );
	printf ( "hash32: R:0x%x G:0x%x GB:0x%x B:0x%x)\n", $hash32_r, $hash32_g, $hash32_gb, $hash32_b );
	for($color = 0; $color < 4; $color ++) {
		$sum = 0;
		for($i = 0; $i < 256; $i ++)
			$sum += $h_raw_ul [$a + $i];
		printf ( "\nhistogram for color #%d %s sum=%d (0x%x):", $color, $colors [$color], $sum, $sum );
		for($i = 0; $i < 256; $i ++) {
			if (($i & 0x0f) == 0)
				printf ( "\n0x%03x:", $i );
			$d = $h_raw_ul [$a ++];
			printf ( " %05x", $d );
		}
		printf ( "\n" );
	}
	for($color = 0; $color < 4; $color ++) {
		printf ( "\ncumulative histogram for color #%d %s:", $color, $colors [$color] );
		for($i = 0; $i < 256; $i ++) {
			if (($i & 0x0f) == 0)
				printf ( "\n0x%03x:", $i );
			$d = $h_raw_ul [$a ++];
			printf ( " %08x", $d );
		}
		printf ( "\n" );
	}
	for($color = 0; $color < 4; $color ++) {
		printf ( "\npercentile for color #%d %s:", $color, $colors [$color] );
		for($i = 0; $i < 256; $i ++) {
			if (($i & 0x01f) == 0)
				printf ( "\n0x%03x:", $i );
			printf ( " %02x", ord ( $h_raw [$percentile_start + (256 * $color) + $i] ) );
		}
		printf ( "\n" );
	}
	echo "</pre>\n";
}
function printRawFrame($frame) {
	$fp_raw = elphel_framepars_get_raw ($GLOBALS['sensor_port'],  $frame );
	$fp_raw_ul = unpack ( 'V*', $fp_raw );
	echo "<pre>\n";
	printf ( "\nFrame= %d(%08x)\n", $frame, $frame );
	$a = 1; // / unpack started with index 1
	echo ".pars:";
	for($i = 0; $i < 927; $i ++) {
		if (($i & 0x0f) == 0)
			printf ( "\n0x%03x:", $i );
		printf ( " %08x:", $fp_raw_ul [$a ++] );
	}
	printf ( "\n.functions= %08x:", $fp_raw_ul [$a ++] );
	echo "\n.modsince:";
	for($i = 0; $i < 31; $i ++) {
		if (($i & 0x0f) == 0)
			printf ( "\n0x%03x:", $i );
		printf ( " %08x:", $fp_raw_ul [$a ++] );
	}
	printf ( "\n.modsince32= %08x:", $fp_raw_ul [$a ++] );
	echo "\n.mod:";
	for($i = 0; $i < 31; $i ++) {
		if (($i & 0x0f) == 0)
			printf ( "\n0x%03x:", $i );
		printf ( " %08x:", $fp_raw_ul [$a ++] );
	}
	printf ( "\n.mod32= %08x:", $fp_raw_ul [$a ++] );
	echo "\n.needproc:";
	for($i = 0; $i < 31; $i ++) {
		if (($i & 0x0f) == 0)
			printf ( "\n0x%03x:", $i );
		printf ( " %08x:", $fp_raw_ul [$a ++] );
	}
	printf ( "\n.needproc32= %08x:", $fp_raw_ul [$a ++] );
	// var_dump($fp_raw_ul);
	echo "</pre>\n";
}
function image_table8() {
	global $imgsrv;
	// $back=6;
	// $min_back=1;
	$back = 8;
	$min_back = 0;
	printf ( "<table>\n" );
	$scale = "100%";
	$row_open = $false;
	for($hist = $back; $hist >= $min_back; $hist --) {
		if (! (($back - $hist) % 3)) {
			if ($row_open)
				printf ( "</tr>\n" );
			printf ( "<tr>" );
			$row_open = true;
		}
		$url = $imgsrv;
		for($i = 0; $i < $hist; $i ++)
			$url .= '/prev';
			// $url .= '/img';
		printf ( '<td><a href="%s"><img src="%s" width="%s" height="%s"/><br \>%d</a>&nbsp;&nbsp;<a href="%s">meta</a></td>', $url . '/img', $url . '/img', $scale, $scale, $hist, $url . '/meta' );
	}
	if ($row_open)
		printf ( "</tr>\n" );
	printf ( "</table>\n" );
}
?>
