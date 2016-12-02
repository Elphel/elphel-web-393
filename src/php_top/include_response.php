<?php
/*
*!***************************************************************************
*! FILE NAME  : include_response.php
*! DESCRIPTION: http requests response functions
*! Copyright (C) 2016 Elphel, Inc.
*! --------------------------------------------------------------------------
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
*! --------------------------------------------------------------------------
*/

function get_uptime(){
	exec('cat /proc/uptime',$output,$retval);
	return floatval(explode(" ",trim($output[0]))[0]);
}
//printf("%08.2f\n",$f);
function log_open(){
	$GLOBALS['logFile'] = fopen ( $GLOBALS['logFilePath'], "a" );
}

/** Log message and optionally print to console */
function log_msg($msg,         ///< message to print
		         $mode = -1)   ///< -1 - print only short messages, 0 - never print, 1 - always print, 2 print in bold red (error), 3 - bold white, 4 - bold yellow (warning)
{
// do not output log when in HTTP request mode
	$ut=get_uptime();
	if (($mode != 0) && !array_key_exists ('REQUEST_METHOD', $_SERVER) && (($mode > 0) || (strlen ($msg) < $GLOBALS['LOG_MAX_ECHO']))) {
		switch ($mode) {
			case 2:
				$emsg = colorize($msg,'RED',1); // bold red
				break;
			case 3:
				$emsg = colorize($msg,'',1);    // bold white
				break;
			case 4 :
				$emsg = colorize($msg, 'YELLOW', 1); // bold white
				break;
			default:
				$emsg = $msg;
		}
		printf(colorize(sprintf("[%8.2f] autocampars: ",$ut),"GREEN",0).$emsg."\n");
	}
	fwrite ($GLOBALS['logFile'], sprintf("%08.2f autocampars: %s\n",$ut,$msg)); // date ("F j, Y, G:i:s")
}
function log_error($msg) {
	log_msg ($msg, 2);
	log_close ();
	exit (1);
}
function log_close() {
	log_msg ("Log file saved as " . $GLOBALS['logFilePath'], 3);
	log_msg ("----------------------------------------------", 0);
	fclose ($GLOBALS['logFile']);
	unset ($GLOBALS['logFile']); // to catch errors
}

/** Closes log file, optianally responxds with XML (if in HTTP mode), exits with 0/1 */
function respond_xml($result,$error=null,$color_mode = 3){ // default white bold
	if (array_key_exists('REQUEST_METHOD',$_SERVER)){
		$xml = new SimpleXMLElement("<?xml version='1.0'  standalone='yes'?><autocampars/>");
		if ($result !== ""){ //"" will not be loged/output
			if (is_string($result) && ((count($result)==0) || ($result[0] != '"'))){
				$result = '"'.$result.'"';
			}
			$xml->addChild ('result',$result);
		}
		if ($error){
			$xml->addChild ('error','"'.$error.'"');
		}
		$rslt=$xml->asXML();
		header("Content-Type: text/xml");
		header("Content-Length: ".strlen($rslt)."\n");
		header("Pragma: no-cache\n");
		printf($rslt);
	}
	
	if (isset($GLOBALS['logFile'])){
		if ($result !== ""){ //"" will not be loged/output
			log_msg(''.$result,$color_mode);
		}
		if ($error){
			log_error($error);
		} else {
			log_close();
			exit (0);
		}
	}
}

?> 
