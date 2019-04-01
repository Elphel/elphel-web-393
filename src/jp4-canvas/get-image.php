<?php
/**
 * @file get-image.php
 * @brief combat cross-origin
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

if (isset($_GET['port']))
    $port = $_GET['port'];
else
    die();

if (isset($_GET['rel']))
    $rel = $_GET['rel'];
else
    die();

if (isset($_GET['ip']))
    $ip = $_GET['ip'];
else
    $ip = "localhost";

/*
header("Location: http://{$_SERVER['HTTP_HOST']}:$port/$rel");
die();
*/

$port0 = 2323;
$pointers = elphel_get_circbuf_pointers(intval($port)-$port0,1);
$pointer = $pointers[count($pointers)-1]['circbuf_pointer'];

$contents = file_get_contents("http://$ip:$port/$rel");

$acao = "*";
$ct   = "image/jpeg"; 

// pass some headers from file_get_contents
// $http_response_header is auto populated
foreach($http_response_header as $h){
    $hv = explode(":",$h);
    if ($hv[0]=="Access-Control-Allow-Origin"){
        $acao = trim($hv[1]);
    }else if ($hv[0]=="Content-Type"){
        $ct   = trim($hv[1]);
    }
}

// allow CORS
header("Access-Control-Allow-Origin: $acao");
header("Content-Type: $ct");
echo $contents;
//echo file_get_contents("http://$ip:$port/$rel");

die();

?>
