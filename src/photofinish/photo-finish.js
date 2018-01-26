/**
 * @file photo-finish.js
 * @brief simple demo
 * @copyright Copyright (C) 2018 Elphel Inc.
 * @author Oleg Dzhimiev <oleg@elphel.com>
 *
 * @licstart  The following is the entire license notice for the
 * JavaScript code in this page.
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

var display_object;

$(function(){

  $("#init").on('click',function(){
    console.log("init photo finish");
    wait_start();
    $.ajax({
      url: "photo-finish.php?cmd=init",
      success: function(){
        console.log("success");
        wait_stop("init done");
        //refresh
        $("#refresh").click();
      }
    });
  });

  $("#refresh").on('click',function(){
    console.log("refresh images");
    wait_start();
    display_object.data.refresh();
  });

  display_object = $("#display-panel").jp4({
    ip:"127.0.0.1",
    port:2323,
    width:600,
    fast:true,
    lowres:0,
    webworker_path:"/js"
  });

  $("#display-panel").on("canvas_ready",function(){
    canvas_ready_handler(this);
  });

});

function canvas_ready_handler(elem){

    // find display canvas from original plugin
    var cnv_old = $(elem).find("#display")[0];
    var parent  = $(cnv_old).parent();

    // init
    if (parent.find("#display2").length==0){
      $(cnv_old).hide();
      cnv_new = $("<canvas>",{id:"display2"});
      parent.append(cnv_new);
    }else{
      cnv_new = parent.find("#display2");
    }

    var w = cnv_old.width;
    var h = cnv_old.height;

    var ctx = cnv_new[0].getContext("2d");

    ctx.canvas.width=h;
    ctx.canvas.height=w;

    ctx.save();
    ctx.rotate(90*Math.PI/180);
    ctx.scale(1, -1);
    ctx.drawImage(cnv_old,0,0,w,h,0,0,w,h);
    ctx.restore();

    wait_stop("refresh done");
}

var wait_interval;
var timeout_counter;

function wait_start(){
  timeout_counter = 30;
  clearInterval(wait_interval);
  wait_interval = setInterval(wait_tick,1000);
}

function wait_stop(msg){
  clearInterval(wait_interval);
  $("#status").html(msg);
}

function wait_tick(){
  if (timeout_counter==0){
    wait_stop("Timeout");
  }else{
    timeout_counter--;
  }
  $("#status").html("waiting("+timeout_counter+")");
}
