<?php
/*
*!***************************************************************************
*! FILE NAME  : eyesis4pi_interface.php
*! DESCRIPTION: command interface for the eyesis4pi gui
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
  
switch($cmd){
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
      $sda2 = "";
    }
    
    respond_xml("{$sda1} {$sda2}");
    
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
  
?>
