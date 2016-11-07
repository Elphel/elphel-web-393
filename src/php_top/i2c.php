<?php
/*!***************************************************************************
*! FILE NAME  : i2c.php
*! DESCRIPTION: Provides single byte/short r/w from/to the i2c
*!              Setting per-slave protection mask,
*!              Synchronizing system clock with a "CMOS" one
*!              Reading from/writing to EEPROM memory
*! Copyright (C) 2008 Elphel, Inc
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
*!  $Log: i2c.php,v $
*!  Revision 1.7  2012/04/08 04:08:58  elphel
*!  bug fix in temperature measurement
*!
*!  Revision 1.6  2012/01/16 01:42:24  elphel
*!  comments
*!
*!  Revision 1.5  2011/12/22 05:35:11  elphel
*!  Added "slow" option (for slow i2c devices) and reading the sensor board temperature
*!
*!  Revision 1.4  2011/08/13 00:51:21  elphel
*!  support for "granddaughter" board ID eeprom
*!
*!  Revision 1.3  2011/01/18 17:38:34  dzhimiev
*!  1. added <len> record,  passed through 'length'
*!
*!  Revision 1.2  2010/08/10 21:11:40  elphel
*!  added EEPROM support of the 10359 and 10338D boards.
*!
*!  Revision 1.1.1.1  2008/11/27 20:04:03  elphel
*!
*!
*!  Revision 1.8  2008/04/24 20:32:44  elphel
*!  reverted, wrong edit
*!
*!  Revision 1.7  2008/04/24 18:19:50  elphel
*!  changed to absolute path to i2c.inc
*!
*!  Revision 1.6  2008/03/16 01:24:09  elphel
*!  Added i2c speed control interface
*!
*!  Revision 1.5  2008/03/15 23:05:18  elphel
*!  split i2c.php into i2c.php and i2c.inc (to be used from other scripts)
*!
*!  Revision 1.4  2008/03/15 18:38:21  elphel
*!  Implemented add-on boards EEPROM support
*!
*!  Revision 1.3  2008/02/20 07:41:42  elphel
*!  report error if the date is wrong
*!
*!  Revision 1.2  2008/02/13 00:30:01  elphel
*!  Added system time synchronization  with "CMOS" clock
*!
*!  Revision 1.1  2008/02/12 21:53:20  elphel
*!  Modified I2c to support multiple buses, added raw access (no address registers) and per-slave protection bitmasks
*!
*!
*/

// In NC393: buses 0..3 - sensors(old bus=0), 4 - aux (old bus=1), 5 - system i2c (TODO)
set_include_path(get_include_path() . PATH_SEPARATOR . '/www/pages/include');
require 'i2c_include.php';

