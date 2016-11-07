<?php
/*!***************************************************************************
*! FILE NAME  : i2c.inc
*! DESCRIPTION: Provides functions to read/write over i2c buses in the camera,
*!              low-level R/W and additionally:
*!              Setting per-slave protection mask,
*!              Synchronizing system clock with a "CMOS" one
*!              Reading from/writing to EEPROM memory
*! Copyright (C) 2008-2016 Elphel, Inc
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
*!  $Log: i2c.inc,v $
*!  Revision 1.4  2012/01/12 19:09:28  elphel
*!  bug fix
*!
*!  Revision 1.3  2011/12/22 05:35:11  elphel
*!  Added "slow" option (for slow i2c devices) and reading the sensor board temperature
*!
*!  Revision 1.2  2010/08/10 21:11:40  elphel
*!  added EEPROM support of the 10359 and 10338D boards.
*!
*!  Revision 1.1.1.1  2008/11/27 20:04:03  elphel
*!
*!
*!  Revision 1.3  2008/10/04 16:09:04  elphel
*!  updating FPGA time with system time from CMOS
*!
*!  Revision 1.2  2008/06/04 20:26:23  elphel
*!  includes SMBus operations, needed to activate USB on the 10369A board
*!
*!  Revision 1.2  2008/03/16 01:24:09  elphel
*!  Added i2c speed control interface
*!
*!  Revision 1.1  2008/03/15 23:05:18  elphel
*!  split i2c.php into i2c.php and i2c.inc (to be used from other scripts)
*!
*!
*/
//$i2c_slow_safe=array(3,4,2,2,7,7); /// sensor i2c delays are 2,2,1,1,7,7
// In NC393: buses 0..3 - sensors(old bus=0), 4 - aux (old bus=1)
/// NC393-fucnctions to read/write sensor ports
/// Reusing NC53 parameters: $width->$name, $raw ->
function i2c_send_sensor($name,         ///< device class name (get all by "cat /sys/devices/soc0/elphel393-sensor-i2c@0/i2c_all")
		                 $sensor_port,  ///< sensor port :0..3
		                 $ra,           ///< register address
		                 $data,         ///< data to write
		                 $sa7_offset=0) ///< slave address offset when there are multiple devices (2*sub-channel in 10359)
		                                ///< return true on success, false on failure
{ //$a<0 - use raw read/write
	$path = '/sys/devices/soc0/elphel393-sensor-i2c@0/i2c'.strval($sensor_port);
	$rslt = file_put_contents ($path, $name.' ' . strval($sa7_offset) . ' ' . strval($ra) . ' ' . strval($data));
	return $rslt; 
}

function i2c_receive_sensor($name,         ///< device class name (get all by "cat /sys/devices/soc0/elphel393-sensor-i2c@0/i2c_all")
		                    $sensor_port,  ///< sensor port :0..3
		                    $ra,           ///< register address
		                    $sa7_offset=0) ///< slave address offset when there are multiple devices (2*sub-channel in 10359)
		                                   ///< @return read data or -1 on error
{
	// Initiate read
	$path = '/sys/devices/soc0/elphel393-sensor-i2c@0/i2c'.strval($sensor_port);
	$rslt = file_put_contents ($path, $name.' ' . strval($sa7_offset) . ' ' . strval($ra) );
	if (!$rslt)
		return -1;
	$rslt = file_get_contents ($path);
	if ($rslt === false)
		return -1;
	return intval($rslt);
}
/** Read 256 bytes from device as a string */
function i2c_read256b_sensor($name,         ///< device class name (get all by "cat /sys/devices/soc0/elphel393-sensor-i2c@0/i2c_all")
		                     $sensor_port,  ///< sensor port :0..3
		                     $sa7_offset=0) ///< slave address offset when there are multiple devices (2*sub-channel in 10359)
		                                    ///< @return read data string or partial (empty) string on error
{
	$rslt = '';
	for ($ra=0;$ra<256;$ra++){
		if (($d = i2c_receive_sensor($name,$sensor_port,$ra,$sa7_offset))<0) break;
		$rslt .= chr($d);
	}
	return $rslt;
}

