
// port class
var Port = function(options){

  var defaults = {
    port: 0,
    index: 0,
    awb:  NaN,
    aexp: NaN,
    preview: null,
    sensor_type:'none'
  };

  this._data = $.extend(defaults,options);

  this.port    = this._data.port;
  this.index   = this._data.index;
  this.awb     = this._data.awb;
  this.aexp    = this._data.aexp;
  this.preview = this._data.preview;
  this.sensor_type = this._data.sensor_type;

}

var BOSON_FFCS_REG= {
	"gao_gain":   0,
	"gao_sffc":   7,
	"gao_ffc":    1,
	"gao_temp":   2,
	"bpr":        22,
	"scnr":       36,
	"srnr":       51,
	"spnr":       46,
	"tf":         42,
	"tf_delta_nf":43
};

var boson_ffc= {
	"gao_gain":   1,
	"gao_sffc":   1,
	"gao_ffc":    1,
	"gao_temp":   1,
	"bpr":        1,
	"scnr":       1,
	"srnr":       1,
	"spnr":       1,
	"tf":         1,
	"tf_delta_nf":1
};


// camera class
var Camera = function(options){

  var defaults = {
    ip: "",
    init: false,
    status: false,
    camogm: false,
    recording: false,
    mounted: false,
    ports: []
  };

  this._data = $.extend(defaults,options);

  this.ip = this._data.ip;
  this.init = this._data.init;
  this.status = this._data.status;
  this.ports = this._data.ports;
  

};

// global
var recording = false;
//var ips_from_url = false;

var cams = [];

var wb_en = 1;
var aexp_en = 1;
var skip_previews = 0;
var refresh_en = 1;
var record_en =  0;
var multicam_dir = "";
var multicam_rperiod = 5.0; // image refresh period
var multicam_speriod = 2.0; // status refresh period
var use_file_system =  1; // no fast recording 
var rec_running_intvl;
var refresh_status_intvl;
var refresh_previews_intvl;
var exp_ms =  50;
var quality = 97;


$(function(){
  init();
});

// launch reading configs from the master camera
function init(){
	  console.log('--PRE parseURL--');
  // what can be done before configuring cameras (ip or ajax) and ports (init2)	
  $.ajax({
	  url: "multicam2.php?cmd=configs",
	  success: function(response){
		var multicam_dir_xml = $(response).find("multicam_dir");
		if (multicam_dir_xml) multicam_dir = multicam_dir_xml.text();
		var multicam_rperiod_xml = $(response).find("multicam_rperiod");
		if (multicam_rperiod_xml) multicam_rperiod = parseFloat(multicam_rperiod_xml.text());
		var multicam_speriod_xml = $(response).find("multicam_speriod");
		if (multicam_speriod_xml) multicam_speriod = parseFloat(multicam_speriod_xml.text());
		console.log('Got configs from master camera config, multicam_dir='+multicam_dir+
		 ", multicam_rperiod="+multicam_rperiod + ", multicam_speriod="+multicam_speriod);
	    init1(); // should be done only after request responce!
	  }
  });
}

function init1(){
  // what can be done before configuring cameras (ip or ajax) and ports (init2)	
  init_rec_button();
  $("#ea_btn").on('click',function(){
    $("#edit_addrs_input").css({
      top: $(this).offset().top,
      left: $(this).offset().left
    }).show();
    $("#eai_text").focus();
  });

  $("#eai_ok").on('click',function(){
    addrs_ta2ips();
    $("#edit_addrs_input").hide();
  });
  zip.workerScriptsPath = "../js/zip/";
  console.log('--PRE parseURL--');
  init_test_button();
  init_snapshot_button();
  initCollapsibleElements();

  // what can NOT be done before configuring cameras (ip or ajax) and ports (init2)	
//get config
  $.ajax({
	  url: "multicam2.php?cmd=ips",
	  success: function(response){
	    var ips = $(response).find("ip");
	    ips.each(function(){
	        console.log('index='+($(this).attr("index"))+", text="+$(this).text());
			cams[$(this).attr("index")] = new Camera ({ip:$(this).text()});
	    });
		console.log('Got IPs from master camera config, ips.length='+ips.length);
	    init2(); // should be done only after request responce!
	  }
  });
}

function init2(){
  addrs_ips2ta();
  // create tables
  addrs_create_tables();
  // now get the ports
  get_ports();

  init_awb_toggle();
  init_aexp_toggle();
  init_multicam_controls(); // 2022
  console.log("Got cameras");
  console.log(cams);
  initBosonFFC();
}



function get_ports(){
  
  for(var i=0;i<cams.length;i++){
	console.log("get_ports():"+i);
    $.ajax({
      url: "http://"+cams[i].ip+"/multicam/multicam2.php?cmd=ports",
      ip:cams[i].ip,
      index: i,
      success: function(response){
        var index = this.index;
        var ports = $(response).find("port");

        cams[this.index].status = true;
        cams[this.index].init   = true;

        // ports are already ordered in response
        ports.each(function(){
          if ($(this).text()!=='none'){
            var tmp_port = new Port({port:  $(this).attr("port"), index: $(this).attr("index"), sensor_type:  $(this).html()});
            cams[index].ports.push(tmp_port);
            init_port(index,cams[index].ports.length-1);
          }
        });
        // check camogm is alive
        check_camogm(index);

        init3(index);

      }
    }).fail(function(data,status){
      console.log(this.ip+" request failed. Check errors");
      // will be checked when sending requests
      cams[this.index].status = false;
      addrs_mark_bad_ip(this.ip);
    });

  }

}

