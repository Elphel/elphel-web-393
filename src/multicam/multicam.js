
// port class
var Port = function(options){

  var defaults = {
    port: 0,
    index: 0,
    awb:  NaN,
    aexp: NaN,
    preview: null
  };

  this._data = $.extend(defaults,options);

  this.port    = this._data.port;
  this.index   = this._data.index;
  this.awb     = this._data.awb;
  this.aexp    = this._data.aexp;
  this.preview = this._data.preview;

}

// camera class
var Camera = function(options){

  var defaults = {
    ip: "",
    init: false,
    status: false,
    camogm: false,
    recording: false,
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
var ips_from_url = false;

var cams = [];

var wb_en = 1;
var aexp_en = 1;


$(function(){
  init();
});

function parseURL(){

    var ips_str = location.host;

    var parameters=location.href.replace(/\?/ig,"&").split("&");
    for (var i=0;i<parameters.length;i++) parameters[i]=parameters[i].split("=");
    for (var i=1;i<parameters.length;i++) {
        switch (parameters[i][0]) {
            case "ip":
              //ips_from_url = true;
              ips_str = parameters[i][1];
              ips_str = ips_str.replace(/,|;/gm,'\n');
              break;
        }
    }

    // force url
    addrs_str2ips(ips_str);
    ips_from_url = true;

}

function init(){

  zip.workerScriptsPath = "../js/zip/";

  parseURL();

  if (!ips_from_url){
    //get config
    $.ajax({
      url: "multicam.php?cmd=read",
      success: function(data){
        var addrs = $(data).find('camera');
        for(var i=0;i<addrs.length;i++){
          cams.push(new Camera({ip:$(addrs[i]).text()}));
        }
        init2();
      }
    });
  }else{
    init2();
  }

  init_rec_button();

  //
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

  $("#display").css({
    position:'absolute',
    top: '2px',
    left: $("#settings").find("table").width()+10
  });

  init_test_button();
  init_snapshot_button();

}

function init2(){
  addrs_ips2ta();
  // create tables
  addrs_create_tables();
  // now get the ports
  get_ports();

  init_awb_toggle();
  init_aexp_toggle();
}

function get_ports(){

  for(var i=0;i<cams.length;i++){

    $.ajax({
      url: "http://"+cams[i].ip+"/multicam/multicam.php?cmd=ports",
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
            var tmp_port = new Port({port:  $(this).attr("port"), index: $(this).attr("index")});
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
        check_camogm_status(this.cam_i);
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
      check_camogm_status(this.cam_i);
    }
  });

}

function check_camogm_status(cam_i){

  // run_status does not interact with camogm, quickest response
  $.ajax({
    url: "http://"+cams[cam_i].ip+"/camogm_interface.php?cmd=status",
    cam_i: cam_i,
    success: function(res){

      var cam = cams[this.cam_i];

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
    console.log("status request failed");
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
      console.log("WARNING: some camogms are running, some are stopped");
    }

    if (all_ready_init_run){

      // display like it's running
      if (any_running){
        console.log("Turn on recording");
        recording = true;
        rec_button_switch(recording);
      }

      refresh_previews_intvl = setInterval(refresh_previews,2000);
      refresh_status_intvl   = setInterval(refresh_status,2000);
    }

    all_ready_init_run = false;

  }

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
    '      <td>'+cam.ip+':</td>',
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

  var ts = Date.now();

  for(var i=0;i<cams.length;i++){
    if (cams[i].init){
      for(var j=0;j<cams[i].ports.length;j++){
        var cam = cams[i];
        //var img_src = 'http://'+cam.ip+':'+cam.ports[j].port+'/img?'+ts;
        var hst_src = 'http://'+cam.ip+'/pnghist.cgi?sensor_port='+cam.ports[j].index+'&sqrt=1&scale=5&average=5&height=128&fillz=1&linterpz=0&draw=2&colors=41&_time='+ts;

        var elem = $("#display_previews").find("table[ip=\'"+cam.ip+"\']");
        //elem.find(".port_preview[index="+j+"]").attr('src',img_src);

        if (!cam.ports[j].preview){
          //console.log("preview does not exist");
          var jp4prev = elem.find(".port_preview[index="+j+"]");
          var preview = jp4prev.jp4({
            ip: cam.ip,
            port: cam.ports[j].port,
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

        elem.find(".hist_preview[index="+j+"]").attr('src',hst_src);
      }
    }
  }

}

function refresh_status(){
  for(var i=0;i<cams.length;i++){
    if (cams[i].init){
      check_camogm_status(i);
    }
  }
}

function init_rec_button(){

  $("#rec_button").on('click',function(){
      recording = !recording;
      rec_button_switch(recording);
      if (recording){
        url = "camogm_interface.php?cmd=start";
      }else{
        url = "camogm_interface.php?cmd=stop";
      }
      multi_ajax(url,function(res){
        console.log(this.ip+": rec = "+recording);
      });
      //http://{$unique_cams[$i]['ip']}/camogm_interface.php?cmd=start

  });

}

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

var rec_running_intvl;
var refresh_status_intvl;
var refresh_previews_intvl;

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
    });

  });
}

// on or off
function button_switch(btn,state){

  //btn = $('#toggle_awb');

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

    if ($(this).find('.btn.active').html()=="ON"){
      aexp_en = 0;
    }else{
      aexp_en = 1;
    }

    button_switch($(this),aexp_en);

    // will it work without port 0?
    url = "parsedit.php?immediate&sensor_port=0&AUTOEXP_ON="+aexp_en+"&*AUTOEXP_ON=0xf";

    multi_ajax(url,function(res){
      console.log(this.ip+": aexp "+aexp_en);
    });

  });
}

function multi_ajax(url,callback){

  for(var i=0;i<cams.length;i++){
    if (cams[i].status){

      $.ajax({
        url: "http://"+cams[i].ip+"/"+url,
        ip: cams[i].ip,
        success: callback
      });

    }
  }

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
    });
   // download images
  });

}

function snapshot_find_latest_ts(ptrs){

  // loop through the 1st one backwards
  var c = $($(ptrs[0]).find('timestamps')[0]).find('ts');

  var total_ports = 0;
  for(var i=0;i<ptrs.length;i++){
    total_ports += $(ptrs[i]).find('port').length;
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