$width = 0;
$bus = 0;
$adr = - 1;
$class = "";
$sa7offs = 0;
$data = "";
$nopars = false;
$raw = 0;
$wprot = - 1;
$rprot = - 1;
$rslt = "";
$cmd = "";
//
$model = "";
$rev = "";
$brand = "Elphel";
$serial = "";
$time = "";
$port = "";
$part = "";
$baud = "";
$sync = "";
$slowbus0 = - 1; // extra microseconds for SCL low and SCL high
$EEPROM_bus0 = - 1;
$EEPROM_chn = 0; // / 0 - 10359/single 10338D, 1..3 - 10338D attached to the 10359
$application = '';
$applicationMode = '';
$sensor = '';
$temperatureSA = 0x30;
$temperarureDelay = 1.0; // extra microseconds for SCL low/high (nominal 4, but works with 1)
$msg = "<?xml version=\"1.0\"?>\n<i2c>\n";
if (count ( $_GET ) == 0) {
	$nopars = true;
} else {
	$pars = array ();
	foreach ( $_GET as $key => $value ) {
		switch ($key) {
			case "slow" :
			case "slowbus0" :
				$slowbus0 = $value + 0;
				break;
			case "cmd" :
				switch ($value) {
					case "fromCMOS" :
						// TODO: NC393: implement
						// $rslt=i2c_getCMOSClock()?"Set system time error (probably CMOS clock is not set)":("System clock is set to ".exec("date"));
						break;
					case "toCMOS" :
						$rslt = i2c_setCMOSClock ();
						break;
					case "fromEEPROM" :
					case "toEEPROM" :
					case "fromEEPROM0" :
					case "toEEPROM0" :
					case "fromEEPROM1" :
					case "toEEPROM1" :
					case "fromEEPROM2" :
					case "toEEPROM2" :
					case "fromEEPROM3" :
					case "toEEPROM3" :
					case "fromEEPROM4" :
					case "toEEPROM4" :
					case "fromEEPROM5" :
					case "toEEPROM5" :
					case "ctl" :
					$cmd = $value;
					break;
				}
				break;
			case "EEPROM_chn" :
				$EEPROM_chn = $value;
				break;
			case "app" :
			case "application" :
				$application = $value;
				break;
			case "mode" :
			case "application_mode" :
				$application_mode = $value;
				break;
			case "sensor" :
				$sensor = $value;
				break;
			case "port" :
				$port = $value;
				break;
			case "part" :
				$part = $value;
				break;
			case "baud" :
				$baud = $value;
				break;
			case "sync" :
				$sync = $value;
				break;
			case "model" :
				$model = $value;
				break;
			case "rev" :
				$rev = $value;
				break;
			case "serial" :
				$serial = $value;
				break;
			case "time" : // seconds from 1970
				$time = $value + 0;
				break;
			case "raw" :
				$adr = ($value + 0) & ~ 0x7f;
				$raw = 1;
				$width = 8;
				break;
			case "width" :
				$width = $value + 0;
				break;
			case "bus" :
				$bus = $value + 0;
				break;
			case "adr" :
				$adr = $value + 0;
				break;
			case "wp" :
				if ($value == 0)
					$wprot = 0;
				else if ($value == 1)
					$wprot = 1;
				break;
			case "rp" :
				if ($value == 0)
					$rprot = 0;
				else if ($value == 1)
					$rprot = 1;
				break;
			case "data" :
				$data = $value;
				break;
			case "length" :
				$length = $value + 0;
				break;
			case "class" :
				$class = $value;
				break;
			case "sa7offs" :
				$sa7offs = $value + 0;
				break;
		}
	}
}

