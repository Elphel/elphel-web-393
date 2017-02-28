/** 
 * @file save_single.js
 * @brief save single shots (download synced images from all available ports)
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
  
  $("#save").on("click",function(){
    if (!$(this).prop("disabled")){
      $(this).prop("disabled",true);
      console.log("disable");
      read_current_trig_period();
    }else{
      console.log("Disabled!");
    }
  });
  
}

function read_current_trig_period(){
  $.ajax({
    url: "http://"+window.location.host+"/parsedit.php?immediate&TRIG_PERIOD",
    success: function(data){
      var TRIG_PERIOD = $(data).find("TRIG_PERIOD").text();
      download_all(TRIG_PERIOD);
      restore_trig_period(TRIG_PERIOD);
    }
  });
}

function restore_trig_period(period){
  $.ajax({
    url: "http://"+window.location.host+"/parsedit.php?immeadiate&TRIG_PERIOD="+period+"*-2&sensor_port=0",
    success: function(){
      console.log("enable");
      $("#save").prop("disabled",false);
      console.log("Done");
    }
  });
}

function download_all(){
  for(var i=0;i<ports.length;i++){
    var tmp_href = "http://"+window.location.host+":"+ports[i]+"/img";
    download(tmp_href);
  }
}

function download(href){
  
  var link = document.createElement('a');
  
  link.setAttribute('download', null);
  link.style.display = 'none';
  
  document.body.appendChild(link);
  
  link.setAttribute('href', href);  
  link.click();
  
  document.body.removeChild(link);
}