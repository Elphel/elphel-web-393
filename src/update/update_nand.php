<?php
/**
 * @file update_software.php
 * @brief update software on nand flash
 * @version 1.0
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

# hardcoded
$UPDATE_DIR = "/var/volatile/html/update";
$NAND_PATH = "/tmp/rootfs.ro";
$FLASH_LOG = "/var/volatile/html/flash.log";
$FLASH_LOG_LINK = "var/flash.log";

$UBI_MNT = "/tmp/ubi0";
$BKP_NAME = "elphel393_backup.tar.gz";
$BKP_DIR  = "/etc/elphel393";

# update files
# file, expertise level, nand partition, size - see http://wiki.elphel.com/index.php?title=NAND_flash_boot_rootfs
# partitions are also listed in the device tree

# for rootfs.ubi - e[3] (=="0 2560") for flash_erase is now calculated from sysfs and depends on the device tree

# WARNING: TRY NOT TO CHANGE
$UPDATE_LIST = array(
  array(0,"boot.bin",      "/dev/mtd0","0 2"),
  array(0,"u-boot-dtb.img","/dev/mtd1","0 8"),
  array(0,"devicetree.dtb","/dev/mtd2","0 8"),
  array(0,"uImage",        "/dev/mtd3","0 128"),
  array(1,"rootfs.tar.gz", "",""),
  //array(0,"rootfs.ubi",    "/dev/mtd4","0 2048","-s 2048 -O 2048"),
  array(0,"rootfs.ubi",    "/dev/mtd4","0 2560","-s 2048 -O 2048"),
  array(1,"rootfs.ubifs",  "/dev/mtd4","/dev/ubi_ctrl -m 4","ubiupdatevol /dev/ubi0_0"),
);

//respond_xml($updatedir);

function lookup_name($name){
  global $UPDATE_LIST;
  foreach($UPDATE_LIST as $e){
    if ($name==$e[1]) return $e;
  }
  return false;
}

function verify($v){
  global $UPDATE_DIR;
  global $NAND_PATH;
  global $UPDATE_LIST;
  
  $rootfs_uploaded = false;
  
  $safe_list = array();
  if (is_dir($UPDATE_DIR)){
    foreach(scandir($UPDATE_DIR) as $e){
      $tmp = lookup_name($e);
      if ($tmp!=false){
        if ($tmp[0]==0){
          array_push($safe_list,$tmp);
        }
      }
    }
  }
  if (count($safe_list)==0){
    $tmp = "";
    foreach($UPDATE_LIST as $e){
      if($e[0]==0){
        switch($e[1]){
          case "boot.bin":
          case "u-boot-dtb.img":
          case "devicetree.dtb":
            $comment = "(rarely updated)";
            break;
          case "uImage":
          case "rootfs.ubi":
            $comment = "(frequently updated)";
            break;
          default:
            $comment = "";
        }
        $tmp .= "<li><b>${e[1]}</b> <span style='font-size:0.9em;'>${comment}</span></li>";
      }
    }
    backup_note();
    $msg = <<<TXT
<b style='color:red;'>ERROR</b>: Files not found. Accepted files are:
<ul>
  $tmp
</ul>
TXT;
    die($msg);
  }else{
    $tmp = "";
    foreach($safe_list as $e){
      if ($e[1]=="rootfs.ubi"){
        $rootfs_uploaded = true;
      }
      $tmp .= "<li>{$e[1]}</li>";
    }
    if ($v) printf("Files to be flashed:<ul>$tmp</ul>");
  }
  //$safe_list is ready
  if (is_dir($NAND_PATH)&&$rootfs_uploaded){
    backup_note();
    rootfs_warning_note();
  }
  if ($v) {
        backup_note();
        printf("<span style='color:green'>Ready for flashing.</span>");
  }
  
  return $safe_list;
}

function rootfs_warning_note(){
        printf("<b style='color:orange'>Warning</b>: To update rootfs, please boot from mmc (<a href='http://wiki.elphel.com/index.php?title=Tmp_manual#Boot'>instructions</a>). Will skip <b>rootfs.ubi</b>");
} 

function backup_note(){
  $tmp = strrpos($_SERVER['SCRIPT_NAME'],"/");
  if ($tmp==0)
    $base = $_SERVER['SCRIPT_NAME'];
  else
    $base = substr($_SERVER['SCRIPT_NAME'],0,$tmp+1);
  print("<b>NOTE</b>: If flashing rootfs, please download a backup copy of <a href='$base?cmd=backup'>/etc/elphel393</a><br/>");
}

function nandflash($list){
  global $UPDATE_DIR;
  global $FLASH_LOG;
  global $FLASH_LOG_LINK;
  global $NAND_PATH;
  
  $rootfs_note = "";
  
  foreach($list as $e){
    if ($e[0]==0){
      if ($e[1]!="rootfs.ubi"){
        exec("flash_unlock ${e[2]} >> $FLASH_LOG");
        exec("flash_erase ${e[2]} ${e[3]} >> $FLASH_LOG");
        exec("nandwrite -n ${e[2]} -p $UPDATE_DIR/${e[1]} >> $FLASH_LOG");
      }else{
        if (!is_dir($NAND_PATH)) {
          exec("flash_unlock ${e[2]} >> $FLASH_LOG");
          // make more universal
          $mtd_device = explode("/",$e[2])[2];
          $mtd_sysfs_path = "/sys/class/mtd/$mtd_device";
          $mtd_size      = intval(file_get_contents("$mtd_sysfs_path/size"));
          $mtd_erasesize = intval(file_get_contents("$mtd_sysfs_path/erasesize"));
          $mtd_size_blocks = intval($mtd_size/$mtd_erasesize);
          $mtd_erase_start = 0;
          //exec("flash_erase ${e[2]} ${e[3]} >> $FLASH_LOG");
          exec("flash_erase ${e[2]} $mtd_erase_start $mtd_size_blocks >> $FLASH_LOG");
          exec("ubiformat ${e[2]} -f $UPDATE_DIR/${e[1]} ${e[4]} >> $FLASH_LOG");
        }else{
          rootfs_warning_note();
        }
      }
    }
  }
  print("Success. See/Download <a href='$FLASH_LOG_LINK'>flash.log</a>. Then power cycle.");
}

function backup(){
  global $NAND_PATH;
  global $UBI_MNT;
  global $BKP_NAME;
  global $BKP_DIR;
  
  if (!is_dir($NAND_PATH)){
    exec("flash_unlock /dev/mtd4");
    exec("ubiattach /dev/ubi_ctrl -m 4");
    if (!is_dir($UBI_MNT)) mkdir($UBI_MNT);
    exec("mount -t ubifs -o ro /dev/ubi0_0 $UBI_MNT");
    if (is_dir("${UBI_MNT}${BKP_DIR}")){
      exec("tar -czvf var/$BKP_NAME -C ${UBI_MNT}${BKP_DIR} .");
    }
    exec("umount $UBI_MNT");
    if (is_dir($UBI_MNT)) rmdir($UBI_MNT);
    exec("ubidetach /dev/ubi_ctrl -m 4");
  }else{
    //booted from nand
    exec("tar -czvf var/$BKP_NAME -C ${BKP_DIR} .");
  }
}

function send_backup(){
  global $BKP_NAME;
  header("Content-Type: application/octet-stream");
  header('Content-Disposition: attachment; filename='.$BKP_NAME);
  print(file_get_contents("var/$BKP_NAME"));
}

function copy_backup(){
  global $NAND_PATH;
  global $UBI_MNT;
  global $BKP_NAME;
  global $BKP_DIR;
  
  if (!is_dir($NAND_PATH)){
    exec("flash_unlock /dev/mtd4");
    exec("ubiattach /dev/ubi_ctrl -m 4");
    if (!is_dir($UBI_MNT)) mkdir($UBI_MNT);
    exec("mount -t ubifs /dev/ubi0_0 $UBI_MNT");
    /*
    if (!is_dir("$UBI_MNT${BKP_DIR}_bkp")) mkdir("$UBI_MNT${BKP_DIR}_bkp");
    exec("tar -C ${UBI_MNT}${BKP_DIR}_bkp -xzpf var/$BKP_NAME");
    */
    
    if (is_file("var/$BKP_NAME")){
    	if (is_dir("$UBI_MNT${BKP_DIR}_defaults")) rmdir("$UBI_MNT${BKP_DIR}_defaults");
    	rename("$UBI_MNT${BKP_DIR}","$UBI_MNT${BKP_DIR}_defaults");
    	//restore old configs
    	exec("mkdir -p ${UBI_MNT}${BKP_DIR}");
    	exec("tar -C ${UBI_MNT}${BKP_DIR} -xzpf var/$BKP_NAME");
    	//take package versions from defaults
    	exec("cp ${UBI_MNT}${BKP_DIR}_defaults/packages/* ${UBI_MNT}${BKP_DIR}/packages/");
    }else{
    	exec("cp -r $UBI_MNT${BKP_DIR} $UBI_MNT${BKP_DIR}_defaults");
    }
    
    exec("sync");
    exec("umount $UBI_MNT");
    if (is_dir($UBI_MNT)) rmdir($UBI_MNT);
    exec("ubidetach /dev/ubi_ctrl -m 4");
  }  
}

function remove(){
  global $UPDATE_DIR;
  exec("rm -rf $UPDATE_DIR/*; sync");
  backup_note();
  print("<b>NOTE</b>: All files have been removed from <b>$UPDATE_DIR</b>.");
}

$cmd = "donothing";
if (isset($_GET['cmd']))
  $cmd = $_GET['cmd'];
else if (isset($argv[1]))
  $cmd = $argv[1];

// default Content-Type is text/html - LibreJS (if turned on) can add extra tags: <html><head></head><body>response</body></html>
header("Content-Type: text/plain");
  
switch($cmd){
  case "flash":
    $flash_list = verify(false);
    backup();
    nandflash($flash_list);
    if (isset($_GET['restore'])) copy_backup();
    break;
  case "backup":
    backup();
    send_backup();
    break;
  case "restore":
  	copy_backup();
  	break;
  case "remove":
    remove();
    break;
  default:
    verify(true);
}

?>
