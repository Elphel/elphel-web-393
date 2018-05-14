
$(function(){
  init();
});

function parseURL(){
    var parameters=location.href.replace(/\?/ig,"&").split("&");
    for (var i=0;i<parameters.length;i++) parameters[i]=parameters[i].split("=");
    for (var i=1;i<parameters.length;i++) {
        switch (parameters[i][0]) {
            case "ip":
              ips_from_url = true;
              ips_str = parameters[i][1];
              ips_str = ips_str.replace(/,|;/gm,'\n');
              addrs_str2ips(ips_str);
              break;
        }
    }
}

function init(){
  parseURL();
  if (ips.length==0){
    ips.push(location.host);
  }
  for(var i=0;i<ips.length;i++){
    get_system_info(ips[i]);
  }
}

var ips = [];
var sysinfo = [];

function addrs_str2ips(str){
  var tmp_ips = str.split("\n");
  for(var i=0;i<tmp_ips.length;i++){
    str = tmp_ips[i].replace(/,|;|^\s+|\s+$/gm,'');
    if (str!==""){
      ips.push(str);
    }
  }
}


function get_system_info(ip){

  $.ajax({
    url: "http://"+ip+"/diagnostics.php",
    ip: ip,
    success:function(res){

      sysinfo.push(res);

      if (sysinfo.length==ips.length){
        analyze_sysinfo();
      }

    }
  }).fail(function(e){
    console.log("ERROR: Failed to get system info for "+this.ip);
    sysinfo.push("");
  });

}

function analyze_sysinfo(){

  // filter out empty
  for(var i=0;i<sysinfo.length;i++){
    if (sysinfo[i]==""){
      sysinfo.splice(i,1);
    }
  }

  for(var i=0;i<sysinfo.length;i++){
    var f = $(sysinfo[i]);
    var tr = [
      '<tr>',
      '  <td>'+f.find("camera").attr('ip')+'</td>',
      '  <td title=\''+f.find("systime").text()+'\'>'+f.find("systimestamp").text()+'</td>',
      '  <td>'+f.find("uptime").text()+'</td>',
      '  <td class=\'right\'>'+parse_temperature(f.find("temperature"))+'</td>',
      '  <td class=\'right\'>'+parse_storage(f.find("storage"))+'</td>',
      '  <td class=\'right\'>'+parse_recorder(f.find("recorder").text())+'</td>',
      '  <td class=\'right\'>'+f.find("master_port").text()+'</td>',
      '  <td class=\'right\'>'+parse_gps(f.find("gps"))+'</td>',
      '  <td class=\'right\'></td>',
      '',
      '</tr>'
    ].join('\n');

    $("#gen_table").append(tr);

    var colspan = $("#pars_table").find("th").length;

    var tr2 = [
      '<tr><td style=\'background:rgba(240,240,240,0.5);\' class=\'left\' colspan=\''+colspan+'\'><b>'+f.find("camera").attr('ip')+'</b></td></tr>'
    ];

    var ports = f.find('port');

    for(var j=0;j<ports.length;j++){
      tr2.push(parse_port(ports[j]));
    }

    $("#pars_table").append(tr2.join('\n'));

  }

  parse_timestamps();

}

function parse_timestamps(){

  var thead = [];
  thead.push('<tr>');
  thead.push([
    '<th>port</th>',
    '<th title=\'Frames period matches programmed trigger period\'>period</th>',
    '<th title=\'Period is not uniform?\'>skipped</th>',
    '<th title=\'In sync with other ports/cameras\'>sync</th>',
    '<th title=\'Timestamps data, mouse over\'>data</th>',
  ].join('\n'));
  //for(var j=0;j<ports.length;j++){
  //  thead.push('<th>p</th><th>frame</th><th>timestamp</th>');
  //}
  thead.push('</tr>');

  $("#ts_table").append(thead);

  for(var i=0;i<sysinfo.length;i++){
    var f = $(sysinfo[i]);
    var ports = f.find('port');
    var colspan = $("#ts_table").find("th").length;

    var tr3 = [
      '<tr><td style=\'background:rgba(240,240,240,0.5);\' class=\'left\' colspan=\''+colspan+'\'><b>'+f.find("camera").attr('ip')+'</b></td></tr>'
    ];

    tr3.push('<tr>');

    for(var j=0;j<ports.length;j++){
      tr3.push(pt_parse_port(i,ports[j]));
    }

    tr3.push('</tr>');

    $("#ts_table").append(tr3.join('\n'));

  }

  // analyze, search forware
  analyze_timestamps();

}

