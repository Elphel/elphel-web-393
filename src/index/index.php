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
    <script type='text/javascript' src='js/jquery-3.1.1.js'></script>
    <!--<script type='text/javascript' src='../js/bootstrap/js/bootstrap.js'></script>-->
    <script src="js/elphel.js"></script>
    <script src="js/jcanvas.js"></script>
    <script src="js/UTIF.js"></script>
    <script src="js/exif.js"></script>
    <script src="js/jquery-jp4.js"></script>

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
        .port_preview{
            width:300px;
            min-height:224px;
            overflow: auto;
        }
        table td {
            padding-right:10px;
        }
        .btn.active:focus, .btn:focus{
            outline:none;
        }
        .btn-toggle{
            padding: 1px 0px;
        }
        #aexp_limit,
        #exposure,
        #fps{
            width: 50px;
            text-align:right;
            margin-top: 1px;
        }

        #ll_status{
            color: rgba(0,150,0,1);
            opacity: 0;
        }

    </style>
</head>
<body>
<div style='padding:10px'>
<?php

  include "include/elphel_functions_include.php";

  $port0 = 2323;
  $table_contents = "";
  $port_links = "";

  $sample_port = -1;

  foreach(get_sensors() as $i => $sensor){
    if ($sensor!="none"){
        $sample_port = $i;

        $sandp = "http://{$_SERVER["SERVER_ADDR"]}:".($port0+$i);
        $href1 = "$sandp/bimg";
        $href2 = "$sandp/mimg";

        $table_contents .= "<td valign='top'>";
        $table_contents .= "<div class='port_window img_window'>";
        //$table_contents .= "<div><a href=\"$href1\"><img class='img_window' src='$href1' style='width:300px'/></a></div>";
        $table_contents .= "<div><a href=\"$href1\"><div index='$i' class='port_preview'></div></a></div>";
        $table_contents .= "<div style='text-align:center;'>port $i: <a title='single image' href='$href1'>bimg</a>, <a title='multi-part image stream (M-JPEG). Played in browser as is.' href='$href2'>mimg</a>, <a href=\"mjpeg.html?port=$i\" title='MJPEG stream played in html canvas' class='canvas_mjpeg'>canvas</a></div>";
        $table_contents .= "</div>";
        $table_contents .= "</td>";

        $port_links .= "<li><a href=\"#\" onclick=\"window.open('camvc.html?sensor_port=$i&reload=0', 'port $i','menubar=0, width=800, height=600, toolbar=0, location=0, personalbar=0, status=0, scrollbars=1')\">port $i</a></li>\n";
    }
  }

  // check awb of master channel
  $master_port = elphel_get_P_value($sample_port,ELPHEL_TRIG_MASTER);
  $awb_on = elphel_get_P_value($master_port,ELPHEL_WB_EN);
  $aexp_on = elphel_get_P_value($master_port,ELPHEL_AUTOEXP_ON);

  $aexp_max = elphel_get_P_value($master_port,ELPHEL_AUTOEXP_EXP_MAX);
  $aexp_max = round($aexp_max/100)/10;

  $trig_period = elphel_get_P_value($master_port,ELPHEL_TRIG_PERIOD);
  if ($trig_period!=1){ // single
    $trig_period_step_s = 0.00000001;
    $trig_period_s = $trig_period*$trig_period_step_s;
    $fps = round(1/$trig_period_s*10)/10;
  }else{
    $fps = 10;
  }

  $expos = elphel_get_P_value($master_port,ELPHEL_EXPOS);
  $expos = round($expos/100)/10;

  echo "<table><tr>$table_contents</tr></table>\n";

  echo "<br/>";

  echo "Camera Control Interface<ul>$port_links</ul>\n";

