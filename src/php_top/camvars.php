<?php
/*!*******************************************************************************
*! FILE NAME  : camvars.php
*! DESCRIPTION: read/write camera internal variables by name
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
*!  $Log: camvars.php,v $
*!  Revision 1.1.1.1  2008/11/27 20:04:03  elphel
*!
*!
*!  Revision 1.2  2008/11/22 05:58:24  elphel
*!  modifying to match elphel_set_P_value()
*!
*!  Revision 1.2  2008/04/17 22:37:57  elphel
*!  bug fix
*!
*!  Revision 1.1  2008/03/22 04:41:07  elphel
*!  Script to set/get camera parameters with HTTP GET (result as XML)
*!
*!
*/
   if (count($_GET)==0) {
      echo <<<USAGE
   <p>This script returns camera variables as XML file, it also allows you to set those variables. Usually those changes will not take effect immediately - please use ccam.php that both changes variables and programs the camera to use them.</p>
   <p>The variable names to be read are specified without values (like camvar.php?WOI_WIDTH&WOI_HEIGHT ), the ones to be written - with the values (camvar.php?QUALITY=75). It is also possible to mix both types in the same request.</p>
USAGE;
      exit (0);
    }
    $toRead=array();
    $toWrite=array();
    foreach($_GET as $key=>$value) {
      if ($value==="") $toRead[$key]=$value;
      else $toWrite[$key]=(integer) $value;
    }
    $npars=(count($toWrite)>0)?elphel_set_P_arr($toWrite):0;
    if (count($toRead)>0) $toRead=elphel_get_P_arr($toRead);
    if ($_GET["STATE"]!==NULL) $toRead["STATE"]=elphel_get_state();
    $xml = new SimpleXMLElement("<?xml version='1.0'  standalone='yes'?><camvars/>");
    foreach ($toRead as $key=>$value) {
       $xml->addChild ($key,$value);
    }
    if (count($toWrite)>0) {
       $xml->addChild ('frame',$npars);
    }
    $rslt=$xml->asXML();
    header("Content-Type: text/xml");
    header("Content-Length: ".strlen($rslt)."\n");
    header("Pragma: no-cache\n");
    printf($rslt);
?>