switch ($cmd) {
	case "fromEEPROM0" :
	case "fromEEPROM1" :
	case "fromEEPROM2" :
	case "fromEEPROM3" :
	case "fromEEPROM4" :
	case "fromEEPROM5" :
		$EEPROM_bus0 = intval ( substr ( $cmd, 10 ) ); // and fall below
	case "fromEEPROM" :
		if (($EEPROM_bus0 == 4) || ($EEPROM_bus0 == 5)) { // using FPGA PIO bus (bus=1 in NC353) for IMU, GPS and such, bus = 5 - 10389 eeprom
			$rslt=i2c_read256b(0xa0+($EEPROM_chn * 2), $EEPROM_bus0);
			if (!is_string($rslt)){
				break; // will report error 
			}
			$zero=strpos($rslt,chr(0));
			if ($zero!==false) $rslt=substr($rslt,0, $zero);
			if (substr($rslt,0,5)=="<?xml") {
				$xml=simplexml_load_string($rslt);
				$xml_string=$xml->asXML();
				header("Content-Type: text/xml");
				header("Content-Length: ".strlen($xml_string)."\n");
				header("Pragma: no-cache\n");
				printf($xml_string);
				exit (0);
			}
			break;
		}
		if (($EEPROM_bus0 < 0) || ($EEPROM_bus0 > 3)) {
			// Not supported in NC393 - TODO: implement with bus=5 for 10389 board serial number
			break;
		}
		if (($EEPROM_chn < 0) || ($EEPROM_chn > 4)) {
			// Not supported in NC393
			break;
		}
		
		// print(i2c_read256b_sensor('sensor_eeprom',3,0));
		// $rslt=i2c_read256b(0xa0+($EEPROM_chn*($EEPROM_bus0?4:2)), $EEPROM_bus0?0:1);
		$rslt = i2c_read256b ( 'sensor_eeprom', $EEPROM_bus0, 2 * $EEPROM_chn );
		$zero = strpos ( $rslt, chr ( 0 ) );
		if ($zero !== false)
			$rslt = substr ( $rslt, 0, $zero );
		if (substr ( $rslt, 0, 5 ) == "<?xml") {
			$xml = simplexml_load_string ( $rslt );
			// $temperatureSA=0x30;
			// $temperarureDelay=1.0; // extra microseconds for SCL low/high (nominal 4, but works with 1)
			// try reading sensor temperature (if available)
			if ($EEPROM_bus0 >= 0) {
				$data = i2c_receive_sensor ( 'sensor_temp', $EEPROM_bus0, 5, 2 * $EEPROM_chn );
				$bus = 0;
				// $sa=$temperatureSA+($EEPROM_chn*($EEPROM_bus0?4:2));
				// $data=i2c_receive_slow(16,$bus,$sa*128+5,0,$temperarureDelay);
				if (($data >= 0) && ($data!=0xffff)) { // <0 - i2c error - not implemented
					$data = (($data & 0x1000) ? - 1 : 1) * ($data & 0xfff) / 16.0;
					$xml->addChild ( 'sensorTemperature', $data );
					// i2c_send_slow(8,$bus,$sa*128+8,3,0,$temperarureDelay); // set slow conversion, 1/16C resolution - for the next time
					i2c_send_sensor ( 'sensor_temp', $EEPROM_bus0, 8, 3, 2 * $EEPROM_chn );
				}
			}
			$xml_string = $xml->asXML ();
			header ( "Content-Type: text/xml" );
			header ( "Content-Length: " . strlen ( $xml_string ) . "\n" );
			header ( "Pragma: no-cache\n" );
			printf ( $xml_string );
			exit ( 0 );
		}
		
		break;
	case "toEEPROM0" :
	case "toEEPROM1" :
	case "toEEPROM2" :
	case "toEEPROM3" :
	case "toEEPROM4" :
	case "toEEPROM5" :
		$EEPROM_bus0 = intval ( substr ( $cmd, 8 ) ); // and fall below
	case "toEEPROM" :
//		echo "<!--  EEPROM_bus0=". $EEPROM_bus0." EEPROM_chn=".$EEPROM_chn."-->";
		if (($EEPROM_bus0 == 4) || ($EEPROM_bus0 == 5)) { // using FPGA PIO bus (bus=1 in NC353) for IMU, GPS and such
			if ($wprot>=0) {
				i2c_setprot ($EEPROM_bus0, 0xa0+($EEPROM_chn*2),1,(1-$wprot));
			}
			if ($data=="") {
				if ($model  === "") {$rslt="model  not specified"; break;}
				if ($rev    === "") {$rslt="rev    not specified"; break;}
				if ($serial === "") {$rslt="serial not specified"; break;}
				if ($time   === "") {$rslt="time   not specified"; break;}
				$xml = new SimpleXMLElement("<?xml version='1.0' standalone='yes'?><board/>");
				if ($brand!='') $xml->addChild ('brand',$brand);
				if ($model!='') $xml->addChild ('model',$model);
				if ($rev!='') $xml->addChild ('rev',  $rev);
				if ($serial!='') $xml->addChild ('serial',$serial);
				if ($time!='') $xml->addChild ('time',$time);
				if ($application!="") $xml->addChild ('app',$application);
				if ($application_mode!="") $xml->addChild ('mode',$application_mode);
				if ($sensor!='') $xml->addChild ('sensor',$sensor);
				if ($length!='') $xml->addChild ('len',$length);
				if ($port!='') $xml->addChild ('port',$port);
				if ($part!='') $xml->addChild ('part',$part);
				if ($baud!='') $xml->addChild ('baud',$baud);
				if ($sync!='') $xml->addChild ('sync',$sync);
				$data=$xml->asXML();
			}
			if (strlen($data)>256) {
				$rslt="data too long - ".strlen($data)." bytes, only 256 are permitted";
				break;
			}
			$rslt="written ".i2c_write256b($data,0xa0+($EEPROM_chn*2), $EEPROM_bus0);
			i2c_setprot ($EEPROM_bus0,0xa0+($EEPROM_chn*2),1,0);
			break;
		}
		if (($EEPROM_bus0 < 0) || ($EEPROM_bus0 > 3)) {
			// Not supported in NC393 - TODO: implement with bus=5 for 10389 board serial number
			break;
		}
		if (($EEPROM_chn < 0) || ($EEPROM_chn > 4)) {
			// Not supported in NC393
			break;
		}
		
		if ($wprot >= 0) {
			// not implemented in NC393?
			// i2c_setprot ($EEPROM_bus0?0:1,0xa0+($EEPROM_chn*($EEPROM_bus0?4:2)),1,(1-$wprot));
		}
		if ($data == "") {
			if ($model === "") {
				$rslt = "model  not specified";
				break;
			}
			if ($rev === "") {
				$rslt = "rev    not specified";
				break;
			}
			if ($serial === "") {
				$rslt = "serial not specified";
				break;
			}
			if ($time === "") {
				$rslt = "time   not specified";
				break;
			}
			$xml = new SimpleXMLElement ( "<?xml version='1.0' standalone='yes'?><board/>" );
			if ($brand != '')
				$xml->addChild ( 'brand', $brand );
			if ($model != '')
				$xml->addChild ( 'model', $model );
			if ($rev != '')
				$xml->addChild ( 'rev', $rev );
			if ($serial != '')
				$xml->addChild ( 'serial', $serial );
			if ($time != '')
				$xml->addChild ( 'time', $time );
			if ($application != "")
				$xml->addChild ( 'app', $application );
			if ($application_mode != "")
				$xml->addChild ( 'mode', $application_mode );
			if ($sensor != '')
				$xml->addChild ( 'sensor', $sensor );
			if ($length != '')
				$xml->addChild ( 'len', $length );
			if ($port != '')
				$xml->addChild ( 'port', $port );
			if ($part != '')
				$xml->addChild ( 'part', $part );
			if ($baud != '')
				$xml->addChild ( 'baud', $baud );
			if ($sync != '')
				$xml->addChild ( 'sync', $sync );
			$data = $xml->asXML ();
		}
		if (strlen ( $data ) > 256) {
			$rslt = "data too long - " . strlen ( $data ) . " bytes, only 256 are permitted";
			break;
		}
		// $rslt="written ".i2c_write256b($data,0xa0+($EEPROM_chn*($EEPROM_bus0?4:2)),$EEPROM_bus0?0:1);
		$rslt = "written " . i2c_write256b_sensor ( $data, 'sensor_eeprom', $EEPROM_bus0, 2 * $EEPROM_chn );
		
		// i2c_setprot ($EEPROM_bus0?0:1,0xa0+($EEPROM_chn*($EEPROM_bus0?4:2)),1,0);
		break;
	case "ctl" :
		if ($bus === "") {
			$rslt = "i2c bus number is not specified";
			break;
		}
		$rslt = i2c_ctl ( $bus, $data )->asXML ();
		header ( "Content-Type: text/xml" );
		header ( "Content-Length: " . strlen ( $rslt ) . "\n" );
		header ( "Pragma: no-cache\n" );
		printf ( $rslt );
		exit ( 0 );
}
if ($rslt == "") {
//	$msg .= "<width1>" . $width . "</width1>\n";
//	$msg .= "<bus1>" . $bus . "</bus1>\n";
//	$msg .= "<slave1>" . (sprintf ( "0x%x", ($adr >> (($width == 8) ? 7 : 8)) & 0xfe )) . "</slave1>\n";
	
	if ($bus > 3) {
		if (($adr >= 0) && (($width == 8) || ($width == 16))) {
			$slave = ($adr >> (($width == 16) ? 8 : 7)) & 0xfe;
			if ($wprot >= 0) {
				// i2c_setprot ($bus,$slave,1,(1-$wprot));
			}
			if ($rprot >= 0) {
				// i2c_setprot ($bus,$slave,0,(1-$rprot));
			}
			$msg .= "<width>" . $width . "</width>\n";
			$msg .= "<bus>" . $bus . "</bus>\n";
			$msg .= "<slave>" . (sprintf ( "0x%x", ($adr >> (($width == 8) ? 7 : 8)) & 0xfe )) . "</slave>\n";
			
			if (! $raw) {
				$msg .= "<adr>" . $adr . "</adr>\n";
				$msg .= "<hex_adr>" . sprintf ( "0x%x", $adr ) . "</hex_adr>\n";
			}
			if ($data != "") { // ! i2c write
				$data += 0;
				$msg .= "<data>" . $data . "</data>\n";
				$msg .= "<wdata>" . $data . "</wdata>\n";
				$msg .= "<hex_data>" . sprintf ( "0x%x", $data ) . "</hex_data>\n";
				$rslt = ($slowbus0 >= 0) ? i2c_send_slow ( $width, $bus, $adr, $data, $raw, $slowbus0 ) : i2c_send ( $width, $bus, $adr, $data, $raw );
				;
				if (! ($rslt > 0))
					$msg .= "<error> \"i2c write error (" . $rslt . ")\"</error>\n";
			} else { // !i2c read
				$data = ($slowbus0 >= 0) ? i2c_receive_slow ( $width, $bus, $adr, $raw, $slowbus0 ) : i2c_receive ( $width, $bus, $adr, $raw );
				$msg .= "<data>" . $data . "</data>\n";
				$msg .= "<rdata>" . $data . "</rdata>\n";
				$msg .= "<hex_data>" . sprintf ( "0x%x", $data ) . "</hex_data>\n";
				if ($data == - 1)
					$msg .= "<error> \"i2c read error\"</error>\n";
			}
		} else {
			if (! $nopars)
				$msg .= "<error>\"Address (adr or raw) or width are not specified or are not positive.\"</error>\n";
			$msg .= "<usage>\"open URL: i2c.php?width&#061;www&amp;bus&#061;bbb&amp;adr&#061;aaa&amp;data&#061;ddd\"</usage>\n";
		}
	} else {
		if ((strlen($class)>0) || ($adr >= 0)) {
			$msg .= "<class>" . $class . "</class>\n";
			$msg .= "<bus>" . $bus . "</bus>\n";
			$msg .= "<sa7offs>" . sprintf ( "0x%x", $sa7offs) . "</sa7offs>\n";
			$msg .= "<adr>" . $adr . "</adr>\n";
			$msg .= "<hex_adr>" . sprintf ( "0x%x", $adr ) . "</hex_adr>\n";
			if ($data != "") { // ! i2c write
				$data += 0;
				$msg .= "<data>" . $data . "</data>\n";
				$msg .= "<wdata>" . $data . "</wdata>\n";
				$msg .= "<hex_data>" . sprintf ( "0x%x", $data ) . "</hex_data>\n";
				$rslt = i2c_send_sensor($class, $bus, $adr, $data, $sa7offs);
				if (! ($rslt > 0))
					$msg .= "<error> \"i2c write error (" . $rslt . ")\"</error>\n";
			} else { // !i2c read
			$data = i2c_receive_sensor($class, $bus, $adr, $sa7offs);
				$msg .= "<data>" . $data . "</data>\n";
				$msg .= "<rdata>" . $data . "</rdata>\n";
				$msg .= "<hex_data>" . sprintf ( "0x%x", $data ) . "</hex_data>\n";
				if ($data == - 1)
					$msg .= "<error> \"i2c read error\"</error>\n";
			}
		} else {
			if (! $nopars)
				$msg .= "<error>\"Device class name and/or register address are not specified address is not positive.\"</error>\n";
				$msg .= "<usage>\"open URL: i2c.php?class&#061;device_class_name&amp;bus&#061;sensor_port&amp;adr&#061;register_address&amp;data&#061;data_to_write&amp;sa7offs&#061;add_to_slave_address\"</usage>\n";
		}
	}
} else {
	$msg .= "<result>\"" . $rslt . "\"</result>\n";
}

$msg .= "</i2c>\n";
header ( "Content-Type: text/xml" );
header ( "Content-Length: " . strlen ( $msg ) . "\n" );
header ( "Pragma: no-cache\n" );
printf ( $msg );
?>
