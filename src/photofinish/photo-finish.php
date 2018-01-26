<?php
/**
 * @file photo-finish.php
 * @brief -
 * @copyright Copyright (C) 2018 Elphel Inc.
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

$help = <<<HELP
Description:
  This scripts sets up a photo finish mode. Will work for port 0, to change
  the port - edit this script.
HELP;

if (isset($argv[1])){
  $_GET['cmd'] = $argv[1];
}

if (isset($_GET['cmd'])){
  $cmd = $_GET['cmd'];
}else{
  $cmd = "donothing";
}

$sensor_port = 0;
$master_port = 0;

// $pars_init_X order is important,
// photo finish is a sensitive mode

// reset
$pars_init_0 = array(
  'WB_EN' => 0,
  'AUTOEXP_ON' => 0,
  'TRIG' => 0,
  'EXPOS' => 300,
  'COMPRESSOR_RUN' => 0
);

// set linescan mode
$pars_init_1 = array(
  'COLOR' => 5,
  'TRIG' => 4,
  'TRIG_PERIOD' => 50000, // 2000fps
  'PF_HEIGHT' => 2,
  'WOI_HEIGHT' => 16 // for faster work
);

$pars_init_2 = array(
  'WOI_HEIGHT' => 8000, // equals to 10 seconds for 400fps
  'WOI_TOP' => 968 // scan at the sensor's center
);

$pars_init_3 = array(
  'EXPOS' => 300,
  'TRIG_PERIOD' => 250000, // 400fps, 2xFPS should cover 1 second
  'COMPRESSOR_RUN' => 2
);

if ($cmd=="init"){

  $frame_num = elphel_get_frame($sensor_port);

  elphel_set_P_arr($sensor_port,$pars_init_0,$frame_num+3);
  elphel_set_P_arr($sensor_port,$pars_init_1,$frame_num+6);
  elphel_set_P_arr($sensor_port,$pars_init_2,$frame_num+9);
  elphel_set_P_arr($sensor_port,$pars_init_3,$frame_num+12);

  elphel_wait_frame_abs($sensor_port,$frame_num+12);

  echo "OK\n";

}

?>
