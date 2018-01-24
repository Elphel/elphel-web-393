/**
 * @file jquery-jp4.js
 * @brief a jquery plugin to convert jp4/jp46 into human viewable format
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

(function ( $ ) {

  //https://gist.github.com/leolux/c794fc63d9c362013448
  var JP4 = function(element,options){

    var elem = $(element);
    var obj = this;

    var settings = $.extend({
      ip: "",
      port: "",
      image: "test.jp4",
      fromhtmlinput: false,
      refresh: false,
      mosaic: [["Gr","R"],["B" ,"Gb"]],
      fast: false,
      precise: false,
      lowres: 0, // valid values: 1,2,4,8. 0 to disable
      width: 600,
      channel: "all",
      diff: false,
      chn1: "red",
      chn2: "green",
      ndvi: false,
      webworker_path: "js",
      debug: false,
      callback: function(){
        console.log("callback");
      }
    },options);

    var DEBUG = settings.debug;

    // working time
    var T0;
    var TX;

    var BAYER = settings.mosaic;
    var FLIPV = 0;
    var FLIPH = 0;
    var IMAGE_FORMAT = "JPEG";
    var SATURATION = [0,0,0,0];

    var PIXELS = [];

    // only valid values are allowed otherwise - disable
    if ((settings.lowres!=0)&&(settings.lowres!=1)&&(settings.lowres!=2)&&(settings.lowres!=4)&&(settings.lowres!=8)){
      settings.lowres = 0;
    }

    var cnv_working = $("<canvas>",{id:"working"});
    var cnv_display = $("<canvas>",{id:"display"});

    // hide working canvas
    cnv_working.css({display:"none"});
    /*
    cnv_working.css({
      position:"absolute",
      top: "500px",
      left: "500px"
    });
    */

    elem.append(cnv_working);
    elem.append(cnv_display);

    if (DEBUG){
        TX = Date.now();
        T0 = Date.now();
    }

    if (settings.fromhtmlinput){
        /*
         * if image is being loaded from <input type='file'>
         * make sure the image data starts with: "data:image/jpeg;base64,"
         * EXIF.js does not like empty data type: "data:;base64,"
         */
        process_image(settings.image);
    }else{
        send_request();
    }
    //end

    function send_request(){

      var rq = "";

      var http = new XMLHttpRequest();

      if (settings.port!=""&&settings.ip!=""){
        rq = "/get-image.php?ip="+settings.ip+"&port="+settings.port+"&rel=bimg&ts="+Date.now();
        //rq = "get-image.php?ip="+settings.ip+"&port="+settings.port+"&rel=img&ts="+Date.now();
        //settings.refresh = true;
      }else{
        rq = settings.image;
      }

      http.open("GET", rq, true);
      http.responseType = "blob";
      http.onload = function(e) {

        if (DEBUG){
          console.log("#"+elem.attr("id")+", file load time: "+(Date.now()-TX)/1000+" s");
          TX = Date.now();
        }

        if (this.status === 200) {
            var imgdata = URL.createObjectURL(http.response);
            process_image(imgdata);
        }
      };

      http.send();

    }

    this.refresh = function(){
      send_request();
    }

    this.resize = function(w){

      settings.width = w;
      send_request();

    }

    this.setAddr = function(url,port){

      settings.port = port;
      settings.ip = url;

      return 0;

    }

    this.getFormat = function(){

      return this.format;

    }

    this.getAddr = function(){
      return Array(settings.ip,settings.port);
    }

    function process_image(imagedata){

        var canvas = cnv_working;
        //reset format
        IMAGE_FORMAT = "JPEG";

        var heavyImage = new Image();

        heavyImage.onload = function(){

        EXIF.getData(this, function() {

            var cnv_w;
            var cnv_h;

            if (settings.lowres!=0){
                cnv_w = this.width/settings.lowres;
                cnv_h = this.height/settings.lowres;
            }else{
                cnv_w = this.width;
                cnv_h = this.height;
            }

            //update canvas size
            canvas.attr("width",cnv_w);
            canvas.attr("height",cnv_h);

            parseEXIFMakerNote(this);

            canvas.drawImage({
                x:0, y:0,
                source: this,
                width: cnv_w,
                height: cnv_h,
                //source: heavyImage,
                load: redraw,
                sx: 0,
                sy: 0,
                sWidth: this.width,
                sHeight: this.height,
                //scale: scale,
                fromCenter: false
            });
        });

        };
        heavyImage.src = imagedata;

    }

    function redraw(){

      //for debugging
      //IMAGE_FORMAT="JPEG";

      $(this).draw({
        fn: function(ctx){

          if (DEBUG){
            console.log("#"+elem.attr("id")+", raw image drawn time: "+(Date.now()-TX)/1000+" s");
            TX = Date.now();
          }

          if (IMAGE_FORMAT=="JPEG"){

            // if JP4/JP46 it will work through webworker and exit later on workers message
            Elphel.Canvas.drawScaled(cnv_working,cnv_display,settings.width);

            if (DEBUG){
              console.log("#"+elem.attr("id")+", Total time: "+(Date.now()-T0)/1000+" s");
            }

            $(this).trigger("canvas_ready");

            if (settings.refresh) {
                if (DEBUG){
                    TX = Date.now();
                    T0 = Date.now();
                }
                send_request();
            }

          }else if ((IMAGE_FORMAT=="JP4")||(IMAGE_FORMAT=="JP46")){

            if (settings.fast){
              quickestPreview(ctx);
            }/*else{
              Elphel.reorderJP4Blocks(ctx,"JP4");

              if (settings.precise){
                PIXELS = Elphel.pixelsToArrayLinear(ctx);
                Elphel.demosaicBilinear(ctx,PIXELS,settings.mosaic,true);
                PIXELS = Elphel.pixelsToArray(ctx);
              }else{
                PIXELS = Elphel.pixelsToArray(ctx);
                Elphel.demosaicBilinear(ctx,PIXELS,settings.mosaic,false);
                PIXELS = Elphel.pixelsToArray(ctx);
              }

              if (settings.channel!="all"){
                  Elphel.showSingleColorChannel(ctx,settings.channel);
              }

              if (settings.diff){
                Elphel.diffColorChannels(PIXELS,settings.chn1,settings.chn2,1);
                Elphel.drawImageData(ctx,PIXELS);
              }

              if (settings.ndvi){
                console.log(PIXELS[0]+" "+PIXELS[1]+" "+PIXELS[2]+" "+PIXELS[3]+" ");
                PIXELS = Elphel.someIndex(PIXELS);
                console.log(PIXELS[0]+" "+PIXELS[1]+" "+PIXELS[2]+" "+PIXELS[3]+" ");
                Elphel.drawImageData(ctx,PIXELS);
              }

            }
            */
            // RGB -> YCbCr x SATURATION -> RGB
            // Taking SATURATION[0] = 1/GAMMA[0] (green pixel of GR-line)
            //saturation(ctx,SATURATION[0]);
          }

          // too early
          //console.log("#"+elem.attr("id")+", time: "+(Date.now()-t0)/1000+" s");
        }
      });
    }

    function quickestPreview(ctx){

      var worker = new Worker(settings.webworker_path+'/webworker.js');

      if (DEBUG){
        TX = Date.now();
      }

      //ctx.canvas.width = ctx.canvas.width/2;
      //ctx.canvas.height = ctx.canvas.height/2;
      //ctx.canvas.style.width = ctx.canvas.style.width/4;
      //ctx.canvas.style.height = ctx.canvas.style.height/4;

      var width = ctx.canvas.width;
      var height = ctx.canvas.height;
      var image = ctx.getImageData(0,0,width,height);
      var pixels = image.data;

      if (DEBUG){
        console.log("#"+elem.attr("id")+", data from canvas for webworker time: "+(Date.now()-TX)/1000+" s");
        TX = Date.now();
      }

      worker.postMessage({
        mosaic: settings.mosaic,
        format: IMAGE_FORMAT,
        width:ctx.canvas.width,
        height:ctx.canvas.height,
        pixels:pixels.buffer,
        settings: {
          fast:    settings.fast,
          channel: settings.channel,
          diff:    settings.diff,
          ndvi:    settings.ndvi,
          lowres:  settings.lowres
        },
      },[pixels.buffer]);


      worker.onmessage = function(e){

        var pixels = new Uint8Array(e.data.pixels);
        var working_context = cnv_working[0].getContext('2d');

        var width = e.data.width;
        var height = e.data.height;

        if (DEBUG){
          console.log("#"+elem.attr("id")+", worker time: "+(Date.now()-TX)/1000+" s");
          TX = Date.now();
        }

        Elphel.Canvas.putImageData(working_context,pixels,width,height);
        Elphel.Canvas.drawScaled(cnv_working,cnv_display,settings.width);

        if (DEBUG){
          // report time
          console.log("#"+elem.attr("id")+", Total time: "+(Date.now()-T0)/1000+" s");
        }
        //trigger here
        cnv_working.trigger("canvas_ready");

        if (settings.refresh) {
            if (DEBUG){
                TX = Date.now();
                T0 = Date.now();
            }
            send_request();
        }
      }

    }

    /**
     * plugin globals get changed
     * @FLIPV - not used
     * @FLIPH - not used
     * @BAYER - not used
     * @IMAGE_FORMAT - used
     * @SATURATION[i] - not used
     */
    function parseEXIFMakerNote(src){

      var exif_orientation = EXIF.getTag(src,"Orientation");

      //console.log("Exif:Orientation: "+exif_orientation);

      var MakerNote = EXIF.getTag(src,"MakerNote");

      //FLIPH & FLIPV
      if (typeof MakerNote !== 'undefined'){
        FLIPH = (MakerNote[10]   )&0x1;
        FLIPV = (MakerNote[10]>>1)&0x1;

        var tmpBAYER = Array();
        for (var i=0;i<BAYER.length;i++){tmpBAYER[i] = BAYER[i].slice();}

        if (FLIPV==1){
          for(i=0;i<4;i++){BAYER[(i>>1)][(i%2)] = tmpBAYER[1-(i>>1)][(i%2)];}
          for(i=0;i<BAYER.length;i++){tmpBAYER[i] = BAYER[i].slice();}
        }
        if (FLIPH==1){
          for(i=0;i<4;i++){BAYER[(i>>1)][(i%2)] = tmpBAYER[(i>>1)][1-(i%2)];}
        }
      }

      //console.log("MakerNote: Flips: V:"+FLIPV+" H:"+FLIPH);

      //COLOR_MODE ----------------------------------------------------------------
      var color_mode = 0;
      if (typeof MakerNote !== 'undefined') color_mode=(MakerNote[10]>>4)&0x0f;

      switch(color_mode){
        case 2: IMAGE_FORMAT = "JP46"; break;
        case 5: IMAGE_FORMAT = "JP4"; break;
        //default:
      }

      obj.format = IMAGE_FORMAT;

      //var gains = Array();
      //var blacks = Array();
      var gammas = Array();
      //var gamma_scales = Array();
      //var blacks256 = Array();
      //var rgammas = Array();


      //SATURATION ----------------------------------------------------------------
      if (typeof MakerNote !== 'undefined'){
        for(i=0;i<4;i++){
          //gains[i]= MakerNote[i]/65536.0;
          //blacks[i]=(MakerNote[i+4]>>24)/256.0;
          gammas[i]=((MakerNote[i+4]>>16)&0xff)/100.0;
          //gamma_scales[i]=MakerNote[i+4] & 0xffff;
        }
        /*
        for (i=0;i<4;i++) {
          rgammas[i]=elphel_gamma_calc(gammas[i], blacks[i], gamma_scales[i]);
        }
        console.log(rgammas);
        //adjusting gains to have the result picture in the range 0..256
        min_gain=2.0*gains[0];
        for (i=0;i<4;i++){
          if (min_gain > (gains[i]*(1.0-blacks[i]))) min_gain = gains[i]*(1.0-blacks[i]);
        }
        for (i=0;i<4;i++) gains[i]/=min_gain;
        for (i=0;i<4;i++) blacks256[i]=256.0*blacks[i];
        */
        for (i=0;i<4;i++) {
          //SATURATION[i] = 1/gammas[i];
          //SATURATION[i] = 1.75; // nightmarish time
          SATURATION[i] = 2;
        }
        //console.log("MakerNote: Saturations: "+SATURATION[0]+" "+SATURATION[1]+" "+SATURATION[2]+" "+SATURATION[3]);
      }

    }

    /*
    function elphel_gamma_calc(gamma,black,gamma_scale){

      gtable = Array();
      rgtable = Array();

      black256=black*256.0;
      k=1.0/(256.0-black256);
      if (gamma < 0.13) gamma=0.13;
      if (gamma >10.0)  gamma=10.0;

      for (var i=0;i<257;i++) {
        x=k*(i-black256);
        if (x<0.0) x=0.0;
        ig = 0.5+65535.0*Math.pow(x,gamma);
        ig = (ig*gamma_scale)/0x400;
        if (ig>0xffff) ig=0xffff;
        gtable[i]=ig;
      }
      // now gtable[] is the same as was used in the camera
      // FPGA was using linear interpolation between elements of the gamma table, so now we'll reverse that process
      indx=0;
      for (i=0;i<256;i++) {
        outValue=128+(i<<8);
        while ((gtable[indx+1]<outValue) && (indx<256)) indx++;
          if (indx>=256) rgtable[i]=65535.0/256;
          else if (gtable[indx+1]==gtable[indx])
            rgtable[i]=i;
          else
            rgtable[i]=indx+(1.0*(outValue-gtable[indx]))/(gtable[indx+1] - gtable[indx]);
      }
      return rgtable;
    }
    */

  };

  $.fn.jp4 = function(options){
    var element = $(this);

    // Return early if this element already has a plugin instance
    if (element.data('jp4')) return element.data('jp4');

    var jp4 = new JP4(this,options);
    element.data('jp4',jp4);

    var res = new Object();
    res.cnv = element;
    res.data = jp4;

    return res;
  };
}(jQuery));
