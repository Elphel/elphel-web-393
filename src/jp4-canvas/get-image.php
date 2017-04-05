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

header('Content-type:image/jpeg');
echo file_get_contents("http://$ip:$port/$rel");

die();

?> 