/** Write string to device */
function i2c_write256b_sensor($data,         ///< Sring to write
							  $name,         ///< device class name (get all by "cat /sys/devices/soc0/elphel393-sensor-i2c@0/i2c_all")
							  $sensor_port,  ///< sensor port :0..3
							  $sa7_offset=0) ///< slave address offset when there are multiple devices (2*sub-channel in 10359)
											 ///< @return number of bytes written
{
	$len = 0;
	if (strlen($data)<256) $data.=chr(0);
	for ($ra=0;$ra<strlen($data);$ra++){
		$b = ord(substr($data,$ra,1));
		if (($d = i2c_send_sensor($name,$sensor_port,$ra,$b,$sa7_offset))<0) break;
		usleep(10000);
		$len ++;
	}
	return $len;
}

///sys/devices/soc0/elphel393-sensor-i2c@0# echo "mt9p006 8 9" > i2c3; cat i2c3
/*
cy22393: 0x69 1 1 100 kHz
sensor_temp: 0x18 1 2 100 kHz
sensor_eeprom: 0x50 1 1 100 kHz
pca9500_eeprom: 0x50 1 1 100 kHz
el10359_32: 0x8 1 4 500 kHz
el10359: 0x8 1 2 500 kHz
mt9p006: 0x48 1 2 500 kHz
mt9f002: 0x10 2 2 500 kHz
 */
/** Convert nc353 addres/width to sa7 and register address */
function aw_to_sa7r($adr,   ///< composite address, nc353 style (256 bytes for each byte-wide device, 512 bytes for each 16-bit one)
		$width  ///< Data with 16 or 8
		)                   ///< @return: array(sa7,ra)
{
	$sa7 = ($adr >> (($width == 16) ? 9 : 8)) & 0x7f;
	$ra =  ($adr >> (($width == 16) ? 1 : 0)) & 0xff;
	return array(sa7,ra);

}

function getSlowArray($usec=0){
  if ($usec<0) $usec=0;
  $SCL_low=3+(int) ($usec*8);
  $SCL_high=4+(int) ($usec*8);
  if ($SCL_low>254) $SCL_low=254;
  if ($SCL_high>254) $SCL_high=254;
  $i2c_slow_safe=array($SCL_low ,$SCL_high,2,2,7,7); /// sensor i2c delays are 2,2,1,1,7,7
  return $i2c_slow_safe;
}
///Map i2c bus of 393 to "old" one
function i2c_bus353($bus){
	if ($bus<4) return 0;
	else return $bus-3;
}
function i2c_ctl($bus,$data="") { //!$data - string of decimal values separated by ":", empty - don't change
   $size=6; // bytes per bus
   $data=trim($data);
   if ($data!="") {
     $darr=explode(":",$data);
     $i2cctl  = fopen('/dev/xi2cctl', 'w');
     for ($i=0; ($i<$size) && ($i<count($darr)); $i++) if ($darr[$i]!=""){
       fseek ($i2cctl, i2c_bus353($bus)*$size+$i) ;
       fwrite($i2cctl, chr($darr[$i]+0), 1);
     }
     fclose($i2cctl);
   }
   $i2cctl  = fopen('/dev/xi2cctl', 'r');
   fseek ($i2cctl, i2c_bus353($bus)*$size) ;
   $data = fread($i2cctl, $size);
   fclose($i2cctl);
   $xml = new SimpleXMLElement("<?xml version='1.0'?><i2cctl/>");
           $xml->addChild ('scl_high',ord($data[0]));
           $xml->addChild ('scl_low', ord($data[1]));
           $xml->addChild ('slave2master', ord($data[2]));
           $xml->addChild ('master2slave', ord($data[3]));
           $xml->addChild ('filter_sda',   ord($data[4]));
           $xml->addChild ('filter_scl',   ord($data[5]));
//           $data=$xml->asXML();
   return $xml;

} // end of i2c_ctl()

