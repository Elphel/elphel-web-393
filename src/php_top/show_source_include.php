<?php
/*!***************************************************************************
*! FILE NAME  : show_source.inc
*! DESCRIPTION: Outputs program source code if url has 'source' parameter.
*! Copyright (C) 2012 Elphel, Inc
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
*/
    if (array_key_exists('source',$_GET)) {
      $source=file_get_contents ($_SERVER['SCRIPT_FILENAME']);
      header("Content-Type: text/php");
      header("Content-Length: ".strlen($source)."\n");
      header("Pragma: no-cache\n");
      echo $source;
      exit(0);
    }

    if (array_key_exists('help',$_GET)) {
	if (function_exists('_help')) {
	    _help();
	    exit(0);
	}
    }

    if (count($_GET)==0) {
	if (function_exists('_usage')) {
	    _usage();
	    exit(0);
	}
    }


?>