function check_camogm(cam_i){

  // run_status does not interact with camogm, quickest response
  $.ajax({
    url: "http://"+cams[cam_i].ip+"/camogm_interface.php?cmd=run_status",
    cam_i: cam_i,
    success: function(res){
      var state = $(res).find('state').text();
      // false(if not init) or 'on' or 'off'
      cams[this.cam_i].camogm = state;
      if (state=='off'){
        console.log(cams[this.cam_i].ip+": camogm is off");
        // launch it
        camogm_launch(this.cam_i);
      }
      if (state=='on'){
        console.log(cams[this.cam_i].ip+": camogm is on");
//        check_camogm_status(this.cam_i);
        setDirSingle(this.cam_i); // wiil end with check_camogm_status(this.cam_i)
      }
    }
  });

}

function camogm_launch(cam_i){

  // raw recording is default on start
  $.ajax({
    url: "http://"+cams[cam_i].ip+"/camogm_interface.php?cmd=run_camogm",
    cam_i: cam_i,
    success: function(res){
      console.log(cams[this.cam_i].ip+": "+res);
      cams[this.cam_i].camogm = 'on';
//      check_camogm_status(this.cam_i);
      setDirSingle(this.cam_i); // wiil end with check_camogm_status(this.cam_i)
    }
  });

}

