/*
FILE NAME  : update_software.js
DESCRIPTION: update software on nand flash
REVISION: 1.00
AUTHOR: Oleg Dzhimiev <oleg@elphel.com>
LICENSE: AGPL, see http://www.gnu.org/licenses/agpl.txt
Copyright (C) 2016 Elphel, Inc.
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