function i2c_ctl_arr($bus,$darr=array()) { //!$data - 6-element array
   $size=6; // bytes per bus
   if (count($darr)>00) {
     $i2cctl  = fopen('/dev/xi2cctl', 'w');
     for ($i=0; ($i<$size) && ($i<count($darr)); $i++) if ($darr[$i]!=""){
       fseek ($i2cctl, i2c_bus353($bus)*$size+$i) ;
       fwrite($i2cctl, chr($darr[$i]+0), 1);
     }
     fclose($i2cctl);
   }
   $i2cctl  = fopen('/dev/xi2cctl', 'r');
   fseek ($i2cctl, i2c_bus353($bus)*$size) ;
   $data = fread($i2cctl, $size);
   fclose($i2cctl);
   for ($i=0;$i<$size; $i++) $darr[$i]=ord($data[$i]);
   return $darr;
} // end of i2c_ctl()


function i2c_send($width, $bus, $a, $d, $raw = 0) { // $a<0 - use raw read/write
	if ($bus < 4)
		return i2c_send_sensor ( $width, $bus, $a, $d, $raw );
	else
		$bus = i2c_bus353 ( $bus );
	if ($bus == 2) { // System i2c in nc393 (was 5)
		$return = -1;
		$w = ($width == 16) ?'w' : 'b';
		$sa7r = aw_to_sa7r($a,$width); // works for raw also, $width is 8 for raw
		if ($raw){
			exec ( 'i2cset -y 0 '.$sa7r[0].' '.$d,                     $i2c_data, $return );
		} else {
			exec ( 'i2cget -y 0 '.$sa7r[0].' '.$sa7r[1].' '.$d.' '.$w, $i2c_data, $return );
		}
		if ($return != 0) return -1;
		return ($width == 16)?2:1;
	}
	
	$w = ($width == 16) ? 2 : 1;
	$i2c_fn = '/dev/xi2c' . ($raw ? 'raw' : (($w == 2) ? '16' : '8')) . (($bus == 0) ? '' : '_aux');
	$i2c = fopen ( $i2c_fn, 'w' );
	fseek ( $i2c, $w * $a );
	if ($w == 1)
		$res = fwrite ( $i2c, chr ( $d ) );
	else
		$res = fwrite ( $i2c, chr ( floor ( $d / 256 ) ) . chr ( $d - 256 * floor ( $d / 256 ) ) );
	fclose ( $i2c );
	return $res;
} // end of i2c_send()

// Seems no difference from i2c_send for nc393
function i2c_send_slow($width, $bus, $a, $d, $raw = 0, $extrausec = -1) { // $a<0 - use raw read/write
	if ($bus < 4)
		return i2c_send_sensor ( $width, $bus, $a, $d, $raw );
	else
		$bus = i2c_bus353 ( $bus );
	if (($bus == 0) && ($extrausec >= 0)) {
		$i2c_old_ctrl = i2c_ctl_arr ( 0 );
		i2c_ctl_arr ( 0, getSlowArray ( $extrausec ) );
	}
	if ($bus == 2) { // System i2c in nc393 (was 5)
		$return = -1;
		$w = ($width == 16) ?'w' : 'b';
		$sa7r = aw_to_sa7r($a,$width); // works for raw also, $width is 8 for raw
		if ($raw){
			exec ( 'i2cset -y 0 '.$sa7r[0].' '.$d,                     $i2c_data, $return );
		} else {
			exec ( 'i2cget -y 0 '.$sa7r[0].' '.$sa7r[1].' '.$d.' '.$w, $i2c_data, $return );
		}
		if ($return != 0) return -1;
		return ($width == 16)?2:1;
	}

	$w = ($width == 16) ? 2 : 1;
	$i2c_fn = '/dev/xi2c' . ($raw ? 'raw' : (($w == 2) ? '16' : '8')) . (($bus == 0) ? '' : '_aux');
	$i2c = fopen ( $i2c_fn, 'w' );
	fseek ( $i2c, $w * $a );
	if ($w == 1)
		$res = fwrite ( $i2c, chr ( $d ) );
	else
		$res = fwrite ( $i2c, chr ( floor ( $d / 256 ) ) . chr ( $d - 256 * floor ( $d / 256 ) ) );
	fclose ( $i2c );
	if (($bus == 0) && ($extrausec >= 0)) {
		i2c_ctl_arr ( 0, $i2c_old_ctrl ); // / restore old speed (not thread-safe)
	}
	return $res;
} // end of i2c_send()

