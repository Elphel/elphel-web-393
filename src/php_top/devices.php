<?php
/*
*!***************************************************************************
*! FILE NAME  : devices.php
*! DESCRIPTION: get devices info
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

?>
