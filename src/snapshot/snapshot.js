
var tp_old = 0;

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
    url:href+"?trig",
    success:function(){
      
      setTimeout(function(){
        download_all(true);
      },200);
      
    }
  });
}

function download_all(rtp){
  
    ports.forEach(function(c,i){
        download_single(ip+":"+c+"/img");
    });
    
    if (rtp) {
      setTimeout(function(){
        read_tp();
      },200);
    }else{
      $("#snapshot").attr("disabled",false);
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
      
    }
    
  }
  http.send();
  
  /*
  return 0;
  
  var link = document.createElement('a');
  
  link.setAttribute('download', null);
  link.style.display = 'none';
  
  link.download = addr;
  link.href = addr;
  
  document.body.appendChild(link);
  link.click();
  
  document.body.removeChild(link);
  */
}


// from here:
//   https://diegolamonica.info/multiple-files-download-on-single-link-click/
//   http://jsfiddle.net/diegolamonica/ssk8z9pa/
function pass_to_file_reader(filename,filedata){

  var parameters = filename.split(";");
  for (var i=0;i<parameters.length;i++) parameters[i]=parameters[i].split("=");
  for (var i=0;i<parameters.length;i++) {
    if (parameters[i][0].trim()=="filename"){
      filename = parameters[i][1].replace(/"/ig,"");
      filename = filename.trim();
    }
  }
  
  var reader = new FileReader();
  reader.filename = filename;
  
  reader.onloadend = function(e){

      var file = [this.filename,e.target.result];
      
      var theAnchor = $('<a />')
        .attr('href', file[1])
        .attr('download',file[0])
        // Firefox does not fires click if the link is outside
        // the DOM
        .appendTo('body');
            
      theAnchor[0].click();
      //theAnchor.click();
      theAnchor.remove();
    
  };
  
  reader.readAsDataURL(filedata);
  
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

