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

include 'include/response.php';

$cmd = "donothing";
if (isset($_GET['cmd']))
  $cmd = $_GET['cmd'];
else if (isset($argv[1]))
  $cmd = $argv[1];

#hardcoded for eyesis4pi
$symlink = "/www/pages/ssd";
$mountpoint = "/mnt/sda1";
  
switch($cmd){
  case "symlink":
    if (is_link($symlink)) die("already exists");
    die(symlink($mountpoint,$symlink));
    break;
  case "free_space":
    if ($_GET['mountpoint']=="/mnt/sda2"){
      respond_xml("test");
      //unpartitioned area
    }else{
        if (is_dir($mountpoint)) $res = disk_free_space($mountpoint);
        else                     $res = 0;
        respond_xml($res);
    }
    break;
  default:
    print("nothing has been done");
}
  
?>