?>
    <table>
    <tr>
        <td>
            <span title='Frames Per Second. Set internal trigger per second. WARNING: Trigger can be programmed to be faster than real fps. In that case some triggers are ignored.'>FPS:</span>
        </td>
        <td><input id='fps' type='text' value='<?php echo $fps;?>' pname='TRIG_PERIOD' /></td>
    </tr>
    <tr>
        <td>
            <span title='Initial read from master port, applied to all ports'>Manual Exposure:</span>
        </td>
        <td><input id='exposure' type='text' value='<?php echo $expos;?>' pname='EXPOS'/> ms</td>
    </tr>
    </table>
    <br/>
    <table>
    <tr id="toggle_awb_div" title='Auto White Balance'>
        <td>
            Auto WB:
        </td>
        <td>
            <div id="toggle_awb" class="btn-group btn-toggle">
                <button class="btn btn-xs <?php echo  ($awb_on)?"btn-success active":"btn-default";?>">ON</button>
                <button class="btn btn-xs <?php echo (!$awb_on)?"btn-danger active":"btn-default";?>">OFF</button>
            </div>
        </td>
    </tr>
    <tr id="toggle_aexp_div" title='Auto Exposure'>
        <td>
            Auto Exposure:
        </td>
        <td>
            <div id="toggle_aexp" class="btn-group btn-toggle">
                <button class="btn btn-xs <?php echo  ($aexp_on)?"btn-success active":"btn-default";?>">ON</button>
                <button class="btn btn-xs <?php echo (!$aexp_on)?"btn-danger active":"btn-default";?>">OFF</button>
            </div>
        </td>
        <td>
            <span title="Auto Exposure Limit in ms">Limit:
        </td>
        <td><input id='aexp_limit' type='text' value='<?php echo $aexp_max;?>' pname='AUTOEXP_EXP_MAX'/> ms</td>
    </tr>
    </table>
    <br />
    <a href="autocampars.php" title="autocampars.php">Parameter Editor</a><br />
    <br />
    <a href="camogmgui.php"   title="Store video/images to the camera's storage">Recorder</a><br />
    <a href="snapshot/"       title="Take a snapshot and download from the camera">Snapshot</a><br />
    <a href="raw.php"         title="Take a snapshot and download raw pixel data from the camera">Snapshot (raw image data)</a><br />
    <a id="low_latency_link" href="#" title="For 5 MPix sensors">Quick low latency setup (1920x1088, 30fps)</a> <span id='ll_status'>settings applied</span><br />
    <a href="photofinish/"    title="Scanline mode demo">Photo finish demo</a><br />
    <br />
    <a href="hwmon.html"           title="hwmon.html">Temperature monitor</a><br />
    <a href="update_software.html" title="Update NAND flash">Update firmware</a><br />
    <br />
    <a title="docs" href="http://wiki.elphel.com/index.php?title=Tmp_manual">User manual</a><br />
    <a href="jp4-viewer/?width=1200&quality=1" title="Preview jp4 images (drag and drop from PC)">JP4 Viewer</a><br />
    <a href="/diagnostics/index.html" title="Inspect camera system info">System info</a><br />
    <a href="/test_sensors.php" title="Switch to test pattern and check md5sums">Test sensors</a><br />
    <a href="/debugfs.html" title="Linux Kernel Dynamic Debug helper interface (debug device drivers)">DebugFS</a><br />
</div>

