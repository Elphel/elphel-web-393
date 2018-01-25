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

$(function(){

  $("#init").on('click',function(){
    console.log("init photo finish");
  });

  $("#refresh").on('click',function(){
    console.log("refresh images");
  });

  var t1 = $("#display-panel").jp4({
    ip:"127.0.0.1",
    port:2323,
    width:600,
    fast:true,
    lowres:0,
    webworker_path:"/js"
  });

  $("#display-panel").on("canvas_ready",function(){

    // get display canvas - hide
    var cnv_old = $(this).find("#display")[0];

    $(cnv_old).hide();

    var w = cnv_old.width;
    var h = cnv_old.height;

    var parent = $(cnv_old).parent();

    cnv_new = $("<canvas>",{id:"display2"});
    parent.append(cnv_new);

    var ctx = cnv_new[0].getContext("2d");

    ctx.canvas.width=h;
    ctx.canvas.height=w;

    ctx.save();
    ctx.rotate(90*Math.PI/180);
    ctx.scale(1, -1);
    ctx.drawImage(cnv_old,0,0,w,h,0,0,w,h);
    ctx.restore();

  });

});
