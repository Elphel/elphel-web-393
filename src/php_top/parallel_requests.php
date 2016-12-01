<?php
/*!***************************************************************************
*! FILE NAME  : parallel_requests.php
*! DESCRIPTION: send out parallel url requests and collect responses
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

// Using parallel requests, PHP has to be configured '--with-curl=' (and libcurl should be installed)
function curl_multi_start($urls) {
	// numprime is needed to actually send the request and remote starts executing it
	// Not really clear - what it should be
	$numprime = 4; // magic number, see http://lampe2e.blogspot.com/2015/03/making-stuff-faster-with-curlmultiexec.html
	$curl_mh = curl_multi_init ();
	$aCurlHandles = array ();
	foreach ($urls as $url) {
		$ch = curl_init ();
		curl_setopt ($ch, CURLOPT_URL, $url);
		curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt ($ch, CURLOPT_HEADER, 0);
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
	
function curl_multi_finish($data, $use_xml=true, $ntry=0, $echo = false) {
	$curl_active = 1;
	$curl_mrc = CURLM_OK;
	$nrep = 0;
	$curl_mh = $data['mh'];
	while ($curl_active && $curl_mrc == CURLM_OK ) {
		if (curl_multi_select ($curl_mh) != -1) {
			do {
				$curl_mrc = curl_multi_exec ($curl_mh, $curl_active);
			} while ($curl_mrc == CURLM_CALL_MULTI_PERFORM );
		}
		if ($echo) echo colorize("$curl_active ",'YELLOW',1);
		$nrep++;
		if ($ntry && ($nrep > $ntry)) {
			break;
		}
	}
	$results = array ();
	if ($use_xml) {
		foreach ($data['handles'] as $i => $ch) {
			$xml = simplexml_load_string (curl_multi_getcontent ($ch));
			curl_multi_remove_handle ($curl_mh, $ch);
			$results[$i] = array ();
			foreach ($xml as $tag => $value) {
				$svalue = (string) $value;
				if (strlen ($svalue) > 0) {
					if ($svalue[0] == '"') $results[$i][$tag] = trim ($svalue, '"');
					else $results[$i][$tag] = (int) $svalue;
				}
			}
		}
	} else {
		foreach ($data['handles'] as $i => $ch) {
			$r = curl_multi_getcontent ($ch);
			curl_multi_remove_handle ($curl_mh, $ch);
			$results[] = $r;
		}
	}
	curl_multi_close ($curl_mh);
	return $results;
}

?> 
