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
  <script>
  // for LibreJS:
  /**
  * @file index.php
  * @copyright Copyright (C) 2017 Elphel Inc.
  * @author -
  *
  * @licstart  The following is the entire license notice for the
  *  JavaScript code in this page.
  *
  *   The JavaScript code in this page is free software: you can
  *   redistribute it and/or modify it under the terms of the GNU
  *   General Public License (GNU GPL) as published by the Free Software
  *   Foundation, either version 3 of the License, or (at your option)
  *   any later version.  The code is distributed WITHOUT ANY WARRANTY;
  *   without even the implied warranty of MERCHANTABILITY or FITNESS
  *   FOR A PARTICULAR PURPOSE.  See the GNU GPL for more details.
  *
  *   As additional permission under GNU GPL version 3 section 7, you
  *   may distribute non-source (e.g., minimized or compacted) forms of
  *   that code without the copy of the GNU GPL normally required by
  *   section 4, provided you include this license notice and a URL
  *   through which recipients can access the Corresponding Source.
  *
  *  @licend  The above is the entire license notice
  *  for the JavaScript code in this page.
  */
  </script>
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
        $sandp = "http://{$_SERVER["SERVER_ADDR"]}:".($port0+$i);
        $href1 = "$sandp/bimg";
        $href2 = "$sandp/mimg";

        $table_contents .= "<td>";
        $table_contents .= "<div class='port_window img_window'>";
        $table_contents .= "<div><a href=\"$href1\"><img class='img_window' src='$href1' style='width:300px'/></a></div>";
        $table_contents .= "<div style='text-align:center;'>port $i: <a title='single image' href='$href1'>bimg</a>, <a title='multi-part image stream (M-JPEG)' href='$href2'>mimg</a></div>";
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
  <a href="autocampars.php" title="autocampars.php">Parameter Editor</a><br />
  <br />
  <a href="camogmgui.php"   title="Store video/images to the camera's storage">Recorder</a><br />
  <a href="snapshot/"       title="Take a snapshot and download from the camera">Snapshot</a><br />
  <a href="raw.php"         title="Take a snapshot and download raw pixel data from the camera">Snapshot (raw image data)</a><br />
  <a href="photofinish/"    title="Scanline mode demo">Photo finish demo</a><br />
  <br />
  <a href="hwmon.html"           title="hwmon.html">Temperature monitor</a><br />
  <a href="update_software.html" title="update NAND flash">Update firmware</a><br />
  <br />
  <a title="docs" href="http://wiki.elphel.com/index.php?title=Tmp_manual">User manual</a><br />
  <a href="jp4-viewer/?width=1200&quality=1" title="preview jp4 images (drag and drop from PC)">JP4 Viewer</a><br />
  <a href="/debugfs.html" title="Linux Kernel Dynamic Debug helper interface (debug device drivers)">DebugFS</a><br />
</div>
<body>
</html>
