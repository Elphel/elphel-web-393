<?php
/*!***************************************************************************
*! FILE NAME  : elphel_functions_include.php
*! DESCRIPTION: various functions
*! Copyright (C) 2016 Elphel, Inc
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
*/

/*
* Contents:
*     * OTHER
*     * LOG AND XML RESPONSE
*     * PARALLEL HTTP REQUESTS
*     * DEVICES
*/


// OTHER
function get_uptime(){
	exec('cat /proc/uptime',$output,$retval);
	return floatval(explode(" ",trim($output[0]))[0]);
}

// LOG AND XML RESPONSE
function colorize($string, $color, $bold) {
	$color = strtoupper($color);
	$attr = array();
	switch ($color) {
		case 'RED':     $attr[]='31'; break;
		case'GREEN':    $attr[]='32'; break;
		case 'YELLOW':  $attr[]='33'; break;
		case 'BLUE':	$attr[]='34'; break;
		case 'MAGENTA':	$attr[]='35'; break;
		case 'CYAN':	$attr[]='36'; break;
		case 'GRAY':	$attr[]='37'; break;
	}
	if ($bold)	$attr[] = '1';
	return sprintf("\x1b[%sm%s\x1b[0m",implode(';',$attr), $string);
}

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
		$xml = new SimpleXMLElement("<?xml version='1.0'  standalone='yes'?><Document/>");
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

// PARALLEL HTTP REQUESTS

// Using parallel requests, PHP has to be configured '--with-curl=' (and libcurl should be installed)
function curl_multi_start($urls, $with_headers=0) {
	// numprime is needed to actually send the request and remote starts executing it
	// Not really clear - what it should be
	$numprime = 4; // magic number, see http://lampe2e.blogspot.com/2015/03/making-stuff-faster-with-curlmultiexec.html
	$curl_mh = curl_multi_init ();
	$aCurlHandles = array ();
	foreach ($urls as $url) {
		$ch = curl_init ();
		curl_setopt ($ch, CURLOPT_URL, $url);
		curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt ($ch, CURLOPT_HEADER, $with_headers);
		$aCurlHandles[] = $ch;
		curl_multi_add_handle ($curl_mh, $ch);
	}
	$curl_active = count ($urls);
	for($x = 0; $x < $numprime && $curl_active; $x++) {
		curl_multi_exec ($curl_mh, $curl_active);
		// we wait for a bit to allow stuff TCP handshakes to complete and so forth...
		usleep (10000);
//		echo ".";
	}
	return array ("mh" => $curl_mh,"handles" => $aCurlHandles);
}

function curl_multi_finish($data, $use_xml=true, $ntry=0, $echo = false, $with_headers = 0) {
	$curl_active = 1;
	$curl_mrc = CURLM_OK;
	$nrep = 0;
	$curl_mh = $data['mh'];
	while ($curl_active && $curl_mrc == CURLM_OK ) {
		if (curl_multi_select ($curl_mh) != -1) {
			do {
				$curl_mrc = curl_multi_exec ($curl_mh, $curl_active);
			} while ($curl_mrc == CURLM_CALL_MULTI_PERFORM );
		} else {
		    break; // all activity was over before call to curl_multi_finish()
		}
		if ($echo) echo colorize("$curl_active ",'YELLOW',1);
		$nrep++;
		if ($ntry && ($nrep > $ntry)) {
			break;
		}
	}
	$results = array ();
	$names = array ();
	if ($use_xml) {
		foreach ($data['handles'] as $i => $ch) {
			$xml = simplexml_load_string (curl_multi_getcontent ($ch));
			curl_multi_remove_handle ($curl_mh, $ch);
			$results[$i] = array ();
			try {
    			foreach ($xml as $tag => $value) {
    				$svalue = (string) $value;
    				if (strlen ($svalue) > 0) {
    					if ($svalue[0] == '"') $results[$i][$tag] = trim ($svalue, '"');
    					else $results[$i][$tag] = (int) $svalue;
    				}
    			}
			} catch (exception $e) {
			  // empty array?  
			}
		}
	} else {
		foreach ($data['handles'] as $i => $ch) {
			$r = curl_multi_getcontent ($ch);
			curl_multi_remove_handle ($curl_mh, $ch);
			if ($with_headers==1){
				$hsize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
				$h = substr($r, 0, $hsize);
				$r = substr($r, $hsize);
				$names[] = curl_get_filename_from_header($h);
			}
			$results[] = $r;
		}
		if ($with_headers==1){
			$results = Array('names' => $names,'contents'=> $results);
		}
	}
	curl_multi_close ($curl_mh);
	return $results;
}

function curl_get_filename_from_header($h){

	$h = explode("\n",$h);
	$res = "";
	if ($h){
		foreach($h as $e){
			if (strlen($e)!=0){
				$tmp = explode(":",$e);
				if ($tmp[0]=="Content-Disposition"){
					$tmp = explode(";",$tmp[1]);
					foreach($tmp as $t){
						$t = trim($t);
						$t = explode("=",$t);
						if ($t[0]=="filename"){
							$res = trim($t[1]," \t\n\r\0\x0B\"");
							break;
						}
					}
					break;
				}
			}
		}
	}

	return $res;

}

// DEVICES

/** Get a list of suitable partitions. The list will contain SATA devices only and
 * will have the following format: "name" => "size_in_blocks".
 */
function get_partitions()
{
	$names = array();
	$regexp = '/([0-9]+) +(sd[a-z0-9]+$)/';
	exec("cat /proc/partitions", $partitions);

	// the first two elements of an array are table header and empty line delimiter, skip them
	for ($i = 2; $i < count($partitions); $i++) {
		// select SATA devices only
		if (preg_match($regexp, $partitions[$i], $name) == 1) {
			$names[$name[2]] = $name[1];
			$j++;
		}
	}
	return $names;
}