// timetamps
var BAT = [];
// frame numbers
var BAF = [];
// trig periods, in seconds
var BAP = [];
var TOTAL_PORTS = 0;
var RES = [];

function analyze_timestamps(){

  // create an array
  for(var i=0;i<sysinfo.length;i++){
    var f = $(sysinfo[i]);
    var ports = f.find('port');
    BAT[i] = [];
    BAF[i] = [];
    BAP[i] = [];
    TOTAL_PORTS += ports.length;
    for(var j=0;j<ports.length;j++){
      var ts = $(ports[j]).find('ts');
      BAP[i][j] = pp_calc_trig_period($(ports[j]).find('trig_period').text());
      BAT[i][j] = [];
      BAF[i][j] = [];
      for(var k=0;k<ts.length;k++){
        BAT[i][j][k] = $(ts[k]).text();
        BAF[i][j][k] = $(ts[k]).attr('frame');
      }
    }
  }

  for(var i=0;i<BAT.length;i++){
    for(var j=0;j<BAT[i].length;j++){
      for(var k=0;k<BAT[i][j].length;k++){
        var res = process_timestamp(BAT[i][j][k],BAF[i][j][k]);
      }
    }
  }

  for(var i=0;i<BAT.length;i++){
    for(var j=0;j<BAT[i].length;j++){
      // RES is ready
      var tmp = process_ts_periods(i,j);
    }
  }

  /*
  var color_inc = 256/(sysinfo.length*TOTAL_PORTS);

  for(var ts in RES){
    var count = RES[ts].count;

    var r = (count==1)?200:0;
    var g = parseInt((count-1)*color_inc);
    var b = 0;

    color = "rgba("+r+","+g+","+b+",1)";
    //console.log(ts+" "+count+" "+color);
    $(".timestamps[ts='"+ts+"']").css({
      color: color
    });
  }
  */

}

function process_ts_periods(cam_i,port_i){

  var tp    = BAP[cam_i][port_i];
  var tses  = BAT[cam_i][port_i];
  var fnums = BAF[cam_i][port_i];

  var data = [];
  for(var i=0;i<tses.length;i++){
    data.push(fnums[i]+": "+tses[i]);
  }
  $("#tses_c"+cam_i+"p"+port_i).find(".ts_data").html("<span title='"+data.join('\n')+"'>data</span>");

  var diffs = [];
  for(var i=0;i<tses.length-1;i++){
    diffs.push(Math.round((tses[i+1]-tses[i])*1000000)/1000000);
  }

  var count = 0;
  var all_match = true;
  for(var i=0;i<diffs.length;i++){
    if (tp==diffs[i]){
      count++;
    }else{
      if(diffs[i]%tp==0){
        all_match = false;
      }
    }

    if (diffs[i]!=diffs[0]){
      // fps is not uniform
      all_match = false;
    }
  }

  //more than half match
  if (count==diffs.length){
    // print ok
    $("#tses_c"+cam_i+"p"+port_i).find(".ts_period").html("<span title='matches with TRIG_PERIOD'>ok</span>");
  }else{
    // print error
    color = "rgb(230,0,0)";
    $("#tses_c"+cam_i+"p"+port_i).find(".ts_period").html("<span title='not match with TRIG_PERIOD or some frames are skipped' style='color:"+color+";'>error</span>");
  }

  if (all_match){
    $("#tses_c"+cam_i+"p"+port_i).find(".ts_skipped").html("<span title=''>ok</span>");
  }else{
    color = "rgb(230,0,0)";
    $("#tses_c"+cam_i+"p"+port_i).find(".ts_skipped").html("<span title='some frames might be skipped' style='color:"+color+";'>error</span>");

    console.log(diffs);
  }

  // now check tses
  var sync = false;
  for(var i=0;i<tses.length;i++){
    if (RES[tses[i]].count==TOTAL_PORTS){
      sync = true;
    }
  }
  if (sync){
    color = "";
    msg = "ok";
  }else{
    color = "rgb(230,0,0)";
    msg = "error";
    console.log("Printing timestamps stats:");
    console.log(RES);
  }
  $("#tses_c"+cam_i+"p"+port_i).find(".ts_sync").html("<span title='Check debug output' style='color:"+color+"'>"+msg+"</span>");

}

