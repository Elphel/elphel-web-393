/** 
 * @file index.js
 * @brief imgsrv pointers monitor
 * @copyright Copyright (C) 2017 Elphel Inc.
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

$(function(){
  init();
});

function init(){
  var wrapper = $("<div>").css({
    padding: "10px"
  });
  $("body").append(wrapper);
  
  var tbl = $("<table>");
  
  tbl.append($("\
<tr>\
  <th colspan='8'></th>\
  <th colspan='3' style='background:rgba(230,230,230,0.5)'>Pointers</th>\
</tr>\
<tr>\
  <th>Port</th>\
  <th>Frame #</th>\
  <th>Compressed #</th>\
  <th>Size, kB</th>\
  <th>Buffer</th>\
  <th>Frames</th>\
  <th>Left</th>\
  <th>Lag</th>\
  <th>this</th>\
  <th>write</th>\
  <th>read</th>\
  <th>sensor state</th>\
  <th>compressor state</th>\
</tr>"));
  
  var tmp_tr,tmp_td;
  
  for(var i=0;i<4;i++){
    tmp_tr = $("<tr>",{id:"row_"+i});
    tbl.append(tmp_tr);
    
    //port name
    tmp_td = $("<td align='center'>");
    tmp_td.html(i);
    
    tmp_tr.append(tmp_td);
    
    //frame#
    tmp_td = $("<td align='center'>");
    tmp_td.html("<div class='frame'></div>");
    tmp_tr.append(tmp_td);

    //compressed frame #
    tmp_td = $("<td align='center'>");
    tmp_td.html("<div class='frame_compressed'></div>");
    tmp_tr.append(tmp_td);
    
    //frame size
    tmp_td = $("<td align='center'>");
    tmp_td.html("<div class='frame_size'></div>");
    tmp_tr.append(tmp_td);
    
    //buffer bar
    tmp_td = $("<td align='center' valign='bot'>");
    tmp_td.html("<div class='buffer'><div class='buffer_free'></div><div class='buffer_used'></div></div>");
    tmp_tr.append(tmp_td);
    
    //frames
    tmp_td = $("<td align='center'>");
    tmp_td.html("<div class='frames'></div>");
    tmp_tr.append(tmp_td);
    
    //left
    tmp_td = $("<td align='center'>");
    tmp_td.html("<div class='frames_left'></div>");
    tmp_tr.append(tmp_td);

    //lag
    tmp_td = $("<td align='center'>");
    tmp_td.html("<div class='frames_lag'></div>");
    tmp_tr.append(tmp_td);
    
    //this pointer
    tmp_td = $("<td align='center'>");
    tmp_td.html("<div class='pointer_this pointers'></div>");
    tmp_tr.append(tmp_td);
    
    //write pointer
    tmp_td = $("<td align='center'>");
    tmp_td.html("<div class='pointer_write pointers'></div>");
    tmp_tr.append(tmp_td);
    
    //read pointer
    tmp_td = $("<td align='center'>");
    tmp_td.html("<div class='pointer_read pointers'></div>");
    tmp_tr.append(tmp_td);
    
    //lag
    tmp_td = $("<td align='center'>");
    tmp_td.html("<div class='sensor_state states'></div>");
    tmp_tr.append(tmp_td);
    
    //lag
    tmp_td = $("<td align='center'>");
    tmp_td.html("<div class='compressor_state states'></div>");
    tmp_tr.append(tmp_td);

  }
  
  wrapper.append(tbl);
 
  run();
  setInterval("run()",1000);
}

function run(){
  console.log("run");
  var port = 2323;
  for(var i=0;i<4;i++){
    $.ajax({
      url: "pointers.php?port="+(port+i),
      index: i,
      success: function(data){
        //console.log(this.index);
        parse_pointer_data(this.index,data);
      }
    });
  }
}

function parse_pointer_data(index,data){
  console.log("parsing");
  var row = $("#row_"+index);
  
  // frame number
  var frame = $(data).find("frame").text();
  row.find(".frame").html(frame);

  // frame number
  var frame = $(data).find("compressedFrame").text();
  row.find(".frame_compressed").html(frame);
  
  // frame size 
  var frame = Math.round(parseInt($(data).find("frame_size").text())/1024);
  row.find(".frame_size").html(frame);
  
  // buffer
  var buf_free = parseInt($(data).find("free").text());
  var buf_used = parseInt($(data).find("used").text());
  var buf_sum = buf_free + buf_used;

  var buf_full_width = row.find(".buffer").width();
  var buf_free_width;
  
  // reset bar
  row.find(".buffer_free").removeClass("buffer_invalid").html(b2mb(buf_free)+" MB");
  row.find(".buffer_used").html(b2mb(buf_used)+" MB");
  
  buf_free_width = buf_free/buf_sum * buf_full_width;
  
  if (buf_free==-1){
    buf_free_width = buf_full_width;
    row.find(".buffer_free").addClass("buffer_invalid").html("invalid");
  }
  
  buf_used_width = buf_full_width - buf_free_width;
  
  row.find(".buffer_free").css({
    width: buf_free_width+"px"
  });
  
  row.find(".buffer_used").css({
    width: buf_used_width+"px"
  });
  
  // frames
  var frame = $(data).find("frames").text();
  row.find(".frames").html(frame);

  // frames left
  var frame = $(data).find("left").text();
  row.find(".frames_left").html(frame);
  
  // frames lag
  var frame = $(data).find("lag").text();
  row.find(".frames_lag").html(frame);  
  
  // pointer this
  var frame = $(data).find("this").text();
  row.find(".pointer_this").html(frame);
  
  // pointer write
  var frame = $(data).find("write").text();
  row.find(".pointer_write").html(frame);
  
  // pointer write
  var frame = $(data).find("read").text();
  row.find(".pointer_read").html(frame);
  
  // pointer read
  var frame = $(data).find("sensor_state").text();
  frame = frame.replace(/"/g,"");
  row.find(".sensor_state").css({
    color: get_color(frame)
  }).html(frame);
  
  if (get_color(frame)=="red"){
    row.find(".buffer_free").addClass("buffer_invalid").html("invalid");
  }
  
  // pointer this
  var frame = $(data).find("compressor_state").text();
  frame = frame.replace(/"/g,"");
  row.find(".compressor_state").css({
    color: get_color(frame)
  }).html(frame);
  
  if (get_color(frame)=="red"){
    row.find(".buffer_free").addClass("buffer_invalid").html("invalid");
  }
  
}

function get_color(text){
  if (text.search(/STOP/)!=-1) {
    return "red";
  }
  return "";
}

function b2mb(val){
  return (Math.round(val/1024/1024*10)/10);
}
