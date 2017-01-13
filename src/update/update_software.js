/** 
 * @file update_software.js
 * @brief update software on nand flash
 * @copyright Copyright (C) 2016 Elphel Inc.
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

var blink_intvl;

function init(){
  console.log("init");
    
  var url = "update_software.php";
  $('#fileupload').fileupload({
    url: url,
    dataType: 'json',
    done: function (e, data) {
      $.each(data.result.files, function (index, file) {
        $('<p/>').text(file.name).appendTo('#files');
      });
    },
    progressall: function (e, data) {
      var progress = parseInt(data.loaded / data.total * 100, 10);
      $('#progress .progress-bar').css(
        'width',
        progress + '%'
      );
    }
  }).prop('disabled', !$.support.fileInput).parent().addClass($.support.fileInput ? undefined :'disabled');
  
  $('#btn_remove').click(function(){
    $.ajax({
      url: "update_nand.php?cmd=remove",
      success: function(result){
        $("#status").html(result);
      }
    });
  });
  
  $('#btn_verify').click(function(){
    $.ajax({
      url: "update_nand.php",
      success: function(result){
        $("#status").addClass("blink").html(result);
      }
    });
  });
  
  $('#btn_flash').click(function(){
    $("#status").html("Flashing...");
    blink_intvl = setInterval(blink,1000);
    $.ajax({
      url: "update_nand.php?cmd=flash",
      success: function(result){
        clearInterval(blink_intvl);
        $("#status").html(result);
      }
    });
  });
}

function blink(){
  $('.blink').fadeOut(500);
  $('.blink').fadeIn(500);
}

function upload(){
  console.log("upload");
}