<script>

    var jp4_previews_enable = true;
    var jp4_previews = [];

    $(function(){
        check_time();
        init_awb_toggle();
        init_aexp_toggle();
        init_jp4_previews();
        init_inputs();
        init_lowlatency();
        update_canvas_mjpeg();
    });

    async function check_time(){
        $.ajax({
            url: "utils.php?cmd=time&ts="+Date.now(),
            success: (res)=>{
                console.log(res);
            }
        });
    }

    function init_jp4_previews(){
        $('.port_preview').each(function(){
            index = parseInt($(this).attr("index"));
            if (jp4_previews_enable) {
                //jp4_previews[index] = $(this).jp4({ip:location.host,port:2323+index,width:300,fast:true,lowres:4});
                jp4_previews[index] = $(this).jp4({src:"http://"+location.host+":"+(2323+index)+"/img",width:300,fast:true,lowres:4});
            }else{
                $(this).html("<img width='300' src='http://"+location.host+":"+(2323+index)+"/img' />");
            }
        });
    }

    function init_awb_toggle(){
        $('#toggle_awb').click(function() {

            if ($(this).find('.btn.active').html()=="ON"){
                $(this).find('.btn.active').toggleClass('btn-success');
            }else{
                $(this).find('.btn.active').toggleClass('btn-danger');
            }

            // toggle active
            $(this).find('.btn').toggleClass('active');

            if ($(this).find('.btn.active').html()=="ON"){
                wb_en = 1;
                $(this).find('.btn.active').toggleClass('btn-success');
            }else{
                wb_en = 0;
                $(this).find('.btn.active').toggleClass('btn-danger');
            }

            $(this).find('.btn').toggleClass('btn-default');

            url = "parsedit.php?immediate&sensor_port=<?php echo $master_port;?>&WB_EN="+wb_en+"&*WB_EN=0xf";

            $.ajax({
                url: url,
                success: function(){
                    console.log("awb "+(wb_en?"on":"off"));
                }
            });

        });
    }

    function init_aexp_toggle(){
        $('#toggle_aexp').click(function() {

            if ($(this).find('.btn.active').html()=="ON"){
                $(this).find('.btn.active').toggleClass('btn-success');
            }else{
                $(this).find('.btn.active').toggleClass('btn-danger');
            }

            // toggle active
            $(this).find('.btn').toggleClass('active');

            if ($(this).find('.btn.active').html()=="ON"){
                aexp_en = 1;
                $(this).find('.btn.active').toggleClass('btn-success');
            }else{
                aexp_en = 0;
                $(this).find('.btn.active').toggleClass('btn-danger');
            }

            $(this).find('.btn').toggleClass('btn-default');

            url = "parsedit.php?immediate&sensor_port=<?php echo $master_port;?>&AUTOEXP_ON="+aexp_en+"&*AUTOEXP_ON=0xf";

            $.ajax({
                url: url,
                success: function(){
                    console.log("aexp "+(aexp_en?"on":"off"));
                }
            });

        });
    }

    async function init_inputs(){
        $("input").change(function(){
            let pname  = $(this).attr('pname');
            let pvalue = $(this).val();

            switch(pname){
            case "AUTOEXP_EXP_MAX":
                pvalue = parseInt(pvalue*1000);
                set_param(pname,pvalue,()=>{
                    console.log("ok");
                });
                break;
            case "EXPOS":
                pvalue = parseInt(pvalue*1000);
                // autoexp off
                if ($('#toggle_aexp').find('.btn.active').html()==="ON"){
                    $('#toggle_aexp').click();
                }
                set_param(pname,pvalue,()=>{
                    console.log("ok");
                });
                break;
            case "TRIG_PERIOD":
                pvalue = parseInt(1/pvalue*10e7);
                set_param(pname,pvalue,()=>{
                    console.log("ok");
                    update_canvas_mjpeg();
                });
                break;
            default:
            }
        });
    }

    async function init_lowlatency(){
        $("#low_latency_link").click(async ()=>{
            await set_param("WOI_WIDTH",1920,()=>{console.log("ok");});
            await set_param("WOI_HEIGHT",1088,()=>{console.log("ok");});
            await set_param("TRIG_PERIOD",3333333,()=>{console.log("ok");});
            $("#ll_status").css({opacity:1}).animate({opacity:0},1000);
            update_canvas_mjpeg();
        });

    }

    async function update_canvas_mjpeg(){

        let refresh = parseInt(1/$("#fps").val()*1000);

        $(".canvas_mjpeg").each(function(){
            let href = $(this).attr("href");
            href = href.split("?");
            let p = new URLSearchParams(href[1]);
            let port = p.get("port");
            $(this).attr("href",href[0]+"?port="+port+"&refresh="+refresh);
        });


    }

    async function set_param(pname,pvalue,callback){
        $.ajax({
            url: "parsedit.php?immediate&sensor_port=<?php echo $master_port;?>&"+pname+"="+pvalue+"&*"+pname+"=0xf",
            success: callback
        });
    }

</script>
<body>
</html>
