<?php
/**
 * @file snapshot.php
 * @brief snapshot
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
<?php

  $port0 = 2323;
  $path = "/sys/devices/soc0/elphel393-detect_sensors@0";
  $available_ports = Array();
  
  $trig_master = -1;
  $trig_master_port = -1;
  
  for($i=0;$i<4;$i++){
      
      $sensor = $path."/sensor{$i}0";
      if (is_file($sensor)){
          $c = trim(file_get_contents($sensor));
          if ($c!="none"){
              array_push($available_ports,$port0+$i);
          }
      }

  }

  // get TRIG_MASTER from lowest port
  if(!empty($available_ports)){
    $trig_master = intval(elphel_get_P_value($available_ports[0]-$port0,ELPHEL_TRIG_MASTER));
    $trig_master_port = $trig_master + $port0;
  }
  
  if ($trig_master>=0){
    if (isset($_GET['trig'])){
    
      // just in case one wants to override master
      if (isset($_GET['port'])){
        $trig_master_port = $_GET['port'];
      }
    
      $f = fopen("http://{$_SERVER['SERVER_ADDR']}:$trig_master_port/trig/pointers", 'r');
      fclose($f);
      die("trigger ok: http://{$_SERVER['SERVER_ADDR']}:$trig_master_port/trig/pointers");
      
    }
  }

?>
<!DOCTYPE HTML>
<html lang="en">
  <head>
    <meta charset="utf-8"/>
    <title>Snapshot</title>
    
    <script type='text/javascript' src='snapshot.js'></script>
    <script type='text/javascript' src='../js/jquery-3.1.1.js'></script>
    
    <style>
    
        body {
            font-family: "Helvetica Neue", Helvetica;
        }
    
        .button{
            font-weight: bold;
            border-radius:3px;
            outline:none;
            border: none;
            color: white;
            text-align: center;
            text-decoration: none;
            display: inline-block;
        }
    
        #snapshot{
            background-color: #CF4040; /* not Green */
            padding: 32px 32px;
            font-size: 20px;
        }

        #snapshot:hover{
            background-color: #BF4040; /* not Green */
        }

        #snapshot:active{
            background-color: #9F4040; /* not Green */
        }
        
        #snapshot:disabled{
            background-color: #A0A0A0; /* not Green */
        }        
        
        #synced{
            width:25px;
            height:25px;
        }
        
        #help_button{
            background-color: #404040; /* not Green */
            padding: 3px 7px;
            font-size: 15px;
        }
        
        #help_button:hover{
            background-color: #303030; /* not Green */
        }
        
        #help_button:active{
            background-color: #202020; /* not Green */
        }
        
        
    </style>

    <script>
        var ip = location.origin;
        var href = location.href;
        var ports = [<?php echo implode(",",$available_ports);?>];
        var trig_master = <?php echo $trig_master;?>;
        var trig_master_port = <?php echo $trig_master_port;?>;
    </script>
    
  </head>
  <body>
    <div>
      <button title='Download images from all channels over network' id='snapshot' onclick='take_snapshot()' class='button'>Snapshot</button>
    </div>
    <br/>
    <div>
      <table>
      <tr>
        <td valign='middle'><span style='font-size:20px;line-height:25px;'>sync</span></td>
        <td valign='middle'><input type='checkbox' id='synced' checked/></td>
        <td valign='middle'><button id='help_button' class='button' onclick='toggle_help()' >?</button></td>
      </tr>
      </table>
    </div>
    <br/>
    <div id='help' style='display:none;'>
      <b>if checked</b>: 
      <ul>
        <li>all ports - same timestamp</li>
        <li>fps will be reprogrammed - set to single trigger mode then restored - careful if some other program is doing recording</li>
      </ul>
      <b>if unchecked</b>:
      <ul>
        <li>timestamps can be different</li>
        <li>fps will not be reprogrammed - no intereference with other recording programs, only network load.</li>
      </ul>
    </div>
  </body>
</html>