
var tp_old = 0;
var DLC = 0;
var DLC_exif = 0;
var filenames = [];

function take_snapshot(){

  $("#snapshot").attr("disabled",true);

  if(ports.length!=0){
    if ($("#synced").prop("checked")){
      read_trig_master();
    }else{
      download_all(false);
    }
  }else{
    console.log("No ports detected");
  }

}

function read_trig_master(){
  var param = "TRIG_MASTER";
  $.ajax({
    url: ip+"/parsedit.php?immediate&"+param,
    success:function(data){
      trig_master = parseInt($(data).find(param).text());
      trigger();
    }
  });
}

function trigger(){
  $.ajax({
    url:"?trig",
    success:function(){

      setTimeout(function(){
        download_all(true);
      },200);

    }
  });
}

function download_all(rtp){

    if ($("#aszip").prop("checked")){

      // get ze blob
      var http = new XMLHttpRequest();
      http.open("GET", "?zip", true);
      http.responseType = "blob";

      http.onload = function(e){
        if (this.status === 200) {
          var filename = this.getResponseHeader("Content-Disposition");
          pass_to_file_reader(filename,http.response);
          if ($("#synced").prop("checked")) {
            read_tp();
          }
        }
      };

      http.send();

    }else{

      DLC = 0;
      ports.forEach(function(c,i){
        //download_single(ip+":"+c+"/timestamp_name/img");
        download_single(ip+":"+c+"/timestamp_name/bimg");
      });

    }

}

function download_single(addr){

  // get ze blob
  var http = new XMLHttpRequest();
  http.open("GET", addr, true);
  http.responseType = "blob";
  http.onload = function(e){

    if (this.status === 200) {

      // To access the header, had to add
      // printf("Access-Control-Expose-Headers: Content-Disposition\r\n");
      // to imgsrv
      var filename = this.getResponseHeader("Content-Disposition");

      pass_to_file_reader(filename,http.response);

      DLC++;
      if (DLC==ports.length){
        if ($("#synced").prop("checked")) {
	  if (dl_exif_histories==1){
	    console.log("getting exif histories");
	    get_exifs();
	  }else{
	    read_tp();
	  }
        }else{
          $("#snapshot").attr("disabled",false);
        }
      }

    }

  }
  http.send();

}


// from here:
//   https://diegolamonica.info/multiple-files-download-on-single-link-click/
//   http://jsfiddle.net/diegolamonica/ssk8z9pa/
//   (helped a lot) https://stackoverflow.com/questions/19327749/javascript-blob-filename-without-link
function pass_to_file_reader(filename,fileblob){

  var parameters = filename.split(";");
  for (var i=0;i<parameters.length;i++) parameters[i]=parameters[i].split("=");
  for (var i=0;i<parameters.length;i++) {
    if (parameters[i][0].trim()=="filename"){
      filename = parameters[i][1].replace(/"/ig,"");
      filename = filename.trim();
    }
  }

  filenames.push(filename);

  var url = window.URL.createObjectURL(fileblob);

  var a = $('<a>')
    .attr('href', url)
    .attr('download',filename)
    // Firefox does not fire click if the link is outside
    // the DOM
    .appendTo('body');

  a[0].click();
  //a.click();

  // delay?
  setTimeout(function(){
    a.remove();
  },200);

  //return;

  // The code below will not work because .readAsDataURL() is limited in Chrome to 2MB, but no limit in FF.

  /*
  var reader = new FileReader();
  reader.filename = filename;

  reader.onloadend = function(e){

      //console.log("Load ended!");

      var file = [this.filename,e.target.result];

      var theAnchor = $('<a>')
        .attr('href', file[1])
        .attr('download',file[0])
        // Firefox does not fire click if the link is outside
        // the DOM
        .appendTo('body');

      theAnchor[0].click();
      //theAnchor.click();

      //delay

      setTimeout(function(){
        theAnchor.remove();
      },200);

  };

  reader.onload = function(e){
    //console.log("onload");
  };

  reader.readAsDataURL(filedata);
  */

}

function get_exifs(){

  DLC_exif = 0;

  filenames.forEach(function(c,i){

    var base = c.split(".");
    base = base[0];

    var port = base.split("_");
    port = port[2];
    var filename = base+"_exifs.txt";
    var addr = "?exifs&sensor_port="+port;

    var http = new XMLHttpRequest();
    http.open("GET", addr, true);
    http.responseType = "blob";

    http.onload = function(e){

      if (this.status === 200) {

        // To access the header, had to add
        // printf("Access-Control-Expose-Headers: Content-Disposition\r\n");
        // to imgsrv
        //var filename = this.getResponseHeader("Content-Disposition");

        pass_to_file_reader(filename,http.response);

        DLC_exif++;
        if (DLC_exif==ports.length){
          if ($("#synced").prop("checked")) {
	    // empty
	    filenames = [];
            read_tp();
          }else{
            $("#snapshot").attr("disabled",false);
          }
        }

      }

    }
    http.send();

  });

}

function read_tp(){

    var param = "TRIG_PERIOD";
    $.ajax({
      url: ip+"/parsedit.php?immediate&"+param,
      success:function(data){
        tp_old = parseInt($(data).find(param).text());
        restore_trig_period();
      }
    });
}

function restore_trig_period(){

    $.ajax({
      url: ip+"/parsedit.php?immediate&TRIG_PERIOD="+(tp_old)+"*-2&sensor_port="+trig_master,
      success: function(){

        console.log("Done");
        $("#snapshot").attr("disabled",false);

      }
    });

}

function toggle_help(){

  $("#help").toggle();

}