function check_camogm_status(cam_i){ // full check, slow
  if (use_file_system) {
//     console.log("check_camogm_status("+cam_i+")1, use_file_system="+use_file_system);
	  $.ajax({
	    url: "http://"+cams[cam_i].ip+"/camogm_interface.php?cmd=state", // shorter version, just state
	    cam_i: cam_i,
	    success: function(res){
	      var cam = cams[this.cam_i];
	//      console.log("check_camogm_status for "+cam_i+" success");
	      if ($(res).find('state').length!=0){
	        var state = $(res).find('state').text();
	        state = state.replace(/"/gm,'');
	        // false(if not init) or 'on' or 'off'
	        cams[this.cam_i].recording = state;
	        rec_button_update_state();
	        var se = $("#display_status").find("tr[ip='"+cam.ip+"']");
            check_camogm_free_space(this.cam_i);
	      }
	    }
	  }).fail(function(data,status){
	    console.log("check_camogm_status()use_file_system: status request failed)."); //CORS
	    console.log(data); // CORS
	    console.log(status);
	    console.log(cam_i);
	    
	  });
  } else { // raw file system
     console.log("check_camogm_status("+cam_i+")2, use_file_system="+use_file_system);
	  $.ajax({
	    url: "http://"+cams[cam_i].ip+"/camogm_interface.php?cmd=status",
	    cam_i: cam_i,
	    success: function(res){
	      var cam = cams[this.cam_i];
	//      console.log("check_camogm_status for "+cam_i+" success");
	      if ($(res).find('state').length!=0){
	        var state = $(res).find('state').text();
	        state = state.replace(/"/gm,'');
	        // false(if not init) or 'on' or 'off'
	        cams[this.cam_i].recording = state;
	        rec_button_update_state();
	
	        var se = $("#display_status").find("tr[ip='"+cam.ip+"']");
        
	        // device
	        var device = $(res).find('raw_device_path').text().replace(/"/gm,'');
	        se.find("#s_device").html(device);
	
	        // free space
	        var lba_end = parseInt($(res).find('lba_end').text());
	        var lba_current = parseInt($(res).find('lba_current').text());
	
	        var free_space = (lba_end - lba_current)/2/1024/1024;
	        free_space = Math.round(100*free_space)/100;
	        se.find("#s_space").html(free_space+" GB");
	      }
	    }
	  }).fail(function(data,status){
	    console.log("status request failed).");
	    console.log(data);
	    console.log(status);
	    console.log(cam_i);
	    
	  });
  }
}

function check_camogm_free_space(cam_i){ // file system, no raw
  $.ajax({
    url: "http://"+cams[cam_i].ip+"/camogm_interface.php?cmd=get_hdd_space",
    cam_i: cam_i,
    success: function(res){
      var cam = cams[this.cam_i];
//      console.log("check_camogm_free_space for "+cam_i+" success");
      if ($(res).find('get_hdd_space').length!=0){
	   var free_space = parseInt($(res).find('get_hdd_space').text());
	   var sdd_found = free_space > 0;
	   cam.mounted = sdd_found;
	   free_space /= 1024*1024*1024;
	   free_space = Math.round(100*free_space)/100;
       var se = $("#display_status").find("tr[ip='"+cam.ip+"']");
	        // device
       se.find("#s_device").html(multicam_dir);
	        // free space
       se.find("#s_space").html(free_space+" GB");
       var bgcol = sdd_found ? "rgb(200, 255, 200)":"rgb(255, 100,100)"; // add yellow for low space
       se.find("#s_space").attr("style","background-color:"+bgcol+";");
       
      }
    }
  }).fail(function(data,status){
    console.log("check_camogm_free_space("+cam_i+")status request failed).");
    console.log(data);
    console.log(status);
    console.log(cam_i);
  });
}


var all_ready_init_run = true;

function rec_button_update_state(){

  var all_ready = true;
  var any_running = false;
  var any_stopped = false;

  for(var i=0;i<cams.length;i++){
    if (cams[i].init){
      if ((cams[i].camogm=='on')&&(!cams[i].recording)){
        all_ready = false;
        break;
      }

      if(cams[i].recording=='running'){
        any_running = true;
      }else if(cams[i].recording=='stopped'){
        any_stopped = true;
      }
    }
  }

  if (all_ready){

    if (any_running && any_stopped){
      console.log("WARNING: some camogms are running, some are stopped"); // false
    }

    if (all_ready_init_run){

      // display like it's running
      if (any_running){
        console.log("Turn on recording");
        recording = true;
        rec_button_switch(recording);
      }
      
      /*
      if (!skip_previews) { // do not schedule previews as they are very slow for Bosons
        refresh_previews_intvl = setInterval(refresh_previews,multicam_rperiod * 1000);
      } else {
//          alert("skipping previews");
      }
      refresh_status_intvl   = setInterval(refresh_status,multicam_speriod * 1000);
      */
      startStopRefresh();
    }
    all_ready_init_run = false;

  }
}

function startStopRefresh (){
	if (refresh_en) {
		if (!skip_previews) {
			if (refresh_previews_intvl) {
				clearInterval(refresh_previews_intvl);
				refresh_previews_intvl = null;
			}
        	refresh_previews_intvl = setInterval(refresh_previews,multicam_rperiod * 1000);
        	refresh_previews(); // first time - immediately
       	}
		if (refresh_status_intvl) {
			clearInterval(refresh_status_intvl);
			refresh_status_intvl = null;
		}
        refresh_status_intvl   = setInterval(refresh_status,multicam_speriod * 1000);
       	refresh_status(); // first time - immediately
        console.log("setInterval(refresh_previews_intvl); refresh_previews_intvl="+refresh_previews_intvl+', period='+(multicam_rperiod * 1000));
        console.log("setInterval(refresh_status_intvl); refresh_status_intvl="+refresh_status_intvl+', period='+(multicam_speriod * 1000));
	} else {
      	clearInterval(refresh_previews_intvl);
        clearInterval(refresh_status_intvl);
        console.log("clearInterval(refresh_previews_intvl); refresh_previews_intvl="+refresh_previews_intvl);
        console.log("clearInterval(refresh_status_intvl); refresh_status_intvl="+refresh_status_intvl);
      	refresh_previews_intvl = null;
      	refresh_status_intvl =  null;
      	blankBackground($('#display_previews'));
      	blankBackground($('#display_status'));
	}
	console.log("startStopRefresh(): "+refresh_en)
}


//var refresh_status_intvl;
//var refresh_previews_intvl;

// get initial wb_en and aexp_en from the lowest port
// assuming the lowest port has the same values
// wb_en
// parsedit.php?immediate&sensor_port=0&WB_EN
function init_port(cam_i,port_i){

  var url = "http://"+cams[cam_i].ip+"/parsedit.php?immediate&sensor_port="+cams[cam_i].ports[port_i].index+"&WB_EN";
  $.ajax({
    url: url,
    cam_i: cam_i,
    port_i: port_i,
    success: function(res){
      wb_en = parseInt($(res).find("WB_EN").text());
      cams[this.cam_i].ports[this.port_i].awb = wb_en;
      button_update_state($("#toggle_awb"));
      //button_switch($("#toggle_awb"),wb_en);
    }
  });

  // aexp_en
  // parsedit.php?immediate&sensor_port=0&WB_EN
  var url = "http://"+cams[cam_i].ip+"/parsedit.php?immediate&sensor_port="+cams[cam_i].ports[port_i].index+"&AUTOEXP_ON";
  $.ajax({
    url: url,
    cam_i: cam_i,
    port_i: port_i,
    success: function(res){
      aexp_en = parseInt($(res).find("AUTOEXP_ON").text());
      cams[this.cam_i].ports[this.port_i].aexp = aexp_en;
      button_update_state($("#toggle_aexp"));
      //button_switch($("#toggle_aexp"),aexp_en);
    }
  });

  var url = "http://"+cams[cam_i].ip+"/parsedit.php?immediate&sensor_port="+cams[cam_i].ports[port_i].index+"&BITS";
  $.ajax({
    url: url,
    cam_i: cam_i,
    port_i: port_i,
    success: function(res){
      bits = parseInt($(res).find("BITS").text());
      cams[this.cam_i].ports[this.port_i].bits = bits;
    }
  });



}

// * if any attribute is not initialized it will be NaN
// * if all do not match then take the last one
function button_update_state(btn){

  // if all ports are filled out then update
  var all_match = true;
  var all_ready = true;
  var start = false;
  var old_port_attr;

  for(var i=0;i<cams.length;i++){
    if (cams[i].init){
      for(var j=0;j<cams[i].ports.length;j++){
        var port = cams[i].ports[j];
        if (btn.attr("id")=="toggle_aexp"){
          port_attr = port.aexp;
        }
        if (btn.attr("id")=="toggle_awb"){
          port_attr = port.awb;
        }

        if (isNaN(port_attr)){
          all_ready = false;
          break;
        }

        if (start){
          if (port_attr!=old_port_attr){
            console.log(port_attr+" vs "+old_port_attr);
            all_match = false;
          }
        }else{
          start = true;
        }
        old_port_attr = port_attr;
      }
      if (!all_ready){
        break;
      }
    }
  }

  // check results
  if (!all_match){
    console.log("WARNING: "+btn.attr("id")+": not all parameters match");
  }else{
    //console.log(btn.attr("id")+": all params across all cameras/ports match");
  }

  button_switch(btn,old_port_attr);

}

// can be used for refresh?
function init3(index){
  // display
  var cam = cams[index];
  var ts = Date.now();

  var img_str = "";
  var hst_str = "";

  for(var i=0;i<cam.ports.length;i++){

    //img_src = 'http://'+cam.ip+':'+cam.ports[i].port+'/img?'+ts;
    //hst_src = 'http://'+cam.ip+'/pnghist.cgi?sensor_port='+cam.ports[i].index+'&sqrt=1&scale=5&average=5&height=128&fillz=1&linterpz=0&draw=2&colors=41&_time='+ts;

    img_str += [
      '  <td>',
      '    <div class=\'port_preview\' index=\''+i+'\'></div>',
      '  </td>'
    ].join('\n');

    hst_str += [
      '  <td>',
      '    <img class=\'hist_preview\' index=\''+i+'\' />',
      '  </td>'
    ].join('\n');

  }

  var display_str = [
    '    <tr>',
    '      <td class="ip_text">'+cam.ip+':</td>',
    '    </tr>',
    '    <tr>',
    img_str,
    '    </tr>',
    '    <tr>',
    hst_str,
    '    </tr>'
  ].join('\n');

  $("#display_previews").find("table[ip=\'"+cam.ip+"\']").html($(display_str));

  // and status
  var status_str = [
    '<td id=\'s_ip\'>'+cam.ip+'</td>',
    '<td id=\'s_device\'></td>',
    '<td id=\'s_space\'></td>',
    '<td id=\'s_errors\'></td>'
  ].join('\n');

  $("#display_status").find("tr[ip=\'"+cam.ip+"\']").html($(status_str));

}

function init_test_button(){
  $("#system_tests").on('click',function(){
    window.open('http://'+location.host+'/diagnostics/index.html?ip='+addrs_ips2str());
  });
}

function refresh_previews(){
//  console.log("refresh_previews(), refresh_en = "+refresh_en);
  if (!refresh_en){
	  return;
  } 
  blinkBackground($('#display_previews'));
//  $('#display_previews').css('background-color', 'rgb(255,220,220)');
//  setTimeout( function(){ $('#display_previews').css('background-color', 'rgb(230,230,230)');}, 300);
  var ts = Date.now();
  for(var i=0;i<cams.length;i++){
    if (cams[i].init){
      for(var j=0;j<cams[i].ports.length;j++){
        var cam = cams[i];
        var is_lwir = cam.ports[j].bits > 8;
        //var img_src = 'http://'+cam.ip+':'+cam.ports[j].port+'/img?'+ts;
        var hst_src = 'http://'+cam.ip+'/pnghist.cgi?sensor_port='+cam.ports[j].index+'&sqrt=1&scale=5&average=5&height=128&fillz=1&linterpz=0&draw=2&colors=41&_time='+ts;

        var elem = $("#display_previews").find("table[ip=\'"+cam.ip+"\']");
        //elem.find(".port_preview[index="+j+"]").attr('src',img_src);

        if (!cam.ports[j].preview){
          //console.log("preview does not exist");
          var jp4prev = elem.find(".port_preview[index="+j+"]");
		  var imgsrv_img = "/img";
		  if (is_lwir){
			  imgsrv_img = "/tiff_palette=2/tiff_telem=1/tiff_auto=33/tiff_convert/img"; // /bimg";
		  }
          var preview = jp4prev.jp4({
            //ip: cam.ip,
            //port: cam.ports[j].port,
//            src: "http://"+cam.ip+":"+cam.ports[j].port+"/img",
            src: "http://"+cam.ip+":"+cam.ports[j].port+imgsrv_img,
            width: 200,
            fast: true,
            lowres:4,
            webworker_path: "../js"
          });

          cam.ports[j].preview = preview;

        }else{

          cam.ports[j].preview.data.refresh();

        }
        //console.log(jp4prev.data.getAddr());
		if (!is_lwir) {
            elem.find(".hist_preview[index="+j+"]").attr('src',hst_src);
        }
      }
    }
  }

}

function blinkBackground(elem) {
  $(elem).css('background-color', 'rgb(255,230,230)');
  setTimeout( function(){ $(elem).css('background-color', 'rgb(240,240,240)');}, 500);
}
function blankBackground(elem) {
  $(elem).css('background-color', 'rgb(255,255,255)');
  setTimeout( function(){ $(elem).css('background-color', 'transparent');}, 600); // little longer than blink
}


function refresh_status(){
 // console.log("---------------------refresh_status(), refresh_en = "+refresh_en);
  if (!refresh_en){
	  return;
  } 
  blinkBackground($('#display_status'));
  
  for(var i=0;i<cams.length;i++){
    if (cams[i].init){
      check_camogm_status(i);
    }
  }
}

function init_rec_button(){

  $("#rec_button").on('click',function(){
//      recording = !recording;
//      rec_button_switch(recording);
      if (recording){ // simple, always works
        url = "camogm_interface.php?cmd=stop";
	    multi_ajax(url,function(res){
	      console.log(this.ip+": rec = "+recording);
	    });
        recording = 0;
        rec_button_switch(recording);
        return;
      }else{ // wants to turn on recording, needs tests
		// check that all cameras have mounted partitions
		for(var i=0;i<cams.length;i++){
		  if (!cams[i].mounted){
		    console.log("Camera "+i+" is not mounted, can not start recording");
		    return; // do nothing, button will not activate
		  }
		}
        recording = 1;
        rec_button_switch(recording);
		// mkdir for recording
		url = "camogm_interface.php?cmd=dir_prefix&name=" + multicam_dir;
	    multi_ajax(url,function(){
       		console.log("starting recording in the camera "+ this.ip+" to " + multicam_dir); 
       		// launch recording 
       	  $.ajax({
            url: "http://"+this.ip+"/"+"camogm_interface.php?cmd=start",
            ip: this.ip,
            success: function(res){
               console.log(this.ip+": rec = "+recording); // got false on 41?
//               recording = 1;
//               rec_button_switch(recording);
               
           } // multi_ajax function
          }); // ajax 
        }); //multi_ajax
      } // if (recording) else
  }); // on.("click")
} // init_rec_button



function rec_button_switch(state){

  if (state){
    $("#rec_button").addClass("rec_outer_active");
    //$(".rec_inner").addClass("rec_inner_running");
    rec_running(true);
  }else{
    $("#rec_button").removeClass("rec_outer_active");
    //$(".rec_inner").removeClass("rec_inner_running");
    rec_running(false);
  }

}


function rec_running(state){
  if (state){
    rec_running_intvl = setInterval(rec_intvl,500);
    $(".rec_inner").fadeOut(0);
  }else{
    clearInterval(rec_running_intvl);
    $(".rec_inner").fadeIn(0);
  }
}

function rec_intvl(){

  $(".rec_inner").fadeToggle(100);

}

// create tables for previews
// but they are invisible until data comes in
function addrs_create_tables(){

  $("#display_status").html([
    '<table>',
    '<tr>',
    '  <th>ip</th>',
    '  <th>device</th>',
    '  <th>free space</th>',
    '  <th>errors</th>',
    '</tr>',
    '</table>'
  ].join('\n'));

  for(var i=0;i<cams.length;i++){
    var tbl = [
      '<div>',
      '  <table ip=\''+cams[i].ip+'\'>',
      '  </table>',
      '</div>'
    ].join('\n');
    $("#display_previews").append($(tbl));

    var tbl_row = [
      '<tr ip=\''+cams[i].ip+'\'>',
      '</tr>'
    ].join('\n');

    $("#display_status").find("table").append($(tbl_row));
  }
}

function addrs_ips2addrs(){
  $("#addrs").html("");
  for(var i=0;i<cams.length;i++){
    $("#addrs").append($('<div>').html(cams[i].ip));
  }
}

function addrs_ips2str(){
  var arr = [];
  var str = "";

  for(var i=0;i<cams.length;i++){
    arr.push(cams[i].ip);
  }

  str = arr.join(",");
  return str;
}

function addrs_ips2ta(){

  var arr = [];
  var str = "";

  for(var i=0;i<cams.length;i++){
    arr.push(cams[i].ip);
  }

  str = arr.join("\n");

  $("#eai_text").val(str);
  addrs_ips2addrs();

}

function addrs_ta2ips(){

  var str = $("#eai_text").val();
  addrs_str2ips(str);
  addrs_ips2addrs();
}

function addrs_str2ips(str){
  cams = [];
  var tmp_ips = str.split("\n");
  for(var i=0;i<tmp_ips.length;i++){
    str = tmp_ips[i].replace(/,|;|^\s+|\s+$/gm,'');
    if (str!==""){
      cams.push(new Camera({ip:str}));
    }
  }
}

function addrs_mark_bad_ip(ip){

  $("#addrs").find("div").each(function(){
    if ($(this).html()==ip){
      $(this).css({color:"red"});
      $(this).attr("title","N/A");
    }
  });

}

function init_awb_toggle(){
  $('#toggle_awb').click(function() {
    if ($(this).find('.btn.active').html()=="ON"){
      wb_en = 0;
    }else{
      wb_en = 1;
    }
    button_switch($(this),wb_en);

    // will it work without port 0?
    url = "parsedit.php?immediate&sensor_port=0&WB_EN="+wb_en+"&*WB_EN=0xf";

    multi_ajax(url,function(res){
      console.log(this.ip+": awb "+wb_en);
    }, 'mt9p006');

  });
}

// on or off
function button_switch(btn,state){
  if (state==1){
    if (btn.find('.btn.active').html()=="OFF"){
      btn.find('.btn.active').toggleClass('btn-danger');
      // toggle active
      btn.find('.btn').toggleClass('active');
      btn.find('.btn.active').toggleClass('btn-success');
      btn.find('.btn').toggleClass('btn-default');
    }
  }

  if (state==0){
    if (btn.find('.btn.active').html()=="ON"){
      btn.find('.btn.active').toggleClass('btn-success');
      // toggle active
      btn.find('.btn').toggleClass('active');
      btn.find('.btn.active').toggleClass('btn-danger');
      btn.find('.btn').toggleClass('btn-default');
    }
  }

}

function init_aexp_toggle(){
  $('#toggle_aexp').click(function() {
    console.log("toggle_aexp(): aexp "+aexp_en);
    if ($(this).find('.btn.active').html()=="ON"){
      aexp_en = 0;
    }else{
      aexp_en = 1;
    }
    button_switch($(this),aexp_en); // just repeats aexp_en
    updateAexpExpQuality();
  });
  // init onchange quality, exposure:

  $('#exposure_ms').change(function() {
    updateAexpExpQuality();
    console.log("change exposure_ms " + exposure_ms);
  });
  console.log("init change exposure_ms");
  
  $('#jpeg_quality').change(function() {
    updateAexpExpQuality();
    console.log("change jpeg_quality: " + quality);
    
  });
  console.log("init change jpeg_quality");
}

function updateAexpExpQuality(){
//    button_switch($(this),aexp_en); // just repeats aexp_en
    // will it work without port 0?
    // get exposure from field
    exp_ms = $('#exposure_ms').val();
    var exp_us = Math.round(1000*exp_ms);
    quality = Math.round($('#jpeg_quality').val());
    var exp_us = Math.round(1000*exp_ms);
    url = "parsedit.php?immediate&sensor_port=0&AUTOEXP_ON="+aexp_en+"&*AUTOEXP_ON=0xf&EXPOS="+exp_us+"&*EXPOS=0xf&QUALITY="+quality+"&*QUALITY=0xf";
    console.log("updateAexpExpQuality(): url="+url);
    multi_ajax(url,function(res){
      console.log(this.ip+":  "+aexp_en);
    }, 'mt9p006');
}


// TODO: Split to send exposure on change and quality

function multi_ajax(url,callback,sensor_type=""){
	console.log ("multi_ajax(): url="+url+", sensor_type="+sensor_type);
  for(var i=0;i<cams.length;i++){
    if (cams[i].status){
	  if ((sensor_type == "") || (sensor_type == cams[i].ports[0].sensor_type)) { 
        $.ajax({
          url: "http://"+cams[i].ip+"/"+url,
          ip: cams[i].ip,
          success: callback
        });
      }
    }
  }
}

function initBosonFFC(){
    console.log("initBosonFFC()");
	for (var ffc_name in boson_ffc) {
		console.log(ffc_name+" = " + boson_ffc[ffc_name]);
		$('#'+ffc_name).change(function() {
		    updateBosonFFC($(this).attr('id'));
		});
		console.log("initBosonFFC(), "+ffc_name);
	}
}

function updateBosonFFC(ffc_name) {
//	ffc_name=$(this).attr('id');
	var in_type = $('#'+ffc_name).attr("type");
	boson_ffc[ffc_name] = (in_type == 'text') ? ($('#'+ffc_name).val()): (($('#'+ffc_name).is(':checked'))?1:0);
	console.log ("updateBosonFFC(): ffc_name="+ffc_name+": "+boson_ffc[ffc_name]+" val="+($('#'+ffc_name).val()+" type="  +($('#'+ffc_name).attr("type"))  ));
	var sensor_reg = "SENSOR_REGS"+BOSON_FFCS_REG[ffc_name];
	url = "parsedit.php?immediate&sensor_port=0&"+sensor_reg+"="+boson_ffc[ffc_name]+"&*"+sensor_reg+"=0xf";
    console.log("updateBosonFFC(): url="+url);
    multi_ajax(url,function(res){
      console.log(this.ip+": "+url);
    }, 'boson640');
    // TODO: add FFC command after changes? Need to spread not to overpower
    
}



var pointers = [];

function init_snapshot_button(){

  $("#snapshot").on('click',function(){
    //reset pointers
    pointers = [];
    // get combined diagnostcs - need timestamps and pointers
    multi_ajax("../diagnostics.php",function(data){
      pointers.push($(data));
      // when everything is collected
      if (pointers.length==cams.length){
        //find the latest
        snapshot_download_all(pointers);
      }
    }, 'mt9p006');
   // download images
  });

}

function snapshot_find_latest_ts(ptrs){

  // loop through the 1st one backwards
  var c = $($(ptrs[0]).find('timestamps')[0]).find('ts');

  var total_ports = 0;
  for(var i=0;i<ptrs.length;i++){

    let port_counter = 0;
    let ports = $(ptrs[i]).find('port');
    for(p of ports){
        let sensors = $(p).attr("sensor").replace(/\s/g,'').split(",");
        if (!sensors.every(v => v==='none')){
            port_counter++;
        }
    }
    total_ports += port_counter;

  }

  var ts = [];
  var pattern = "";
  var common_ts_found = false;

  for(var i=c.length-1;i>=0;i--){
    var pattern = c[i].innerText;
    var counter = 0;
    for(var j=0;j<ptrs.length;j++){
      var l = $(ptrs[j]).find('ts[ts=\''+pattern+'\']').length;
      counter += l;

    }
    if (counter==total_ports){
      common_ts_found = true;
      break;
    }
  }

  if (common_ts_found){
    console.log("Downloading "+pattern);
    return pattern;
  }else{
    console.log("ERROR: didn't find a common timestamp among cameras and ports");
    return false;
  }

}

var ZW;
var zip_filename = "zip.zip";

var blobs = [];
var filenames = [];

var snapshot_counter = 0;
var zip_counter = 0;
var blob_coounter = 0;

function snapshot_download_all(ptrs){

  // create zipWriter
  /*
  zip.createWriter(new zip.BlobWriter("application/zip"),function(zipWriter){

    ZW = zipWriter;
  });
  */

  var ts = snapshot_find_latest_ts(ptrs);
  if (ts){

    zip_filename = ts.replace(/\./ig,"_")+".zip";

    snapshot_counter = 0;
    zip_counter = 0;
    blob_counter = 0;
    filenames = [];
    blobs = [];

    for(var i=0;i<ptrs.length;i++){
        var ip = $(ptrs[i]).find('camera').attr('ip');
        var bchn = get_base_channel(ip);
        //console.log(ip+": base channel = "+base_chn);
        var buf_pointers = $(ptrs[i]).find('ts[ts=\''+ts+'\']');

        for(var j=0;j<cams[i].ports.length;j++){
          // everything is ordered
          var port = cams[i].ports[j].port;
          var pointer = $(buf_pointers[j]).attr('ptr');
          var url = "http://"+ip+":"+port+"/"+pointer+"/timestamp_name/bchn"+bchn+"/bimg";
          snapshot_download_single(url);
        }
    }
  }

}

function get_base_channel(ip){
  var base = 0;
  for(var i=0;i<cams.length;i++){
    if (cams[i].ip==ip){
      break;
    }else{
      base += cams[i].ports.length;
    }
  }
  return base;
}

// from snapshot.js
function snapshot_download_single(addr){

  snapshot_counter++;

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
      filename = filename_from_content_disposition(filename);

      // store in case zip cannot zip
      filenames.push(filename);
      blobs.push(http.response);

      blob_counter++;
      //var blob = http.response;
      //blob.type = "image/jpeg";

      if (blob_counter==snapshot_counter){
        zip_blobs();
        //add_test_blob_to_zip();
      }

      //pass_to_file_reader(filename,http.response);

    }

  }
  http.send();

}

function add_test_blob_to_zip(){

  var textblob = new Blob(
    ["Lorem ipsum dolor sit amet, consectetuer adipiscing elit..." ],
    {type : "text/plain"}
  );

  ZW.add("test.txt",new zip.BlobReader(textblob),function(){
    ZW.close(function(zippedBlob){
      pass_to_file_reader(zip_filename,zippedBlob);
    });
  });

}

function zip_blobs(){

  zip.createWriter(new zip.BlobWriter("application/zip"), function(writer) {
      var f = 0;
      function nextFile(f) {
          fblob = blobs[f];
          writer.add(filenames[f], new zip.BlobReader(fblob), function() {
              // callback
              f++;
              if (f < filenames.length) {
                  nextFile(f);
              } else close();
          });
      }

      function close() {
          // close the writer
          writer.close(function(blob) {
              // save with FileSaver.js
              pass_to_file_reader(zip_filename,blob);
          });
      }

      nextFile(f);

  }, onerror);

  /*
  for(var i=0;i<filenames.length;i++){
    // add to zip writer
    ZW.add(filenames[i],new zip.BlobReader(blobs[i]),function(){
      zip_counter++;
      if (snapshot_counter==zip_counter){
        ZW.close(function(zippedBlob){
          // right befre closing
          // save zip
          pass_to_file_reader(zip_filename,zippedBlob);
        });
      }
    });
  }
  */

}

function filename_from_content_disposition(str){
  var parameters = str.split(";");
  for (var i=0;i<parameters.length;i++) parameters[i]=parameters[i].split("=");
  for (var i=0;i<parameters.length;i++) {
    if (parameters[i][0].trim()=="filename"){
      str = parameters[i][1].replace(/"/ig,"");
      str = str.trim();
    }
  }
  return str;
}

// from here:
//   https://diegolamonica.info/multiple-files-download-on-single-link-click/
//   http://jsfiddle.net/diegolamonica/ssk8z9pa/
//   (helped a lot) https://stackoverflow.com/questions/19327749/javascript-blob-filename-without-link
function pass_to_file_reader(filename,fileblob){

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
//console.log(cams[this.cam_i].ip+": camogm is on");
var collapsible_elements;
function initCollapsibleElements_OLD() {
//	console.log("+++++ $('.collapsible').length="+$('.collapsible').length);
//	collapsible_elements = document.getElementsByClassName("collapsible");
	collapsible_elements = $('.collapsible');
	console.log("+++++ collapsible_elements.length="+collapsible_elements.length);
	var i;
	for (i = 0; i < collapsible_elements.length; i++) {
	  collapsible_elements[i].addEventListener("click", function() {
	    this.classList.toggle("active");
	    var content = this.nextElementSibling;
	    if (content.style.display === "block") {
	      content.style.display = "none";
	    } else {
	      content.style.display = "block";
	    }
	  });
	}
}

function initCollapsibleElements() {
	collapsible_elements = $('.collapsible');
	console.log("+++++ collapsible_elements.length="+collapsible_elements.length);
	var i;
	for (i = 0; i < collapsible_elements.length; i++) {
	  $(collapsible_elements[i]).click(function(){
	    this.classList.toggle("active");
	    var content = this.nextElementSibling;
	    if (content.style.display === "none") {
	      content.style.display = "block";
	    } else {
	      content.style.display = "none";
	    }
	  });
	}
}




function init_multicam_controls(){
  refresh_en = 1;
  button_switch($('#toggle_refresh'),refresh_en);
  startStopRefresh();
  record_en =  0;
  button_switch($('#toggle_record'),record_en);
  // set input values
  $('#dir_name').val(multicam_dir);
  $('#ref_rate').val(multicam_rperiod);
  $('#sref_rate').val(multicam_speriod);

  $('#toggle_refresh').click(function() {
    if ($(this).find('.btn.active').html()=="ON"){
      refresh_en = 0;
    }else{
      refresh_en = 1;
    }
    button_switch($(this),refresh_en);
    startStopRefresh();

    // Add actual functionality
  });
  $('#toggle_record').click(function() {
    if ($(this).find('.btn.active').html()=="ON"){
      record_en = 0;
    }else{
      record_en = 1;
    }
    button_switch($(this),record_en);
    // Add actual functionality
  });
  
  $('#dir_name').change(function() {
	  multicam_dir = $(this).val();
      console.log("dir_name="+multicam_dir );
      updateConfifs();
      setDirMulti();
  });
  
  $('#ref_rate').change(function() {
	  multicam_rperiod = parseFloat($(this).val());
      console.log("ref_rate="+multicam_rperiod );
      updateConfifs();
  });

  $('#sref_rate').change(function() {
	  multicam_speriod = parseFloat($(this).val());
      console.log("sref_rate="+multicam_speriod );
      updateConfifs();
  });
  
  console.log('init_multicam_controls() DONE');
}


function updateConfifs(){ // write modified settings to the master camera persistent storage
  // what can be done before configuring cameras (ip or ajax) and ports (init2)	
  $.ajax({
	  url: "multicam2.php?cmd=update&multicam_dir="+multicam_dir+
	  "&multicam_rperiod="+multicam_rperiod+"&multicam_speriod="+multicam_speriod,
	  success: function(){
		console.log("Written configs to the master camera");
	  }
  });
}

function setDirMulti(){
//    url = "camogm_interface.php?cmd=dir_prefix&name=" + multicam_dir;
    url = "camogm_interface.php?cmd=set_prefix&name=" + multicam_dir; // can not mkdir before mounted
    console.log("setDirMulti(), url="+url);
    multi_ajax(url,function(){
    	console.log(this.ip+": dir_prefix " + multicam_dir);
    });
}

var appply_pending = 0;
function setDirSingle(cam_i){ // set recording directory for one camera (after starting camogm), request status 
  console.log("csetDirSingle("+cam_i+")");
  $.ajax({
//    url: "http://"+cams[cam_i].ip+"/camogm_interface.php?cmd=dir_prefix&name=" + multicam_dir, // should never be done before partition is mounted!
    url: "http://"+cams[cam_i].ip+"/camogm_interface.php?cmd=SET_prefix&name=" + multicam_dir, // can not mkdir before mounted
    cam_i: cam_i,
    success: function(){
        console.log(cams[this.cam_i].ip+": set directory to " + multicam_dir);
        check_camogm_status(this.cam_i);
    }
  });
}

// LWIR16
/*
function LWIR16_sendStatusRequest(){
    var url = "http://192.168.0.41/lwir16/lwir16.php?daemon=status";
//    if (apply_pending) { // TODO: implement
//       var mod_pars =   modParameters();
//        for(const p in mod_pars) {
//            url +="&"+p+"="+mod_pars[p];
//        }
//        update_editable = true; // will update edited fields
//        apply_pending = false;
//    }
  $.ajax({
    url: url,
    success: function(){ // was parseStatusResponse()
        console.log(cams[this.cam_i].ip+": set directory to " + multicam_dir);
        check_camogm_status(this.cam_i);
    }
  }).fail(function(data,status){
      console.log("LWIR16_sendStatusRequest() failed. Check errors");
      // will be checked when sending requests
    });
}

function parseStatusResponse(resp, update_editable){
  var result = "";
//  console.log(resp);
  if (update_editable) {
    if (resp.getElementsByTagName("pre_delay").length!=0){
        pre_delay = parseFloat(resp.getElementsByTagName("pre_delay")[0].childNodes[0].nodeValue);
        document.getElementById("idpre_delay").value = pre_delay;
    }
    
    if (resp.getElementsByTagName("duration").length!=0){
        duration = parseInt(resp.getElementsByTagName("duration")[0].childNodes[0].nodeValue);
        document.getElementById("idduration").value = duration;
    }
    
    if (resp.getElementsByTagName("duration_eo").length!=0){
        duration_eo = parseInt(resp.getElementsByTagName("duration_eo")[0].childNodes[0].nodeValue);
        document.getElementById("idduration_eo").value = duration_eo;
    }

    if (resp.getElementsByTagName("ffc").length!=0){
        ffc = parseInt(resp.getElementsByTagName("ffc")[0].childNodes[0].nodeValue);
        document.getElementById("idffc").checked = ffc > 0;
    }

    if (resp.getElementsByTagName("ffc_period").length!=0){
        ffc_period = parseFloat(resp.getElementsByTagName("ffc_period")[0].childNodes[0].nodeValue);
        document.getElementById("idffc_period").value = ffc_period;
    }

    if (resp.getElementsByTagName("ffc_groups").length!=0){
        ffc_groups = parseInt(resp.getElementsByTagName("ffc_groups")[0].childNodes[0].nodeValue);
        document.getElementById("idffc_groups").value = ffc_groups;
    }
    
    if (resp.getElementsByTagName("ffc_frames").length!=0){
        ffc_frames = parseInt(resp.getElementsByTagName("ffc_frames")[0].childNodes[0].nodeValue);
        document.getElementById("idffc_frames").value = ffc_frames;
    }

    if (resp.getElementsByTagName("compressor_run").length!=0){
        compressor_run = parseInt(resp.getElementsByTagName("compressor_run")[0].childNodes[0].nodeValue);
        document.getElementById("idcompressor_run").checked = compressor_run > 0;
    }
    
    if (resp.getElementsByTagName("debug").length!=0){
        debug = parseInt(resp.getElementsByTagName("debug")[0].childNodes[0].nodeValue);
        document.getElementById("iddebug").value = debug;
    }
  }
  if (resp.getElementsByTagName("sequence_num").length!=0){
  	sequence_num = parseInt(resp.getElementsByTagName("sequence_num")[0].childNodes[0].nodeValue);
  	document.getElementById("idsequence_num").value = sequence_num;
  }

  if (resp.getElementsByTagName("last_ffc").length!=0){
  	last_ffc = parseFloat(resp.getElementsByTagName("last_ffc")[0].childNodes[0].nodeValue);
  	document.getElementById("idlast_ffc").value = last_ffc;
  }
  
  if (resp.getElementsByTagName("time_to_ffc").length!=0){
  	time_to_ffc = parseFloat(resp.getElementsByTagName("time_to_ffc")[0].childNodes[0].nodeValue);
  	document.getElementById("idtime_to_ffc").value = time_to_ffc;
  }
  
  if (resp.getElementsByTagName("capture_run").length!=0){
  	capture_run = parseInt(resp.getElementsByTagName("capture_run")[0].childNodes[0].nodeValue);
  	document.getElementById("idcapture_run").checked = capture_run > 0;
  	if (update_editable) {
        document.getElementById("idStartStop").innerHTML=capture_run?"Stop":"Start";
        document.getElementById("idStartStop").disabled = false;
        want_run = capture_run;
  	}
  }
  if (update_editable) {
       document.getElementById('idApply').innerHTML='Apply';
       document.getElementById('idApply').disabled= false;
       document.getElementById('idRestart').innerHTML='Restart';
       document.getElementById('idRestart').disabled= false;
       
  }
  request_num++;
  document.getElementById("idrequest_num").value = request_num;
  update_editable = false;
  sendStatusRequest();
}
*/