function process_timestamp(ts,fr){

  if (RES[ts]==null){
    RES[ts] = {count: 1, frame:[fr]}
  }else{
    RES[ts].count++;
    RES[ts].frame.push(fr);
  }

}

function pt_parse_port(cn,port){

  var p = $(port);
  var pn = p.attr('index');
  var mux = p.attr('mux');
  var sensors = p.attr('sensor');

  var res = [
    '<tr id=\'tses_c'+cn+'p'+pn+'\'>',
    '  <td class=\'ts_port center\'>'+pn+'</td>',
    '  <td class=\'ts_period center vtop\'></td>',
    '  <td class=\'ts_skipped center vtop\'></td>',
    //'  <td class=\'center vtop\'>'+pt_parse_framenumbers(cn,pn,p.find('ts'))+'</td>',
    //'  <td class=\'center vtop\'>'+pt_parse_timestamps(cn,pn,p.find('ts'))+'</td>',
    '  <td class=\'ts_sync\'></td>',
    '  <td class=\'ts_data\'></td>',
    '</tr>',
  ].join('\n');

  return res;
}

function pt_parse_timestamps(cn,pn,ts){

  res = [];

  for(var i=0;i<ts.length;i++){
    var frame = $(ts[i]).attr('frame');
    var timestamp = $(ts[i]).text();
    res.push("<span class='timestamps' ts='"+timestamp+"' fn='"+frame+"'>"+timestamp+"</span>");
  }

  return res.join('<br/>');
}

function pt_parse_framenumbers(cn,pn,ts){

  res = [];

  for(var i=0;i<ts.length;i++){
    var frame = $(ts[i]).attr('frame');
    var timestamp = $(ts[i]).text();
    res.push("<span class='framenumbers' ts='"+timestamp+"' fn='"+frame+"'>"+frame+"</span>");
  }

  return res.join('<br/>');

}

function parse_port(port){

  var p = $(port);

  var pn = p.attr('index');
  var mux = p.attr('mux');
  var sensors = p.attr('sensor');

  var res = [
    '<tr>',
    '  <td class=\'center\'>'+pn+'</td>',
    '  <td>'+mux+'</td>',
    '  <td>'+sensors+'</td>',
    '  <td class=\'center\'>'+pp_parse_sensor_run(p.find('sensor_run'))+'</td>',
    '  <td class=\'center\'>'+pp_parse_sensor_run(p.find('compressor_run'))+'</td>',
    '  <td class=\'center\'>'+pp_parse_format(p.find('color'))+'</td>',
    '  <td class=\'center\'>'+p.find('quality').text()+'%</td>',
    '  <td class=\'center\'>'+p.find('woi_width').text()+'x'+p.find('woi_height').text()+'</td>',
    '  <td class=\'center\'>'+p.find('trig').text()+'</td>',
    '  <td class=\'center\'>'+p.find('trig_master').text()+'</td>',
    '  <td class=\'center\'>'+pp_parse_trig_period(p.find('trig_period').text(),p.find('expos').text())+'</td>',
    '  <td class=\'center\'>'+pp_parse_trig_p(p.find('trig_out').text())+'</td>',
    '  <td class=\'center\'>'+pp_parse_trig_p(p.find('trig_condition').text())+'</td>',
    '  <td class=\'right\'>'+pp_parse_expos(p.find('expos').text(),p.find('trig_period').text())+'</td>',
    '  <td class=\'center\'>'+pp_parse_gain(p.find('gainr').text())+'</td>',
    '  <td class=\'center\'>'+pp_parse_gain(p.find('gaing').text())+'</td>',
    '  <td class=\'center\'>'+pp_parse_gain(p.find('gainb').text())+'</td>',
    '  <td class=\'center\'>'+pp_parse_gain(p.find('gaingb').text())+'</td>',
    '</tr>'
  ].join('\n');

  return res;

}

function pp_parse_expos(str,period){

  var exp = pp_calc_exposure(str);
  var per = pp_calc_trig_period(period);

  /*
  if (v>100){
    color = "rgb(240,160,0)";
  }
  */

  var color = "";
  if (exp>per*1000){
    color = "rgb(230,0,0)";
  }

  var res = "<span style='color:"+color+";'>"+exp+" ms</span>";
  return res;

}