function smbus_send($a, $d) { // d - array
	$i2c_fn = '/dev/xi2c8_aux';
	$i2c = fopen ( $i2c_fn, 'w' );
	fseek ( $i2c, $a );
	$cmd = chr ( count ( $d ) );
	foreach ( $d as $b )
		$cmd .= chr ( $b );
		// var_dump($cmd);
	$res = fwrite ( $i2c, $cmd );
	fclose ( $i2c );
	return $res;
} // end of i2c_send()


function i2c_receive($width, $bus, $a, $raw = 0) {
	if ($bus < 4)
		return i2c_receive_sensor ( $width, $bus, $a, $raw );
	else
		$bus = i2c_bus353 ( $bus );
	if ($bus == 2) { // System i2c in nc393 (was 5)
		$w = ($width == 16) ?'w' : 'b';
		$sa7r = aw_to_sa7r($a,$width); // works for raw also, $width is 8 for raw
		if ($raw){
			exec ( 'i2cget -y 0 '.$sa7r[0],                     $i2c_data, $return );
		} else {
			exec ( 'i2cget -y 0 '.$sa7r[0].' '.$sa7r[1].' '.$w, $i2c_data, $return );
		}
		if ($return != 0) return -1;			
		return $i2c_data[0];
	}
	$w = ($width == 16) ? 2 : 1;
	$i2c_fn = '/dev/xi2c' . ($raw ? 'raw' : (($w == 2) ? '16' : '8')) . (($bus == 0) ? '' : '_aux');
	$i2c = fopen ( $i2c_fn, 'r' );
	fseek ( $i2c, $w * $a );
	$data = fread ( $i2c, $w );
	fclose ( $i2c );
	if (strlen ( $data ) < $w)
		return - 1;
	$v = unpack ( ($w == 1) ? 'C' : 'n1', $data );
	return $v [1];
} // end of i2c_receive()

// i2c_receive_slow is the same as i2c_receive for nc393
function i2c_receive_slow($width, $bus, $a, $raw = 0, $extrausec = -1) {
	if ($bus < 4)
		return i2c_receive_sensor ( $width, $bus, $a, $raw );
	else
		$bus = i2c_bus353 ( $bus );
	if (($bus == 0) && ($extrausec >= 0)) {
		$i2c_old_ctrl = i2c_ctl_arr ( 0 );
		i2c_ctl_arr ( 0, getSlowArray ( $extrausec ) );
	}
	if ($bus == 2) { // System i2c in nc393 (was 5)
		$w = ($width == 16) ?'w' : 'b';
		$sa7r = aw_to_sa7r($a,$width); // works for raw also, $width is 8 for raw
		if ($raw){
			exec ( 'i2cget -y 0 '.$sa7r[0],                     $i2c_data, $return );
		} else {
			exec ( 'i2cget -y 0 '.$sa7r[0].' '.$sa7r[1].' '.$w, $i2c_data, $return );
		}
		if ($return != 0) return -1;
		return $i2c_data[0];
	}
	$w = ($width == 16) ? 2 : 1;
	$i2c_fn = '/dev/xi2c' . ($raw ? 'raw' : (($w == 2) ? '16' : '8')) . (($bus == 0) ? '' : '_aux');
	$i2c = fopen ( $i2c_fn, 'r' );
	fseek ( $i2c, $w * $a );
	$data = fread ( $i2c, $w );
	fclose ( $i2c );
	if (strlen ( $data ) < $w)
		return - 1;
	$v = unpack ( ($w == 1) ? 'C' : 'n1', $data );
	if ($bus == 0) {
		i2c_ctl_arr ( 0, $i2c_old_ctrl ); // / restore old speed (not thread-safe)
	}
	return $v [1];
} // end of i2c_receive()

