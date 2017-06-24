
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
  
  var link = document.createElement('a');
  
  link.setAttribute('download', null);
  link.style.display = 'none';
  
  document.body.appendChild(link);
  
  link.setAttribute('href', addr);
  link.click();
  
  document.body.removeChild(link);
  
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

