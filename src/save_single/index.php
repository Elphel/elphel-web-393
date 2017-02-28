<?php
/**
 * @file index.php
 * @brief index page
 * @copyright Copyright (C) 2017 Elphel Inc.
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
?>
<!doctype html>
<html lang="en">
<head>
  <title>Elphel 393</title>
  <meta charset="utf-8"/>
  <script src="../js/jquery-3.1.1.js"></script>
  <script src="save_single.js"></script>
  <link rel="stylesheet" href="save_single.css">
  <script>
<?php
  $port0 = 2323;
  $path = "/sys/devices/soc0/elphel393-detect_sensors@0";
  
  $available_ports = Array();
  
  for($i=0;$i<4;$i++){
    $sensor = $path."/sensor{$i}0";
    if (is_file($sensor)){
      $c = trim(file_get_contents($sensor));
      if ($c!="none"){
        array_push($available_ports,$port0+$i);
      }
    }
  }
  
  if (count($available_ports)!=0){
    echo "  var ports = [".implode(",",$available_ports)."];\n";
    //echo "  var ports = [2324];\n";
  }
  
?>
  </script>
</head>
<body>
  <div><button id='save'>SAVE</button></div>
</body>
</html>