function i2c_setprot($bus, $slave, $bit, $value) { // !slave is MSB aligned, LSB==0)
	if ($bus != 4)
		return -1; // applicable only to grand-daughter i2c (like imu, gps) 
	$bus = i2c_bus353 ( $bus );
	$i2cprot = fopen ("/dev/xi2cenable", 'r+' );
	
	fseek ( $i2cprot, ($bus * 128) + ($slave >> 1) );
	$data = ord ( fread ( $i2cprot, 1 ) );
	if ($value)
		$data |= (1 << $bit);
	else
		$data &= ~ (1 << $bit);
	fseek ( $i2cprot, ($bus * 128) + ($slave >> 1) );
	fwrite ( $i2cprot, chr ( $data ) );
	fclose ( $i2cprot );
} // end of i2c_setprot ()

function i2c_getCMOSClock() {
	echo "**** FUNCTION NOT SUPPORTED in NC393 *****";
	return - 1;
	$i2c = fopen ( '/dev/xi2c8_aux', 'r' );
	fseek ( $i2c, 0x5102 ); // seconds in clock on the 10369 board
	$data = fread ( $i2c, 7 ); // sec/min/hours/days/weekdays/century-months/years, BCD
	fclose ( $i2c );
	if (strlen ( $data ) < 7)
		return "error";
	$v = unpack ( 'C*', $data );
	$d = array (
			"sec" => ($v [1] & 0xf) + 10 * (($v [1] >> 4) & 0x7),
			"min" => ($v [2] & 0xf) + 10 * (($v [2] >> 4) & 0x7),
			"hrs" => ($v [3] & 0xf) + 10 * (($v [3] >> 4) & 0x3),
			"day" => ($v [4] & 0xf) + 10 * (($v [4] >> 4) & 0x3),
			"wday" => $v [5] & 0x7,
			"month" => ($v [6] & 0xf) + 10 * (($v [6] >> 4) & 0x1),
			"year" => ($v [7] & 0xf) + 10 * (($v [7] >> 4) & 0xf) + (($v [6] & 0x80) ? 2000 : 1900) 
	);
	exec ( "date -s " . sprintf ( "%02d%02d%02d%02d%04d.%02d", $d ["month"], $d ["day"], $d ["hrs"], $d ["min"], $d ["year"], $d ["sec"] ), $out, $ret );
	if ($ret == 0) {
		elphel_set_fpga_time ( time () + 0.0 );
	}
	return $ret;
} // end of i2c_getCMOSClock()

function i2c_bcd($v) {
  $d=intval($v,10);
  return $d + 6*floor($d/10);
} // end of i2c_bcd($v)

function i2c_setCMOSClock(){
 $ts=time();
 $data=chr(i2c_bcd(gmdate("s",$ts))).
       chr(i2c_bcd(gmdate("i",$ts))).
       chr(i2c_bcd(gmdate("H",$ts))).
       chr(i2c_bcd(gmdate("d",$ts))).
       chr(i2c_bcd(gmdate("w",$ts))).
       chr(i2c_bcd(gmdate("m",$ts))+ ((intval(gmdate("Y",$ts),10)>=2000)?0x80:0)).
       chr(i2c_bcd(gmdate("y",$ts)));
   $i2c  = fopen('/dev/xi2c8_aux', 'w');
   fseek ($i2c, 0x5102) ; //seconds in clock on the 10369 board
   $written= fwrite($i2c, $data);
   fclose($i2c);
   if ($written < strlen($data)) return "error";
   return "OK";
} // end of i2c_setCMOSClock()

