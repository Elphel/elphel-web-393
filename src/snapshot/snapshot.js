
var tp_old = 0;

function take_snapshot(){
  
  $("#snapshot").attr("disabled",true);
  
  if(ports.length!=0){
    
    if ($("#synced").attr("checked")){
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
      trig_master = parseInt($(data).find(param));
      read_tp();
    }
  });
}

function read_tp(){
    var param = "TRIG_PERIOD";
    $.ajax({
      url: ip+"/parsedit.php?immediate&"+param,
      success:function(data){
        tp_old = parseInt($(data).find(param));
        trigger();
      }
    });
}

// channel independent or lowest?
function read_par(param,callback){
    $.ajax({
      url: ip+"/parsedit.php?immediate&"+param,
      success:function(data){
        tp_old = parseInt($(data).find("TRIG_MASTER"));
        trigger();
      }
    });
}

function trigger(){
  $.ajax({
    url:ip+"?trig",
    success:function(){
      
      setTimeout(function(){
        download_all(true);
      },500);
      
    }
  });
}

function restore_trig_period(){
  
    $.ajax({
      url: ip+"/parsedit.php?immediate&TRIG_PERIOD="+(tp_old+1)+"*-2&sensor_port="+trig_master,
      success: function(){
        
        $.ajax({
          url: ip+"/parsedit.php?immediate&TRIG_PERIOD="+(tp_old)+"*-2&sensor_port="+trig_master,
          success: function(){
            
            console.log("Done!");
            $("#snapshot").attr("disabled",false);
            
          }
        });
        
      }
    });
    
}

function download_all(rtp){
  
    ports.forEach(function(c,i){
        download_single(ip+":"+c+"/img");
    });
    
    // give 500ms (?)
    if (rtp) {
      setTimeout(function(){
        restore_trig_period();
      },200);
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

function toggle_help(){
  
  $("#help").toggle();
  
}