function pp_parse_gain(str){

  var v = parseInt(str);
  v = v/0x10000;

  color = "";

  if (v>5){
    color = "rgb(240,160,0)";
  }

  res = "<span style='color:"+color+";'>"+v.toFixed(2)+"</span>";

  return res;
}

function pp_parse_trig_p(str){

  var v = parseInt(str);
  return "0x"+v.toString(16);

}

function pp_calc_trig_period(str){
  var clock = 100000000;
  var v = parseInt(str);
  v = v/clock;
  return v;
}

function pp_calc_exposure(str){
  var v = parseInt(str);
  v = v/1000;
  return v;
}

function pp_parse_trig_period(period,str){

  var per = pp_calc_trig_period(period);
  var exp = pp_calc_exposure(str);

  var fps = (1/per);

  if (per<1){
    res = (per*1000)+" ms";
  }else{
    res = per+" s";
  }

  var color = "";
  if (exp>per*1000){
    color = "rgb(230,0,0)";
  }

  res = "<span style='color:"+color+";'>"+res+" ("+fps+" fps)</span>";

  return res;

}

function pp_parse_format(str){
  var fmt = $(str).text();

  color = "";

  if (fmt==5){
    fmt = "jp4";
  }else if (fmt==0){
    color = "rgb(240,160,0)";
    fmt = "jpeg";
  }else{
    color = "rgb(230,0,0)";
    fmt = "else";
  }

  res = "<span style='color:"+color+";'>"+fmt+"</span>";

  return res;

}

function pp_parse_sensor_run(str){

  var v = parseInt($(str).text());

  if (v==2){
    color = "rgb(0,0,0)";
    v = "ok";
  }

  if (v==1){
    color = "rgb(240,160,0)";
    v = "idle";
  }

  if (v==0){
    v = "stopped";
    color = "rgb(230,0,0)";
  }

  res = "<span style='color:"+color+"'>"+v+"</span>";

  return res;

}

function parse_temperature(str){
  temps = ["cpu","b10389","sda","sdb"];
  res = [];
  for(var i=0;i<temps.length;i++){
    res.push(color_temperature(str.find(temps[i]).text(),temps[i]));
  }

  return res.join(', ');
}

function color_temperature(str,title){

  color = "rgb(0,0,0)";

  if (str!="-"){
    var t = parseFloat(str);

    if (t>85){
      color = "rgb(230,0,0)";
    }else if(t>70){
      color = "rgb(240,160,0)";
    }else{
      color = "rgb(0,150,0)";
    }
  }

  temp = "<span title='"+title+"' style='color:"+color+";'>"+str+"&deg;</span>";

  return temp;

}

function parse_storage(str){

  var devs = str.find("device");

  var res = "";

  ds = [];
  for(var i=0;i<devs.length;i++){
    var ds_str = "";

    var devname = $(devs[i]).attr('name');
    var devsize = (parseFloat($(devs[i]).attr('size'))/1024/1024).toFixed(2);
    var parts = $(devs[i]).find("partition");

    ds_str += "<b>"+devname+"("+devsize+"G)</b>: ";

    if (parts.length==0){
      ds_str += "unpartitioned";
    }else{
      ps = []
      for (var j=0;j<parts.length;j++){
        pname = $(parts[j]).attr('name');
        psize = (parseFloat($(parts[j]).attr('size'))/1024/1024).toFixed(2);
        ps.push(pname+"("+psize+"G)");
      }
      ds_str += ps.join(', ');
    }

    ds.push(ds_str);

  }

  res = ds.join('; ');

  return res;

}

function parse_recorder(str){

  color = "rgb(0,0,0)";

  if (str!="on"){
    color = "rgb(230,0,0)";
  }

  res = "<span style='color:"+color+";'>"+str+"</span>";

  return res;

}

function parse_gps(str){
  var lat = $(str).find("lat");
  var lon = $(str).find("lon");

  var na = $(str).text();


  if ((lat.length==0)||(lon.length==0)){
    res = "<span style='color:rgb(230,0,0)'>"+na.toLowerCase()+"</span>";
  }else{
    res = "<span>"+$(lat).text()+", "+$(lon).text()+"</span>";
  }

  return res;
}




