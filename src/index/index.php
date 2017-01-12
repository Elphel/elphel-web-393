<!doctype html>
<html lang="en">
<head>
  <title>Elphel 393</title>
  <meta charset="utf-8"/>
  <link rel="stylesheet" href="js/bootstrap/css/bootstrap.css">
  <style>
  .port_window{
    padding: 5px;
    background: rgba(240,240,240,0.5);
    border-radius: 2px;
  }
  .img_window{
    border: 1px solid rgba(210,210,210,1);
  }  
  
  table td {
    padding-right:10px;
  }
  </style>
</head>
<body>
<div style='padding:10px'>
  <?php
  
  $port0 = 2323;
  $path = "/sys/devices/soc0/elphel393-detect_sensors@0";
    
  $table_contents = "";
  $port_links = "";
    
  for($i=0;$i<4;$i++){
    $sensor = $path."/sensor{$i}0";
    if (is_file($sensor)){
      $c = trim(file_get_contents($sensor));
      if ($c!="none"){
        $href = "http://{$_SERVER["SERVER_ADDR"]}:".($port0+$i)."/bimg";
        $table_contents .= "<td>";
        $table_contents .= "<div class='port_window img_window'>";
        $table_contents .= "<div><a href=\"$href\"><img class='img_window' src='$href' style='width:300px'/></a></div>";
        $table_contents .= "<div style='text-align:center;'>port $i</div>";
        $table_contents .= "</div>";
        $table_contents .= "</td>";
        
        $port_links .= "<li><a href=\"#\" onclick=\"window.open('camvc.html?sensor_port=$i&reload=0', 'port 0','menubar=0, width=800, height=600, toolbar=0, location=0, personalbar=0, status=0, scrollbars=1')\">port $i</a></li>\n";
        
      }
    }
  }
  
  echo "<table><tr>$table_contents</tr></table>\n";
  
  echo "<br/>";
  
  echo "Camera Control Interface<ul>$port_links</ul>\n";
  
  ?>
  <br />
  <a title="autocampars.php" href="autocampars.php">Parameter Editor</a><br />
  <a title="camogmgui.php" href="camogmgui.php">Recorder</a><br />
  <a title="hwmon.html" href="hwmon.html">Temperature monitor</a><br />
  <br />
  <a title="docs" href="http://wiki.elphel.com/index.php?title=Tmp_manual">User manual</a><br />
  
</div>
<body>
</html>