/** Get a list of disk devices which have file system and can be mounted. This function
 *  uses 'blkid' command from busybox.
 */
function get_mnt_dev()
{
	$partitions = get_partitions();
	$devices = array();
	$fs_types = array();
	foreach ($partitions as $partition => $size) {
		$res = array();
		$dev = "/dev/" . $partition;
		exec("blkid " . $dev, $res);
		if (!empty($res)) {
			$devices[$i] = preg_replace('/: +.*/', "", $res[0]);
			if (preg_match('/(?<=TYPE=")[a-z0-9]+(?=")/', $res[0], $fs) == 1)
				$fs_types[$i] = $fs[0];
			else
				$fs_types[$i] = "none";
			$i++;
		}
	}

	return array("devices" => $devices, "types" => $fs_types);
}

/** Get a list of devices whithout file system which can be used for raw disk storage from camogm. */
function get_raw_dev()
{
	$j = 0;
	$ret = get_mnt_dev();
	$devices = $ret["devices"];
	$types = $ret["types"];

	$names = get_partitions();

	// filter out partitions with file system
	$i = 0;
	$raw_devices = array();

	foreach ($names as $name => $size) {
		$found = false;
		foreach ($devices as $device) {
			if (strpos($device, $name) !== false)
				$found = true;
		}
		if ($found === false) {
			// current partition is not found in the blkid list, add it to raw devices
			$raw_devices["/dev/" . $name] = $size;
			$i++;
		}
	}

	//special case
	if (count($raw_devices)>1) {
            foreach($raw_devices as $k=>$v){
                if (preg_match('/sd[a-z][0-9]/',$k)==0) {
                    unset($raw_devices[$k]);
                }
            }
	}
	return $raw_devices;
}

/** Get a list of detected sensors (no mux). Readimg from sysfs. */
function get_sensors(){
    $sensors = array('none','none','none','none');
    $path = "/sys/devices/soc0/elphel393-detect_sensors@0";
    for($i=0;$i<count($sensors);$i++){
        $s = "$path/sensor{$i}0";
        if (is_file($s)){
            $c = trim(file_get_contents($s));
            $sensors[$i] = $c;
        }
    }
    return $sensors;
}

/**
 * Locating process(es) by name and optional interpreter name.
 * 
 * @param string name - name of the program/script, both basename and full name are OK.
 * @param string $interpreter - name of the interpreter (optional), both basename and full name are OK. This
 *        parameter is required when script is executed with explicit interpreter (such as in 'php script.php'.
 * @param boolean $active_only - skip zombie and kernel thread processes
 * @return array of matches, each match may have the following fields:
 *  'active' - false for zombies/kernel threads (indicated by [] in COMMAND field), true otherwise
 *  'pid' -  integer PID of the process ) (first column of ps output)
 *  'user' - process owner (second column of ps output)
 *  'vsz' -  process virtual memory size (multiplied by 1000000 if third column of ps output ends with 'm')
 *  'stat' - fourth column of ps output
 *  'scmd' - fifth column of the ps output (with enclosing [] removed)
 *  'exe'-   (optional) called script path if present (enclosed in {} in ps output) when interpreter is invoked from #! line
 *  'interpreter' - intrepreter full path if specified or detected from #! by ps
 *  'argv' - array of the command line tokens, starting with the program/script path. 
 *  
 *  Long argument lists are truncated by "ps -w"
 */
function getPIDByName($name, $interpreter="",$active_only=false){
    $ss = $interpreter ? ($interpreter.".*".$name) : $name;
    exec('ps -w | grep "'.$ss.'"', $arr);
    $rslt=array();
    $sep = " \n\t";
    foreach ($arr as $entry){
//        echo $entry."\n";
        $active = substr($entry, -1) != ']';
        $l = array();
        $l['active'] =    $active;
        $l['pid'] = (int) strtok($entry, $sep);
        $l['user'] =      strtok($sep);
        $vsz=             strtok($sep);
        $l['vsz'] = (int) $vsz;
        if (strchr($vsz,'m')){
            $l['vsz'] *=1000000;
        }
        $l['stat'] =      strtok($sep);
        $l['scmd'] =   trim(trim(strtok('')),'[]'); // rest of the string with leading ' ' removed
        
        // parse command line
        $acmd = array();
        $tok = strtok($l['scmd'], $sep);
        while ($tok !== false){
            $acmd[] = $tok;
            $tok = strtok($sep);
        }
        
        if ($acmd[0][0] == "{"){
            $l['exe'] = trim(array_shift($acmd),'{}'); // first token - enclosed in {} executed script that calle interpreter throuh #!
            if (!$interpreter) {
                $interpreter = $acmd[0];
                if (strrpos($interpreter, '/') !== false){
                    $interpreter = substr($interpreter, strrpos($interpreter, '/') + 1);
                }
            }
        }
        if ($interpreter){ // see if interpreter matches
            $first_tok = array_shift($acmd); // shifts array, removes first element
            $offs = strpos($first_tok,$interpreter);
            if ($offs === false){
                continue; // interpreter does not match
            } else if (($offs >0) && ($first_tok[$offs -1] != '/')){
                continue; // interpreter does not match (only ends with)
            }
            $l['interpreter'] = $first_tok;
        }
        if (!count($acmd)){
            continue; // only interpreter present; // should not get here
        }
        $argv0 = $acmd[0];
        $offs = strpos($argv0,$name);
        if ($offs === false){
            continue; // program name does not match
        } else if (($offs >0) && ($argv0[$offs -1] != '/')){
            continue; // program name does not match (only ends with)
        }
        $l['argv'] = $acmd;
        $rslt[] = $l;
    }
    return $rslt;
}
?>
