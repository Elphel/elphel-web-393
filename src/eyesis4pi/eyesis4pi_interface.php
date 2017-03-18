<?php
/**
 * @file eyesis4pi_interface.php
 * @brief command interface for the eyesis4pi gui
 * @copyright Copyright (C) 2016 Elphel Inc.
 * @author Oleg Dzhimiev <oleg@elphel.com>
 *
 * @par <b>License</b>:
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

include 'include/elphel_functions_include.php';

$cmd = "donothing";
if (isset($_GET['cmd']))
  $cmd = $_GET['cmd'];
else if (isset($argv[1]))
  $cmd = $argv[1];

#hardcoded for eyesis4pi
$symlink = "/www/pages/ssd";
$mountpoint = "/mnt/sda1";

$camogmdisk = "/home/root/camogm.disk";
$nandbootpath = "/tmp/rootfs.ro";

if (is_dir($nandbootpath)) $camogmdisk = $nandbootpath.$camogmdisk; 

$sysfs_lba_path = "/sys/devices/soc0/amba@0/80000000.elphel-ahci/";

$file_lba_start = $sysfs_lba_path."lba_start";
$file_lba_current = $sysfs_lba_path."lba_current";
$file_lba_end = $sysfs_lba_path."lba_end";

switch($cmd){
  case "camogm_debug":
        exec("echo 'debug=/tmp/camogm.log;debuglev=6' > /var/state/camogm_cmd");
        echo "$cmd ok";
        break;
  case "camogm_kill":
        exec("killall -9 camogm");
        echo "$cmd ok";
        break;
  case "logs_download":
  
        header("Content-Type: application/octet-stream");
        header("Content-Disposition: attachment; filename=\"{$_SERVER["SERVER_ADDR"]}.logs\"");
  
        echo_file($sysfs_lba_path."io_error");
        echo_file($file_lba_start);
        echo_file($file_lba_current);
        echo_file($file_lba_end);
        echo_file($camogmdisk);
        echo_file("/var/log/messages");
        echo_file("/var/tmp/camogm.log");
        
        break;
  case "external_drive":
  	switch_sata_connection("external");
  	echo "$cmd ok";
  	break;
  case "internal_drive":
  	switch_sata_connection("internal");
  	echo "$cmd ok";
  	break;
  case "symlink":
    if (is_link($symlink)) die("already exists");
    die(symlink($mountpoint,$symlink));
    break;
  case "free_space":
    //sda1
    if (is_dir($mountpoint)){
      $sda1 = round(disk_free_space($mountpoint)/1024/1024/1024,2);
      $sda1 .= "G";
    }
    
    
    //sda2
    $lba_start = 0;
    $lba_current = 0;
    $lba_end = 0;
    
    if (is_file($file_lba_start))   $lba_start   = floatval(trim(file_get_contents($file_lba_start)));
    if (is_file($file_lba_current)) $lba_current = floatval(trim(file_get_contents($file_lba_current)));
    if (is_file($file_lba_end))     $lba_end     = floatval(trim(file_get_contents($file_lba_end)));
    
    if (($lba_start!=0)&&($lba_current!=0)&&($lba_end!=0)){
    	//$size = ((($lba_end>>10)&0x003fffff) - (($lba_current>>10)&0x003fffff))/2/1024;
    	$size = ($lba_end - $lba_current)/2/1024/1024;
    	$sda2 = round($size,2);
    	$sda2 .= "G";
    }else{
    	// camogm.disk not found
    	if (!is_file($camogmdisk)){
    		$devices = get_raw_dev();
    		foreach($devices as $device=>$size){
    			//size in MB
    			if ($device=="/dev/sda2") {
    				$sda2 = round($size/1048576,2);
    				$sda2 .= "G";
    			}
    		}
    	}else{
    		//read camogm.disk file
    		$content = file_get_contents($camogmdisk);
    		$content = trim(preg_replace('/\n|\t{2,}/',"\t",$content));
    		$content_arr = explode("\t",$content);
    		
    		if (count($content_arr)>=8){
    			$device = $content_arr[4];
    			$lba_current = $content_arr[6];
    			$lba_end = $content_arr[7];
    			$size = ($lba_end - $lba_current)/2/1024/1024;
    			$sda2 = round($size,2);
    			$sda2 .= "G";
    		}else{    		
	    		//tmp
	    		$devices = get_raw_dev();
	    		foreach($devices as $device=>$size){
	    			//size in MB
	    			if ($device=="/dev/sda2") {
	    				$sda2 = round($size/1048576,2);
	    				$sda2 .= "G";
	    			}
	    		}
    		}
    	}
    }
    respond_xml("{$sda1} {$sda2}");
    break;
    
  case "reset_camogm_fastrec":
  	//remove file
  	if (is_file($camogmdisk)){
  		unlink($camogmdisk);
  	}
  	file_put_contents($file_lba_current,file_get_contents($file_lba_start));
  	print("reset fastrec: ok");
    break;
  case "free_space_bkp":
    // results are in GB
    // /dev/sda2 is not a mountpoint but a device because it does not have a file system
    $res = 0;
    if ($_GET['mountpoint']=="/dev/sda2"){
      //root@elphel393:~# cat /home/root/camogm.disk
      //Device          Start LBA       Current LBA     End LBA
      ///dev/sda2       195334335       545641716       976768065
      if (!is_file($camogmdisk)){
        $devices = get_raw_dev();
        foreach($devices as $device=>$size){
          //size in MB
          if ($device=="/dev/sda2") $res = round($size/1048576,2);
        }
      }else{
        //read camogm.disk file
        $res = 10;
      }
    }else{
      if (is_dir($mountpoint)) $res = round(disk_free_space($mountpoint)/1024/1024/1024,2);
    }
    respond_xml($res);
    break;
  default:
    print("nothing has been done");
}

function echo_file($f){
  if (is_file($f)){
    echo "$f:\n";
    echo file_get_contents($f)."\n";
  }else{
    echo "$f: missing\n";
  }
  return 0;
}

function switch_sata_connection($mode){

  global $mountpoint;

  if       ($mode=="external"){
    exec("/usr/local/bin/x393sata_eyesis4pi_control.py set_zynq_esata");
  }else if ($mode=="internal"){
    exec("/usr/local/bin/x393sata_eyesis4pi_control.py set_zynq_ssd");
  }

}

?>
