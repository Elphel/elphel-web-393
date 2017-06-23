
var tp_old = 0;

function take_snapshot(){
  
  $("#snapshot").attr("disabled",true);
  
  if(ports.length!=0){
    read_trig_master();
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
    url:ip+"/snapshot.php?trig",
    success:function(){
      
      setTimeout(download_all,200);
      
    }
  });
}

function download_all(){
  
    ports.forEach(function(c,i){
        download_single(ip+":"+c+"/bimg");
    });
    
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

function download_single(addr){
  
  var link = document.createElement('a');
  
  link.setAttribute('download', null);
  link.style.display = 'none';
  
  document.body.appendChild(link);
  
  link.setAttribute('href', addr+"/img");
  link.click();
  
  document.body.removeChild(link);
  
}