function i2c_read256b($slave = 0xa0, $bus = 4, $extrausec = 0) { // will read 256 bytes from slave (address is 8-bit, includes r/~w)
	if ($bus < 4)
		return i2c_read256b_sensor ( $slave, $bus, $extrausec ); // ($name, $sensor_port, $sa7_offset)
	else
		$bus = i2c_bus353 ( $bus );
	if (($bus == 0) && ($extrausec >= 0)) {
		$i2c_old_ctrl = i2c_ctl_arr ( 0 );
		i2c_ctl_arr ( 0, getSlowArray ( $extrausec ) );
	}
	if ($bus == 2) { // System i2c in nc393 (was 5)
		$sa7 = $slave >> 1;
		/* // very slow
		for($i = 0; $i < 256; $i ++) {
			exec ( 'i2cget -y 0 ' . $sa7 . ' ' . $i . ' b', $i2c_data, $return );
			if ($return != 0)
				return - 1;
			}
		$data == "";
		foreach ($i2c_data as $c) $data.=chr($c);
		*/
		exec('i2cdump -y 0 ' . $sa7 . ' b', $i2c_data, $return );
		if ($return != 0) return - 1;
		// Extract data from dump table
		$line="";
		for ($j=0; $j<16; $j++) $line .= substr($i2c_data[$j+1],4,48);
		$data == "";
		foreach (explode(' ',$line) as $e) $data.=chr(intval($e,16));
		return $data;
	}
	$i2c_fn = '/dev/xi2c8' . (($bus == 0) ? '' : '_aux');
	$i2c = fopen ( $i2c_fn, 'r' );
	fseek ( $i2c, $slave * 128 ); // 256 per slave, but slave are only even
	$data = fread ( $i2c, 256 ); // full 256 bytes
	fclose ( $i2c );
	if (($bus == 0) && ($extrausec >= 0)) {
		i2c_ctl_arr ( 0, $i2c_old_ctrl ); // / restore old speed (not thread-safe)
	}
	return $data;
} // end of i2c_read256b ()
//this EEPROM writes only 4 bytes sequentionally (only 2 LSBs are incremented)

function i2c_write256b($data, $slave = 0xa0, $bus = 4, $extrausec = 0) { // will write up to 256 bytes $data to slave (address is 8-bit, includes r/~w). EEPROM should be un-protected
	if ($bus < 4)
		return i2c_write256b_sensor ( $data, $slave, $bus, $extrausec ); // ($data, $name, $sensor_port, $sa7_offset)
	else
		$bus = i2c_bus353 ( $bus );
	if (($bus == 0) && ($extrausec >= 0)) {
		$i2c_old_ctrl = i2c_ctl_arr ( 0 );
		i2c_ctl_arr ( 0, getSlowArray ( $extrausec ) );
	}
	$maxretries = 200; // measured - 19
	$len = 0;
	if (! is_string ( $data ))
		return - 1;
	if (strlen ( $data ) > 256)
		return - 2;
	if (strlen ( $data ) < 256)
		$data .= chr ( 0 );

	if ($bus == 2) { // System i2c in nc393 (was 5)
		$sa7 = $slave >> 1;
		foreach (str_split($data) as $d) {
			exec ( 'i2cset -y 0 ' . $sa7 . ' ' .$len . ' ' . ord($d) . ' b', $i2c_data, $return );
			if ($return != 0)
				return - 1;
			usleep ( 10000 );
			$len ++;
		}
		return $len;
	}
//foreach (str_split($data) as $d) echo ord($d);		
	$i2c_fn = '/dev/xi2c8' . (($bus == 0) ? '' : '_aux');
	$i2c = fopen ( $i2c_fn, 'w' );
	for($i = 0; $i < strlen ( $data ); $i += 4) {
		for($retry = 0; $retry < $maxretries; $retry ++) {
			fseek ( $i2c, $slave * 128 + $i ); // 256 per slave, but slave are only even
			$rslt = fwrite ( $i2c, substr ( $data, $i, 4 ) );
			if ($rslt > 0)
				break;
		}
		if ($rslt <= 0) {
			$len = $rslt;
			break;
		}
		$len += $rslt;
	}
	fclose ( $i2c );
	if (($bus == 0) && ($extrausec >= 0)) {
		i2c_ctl_arr ( 0, $i2c_old_ctrl ); // / restore old speed (not thread-safe)
	}
	return $len;
} // end of i2c_write256b ()
?>
