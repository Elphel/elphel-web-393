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

    // https://stackoverflow.com/questions/28495390/thermal-imaging-palette
    var iron_palette = ["#00000a","#000014","#00001e","#000025","#00002a","#00002e","#000032","#000036",
                        "#00003a","#00003e","#000042","#000046","#00004a","#00004f","#000052","#010055",
                        "#010057","#020059","#02005c","#03005e","#040061","#040063","#050065","#060067",
                        "#070069","#08006b","#09006e","#0a0070","#0b0073","#0c0074","#0d0075","#0d0076",
                        "#0e0077","#100078","#120079","#13007b","#15007c","#17007d","#19007e","#1b0080",
                        "#1c0081","#1e0083","#200084","#220085","#240086","#260087","#280089","#2a0089",
                        "#2c008a","#2e008b","#30008c","#32008d","#34008e","#36008e","#38008f","#390090",
                        "#3b0091","#3c0092","#3e0093","#3f0093","#410094","#420095","#440095","#450096",
                        "#470096","#490096","#4a0096","#4c0097","#4e0097","#4f0097","#510097","#520098",
                        "#540098","#560098","#580099","#5a0099","#5c0099","#5d009a","#5f009a","#61009b",
                        "#63009b","#64009b","#66009b","#68009b","#6a009b","#6c009c","#6d009c","#6f009c",
                        "#70009c","#71009d","#73009d","#75009d","#77009d","#78009d","#7a009d","#7c009d",
                        "#7e009d","#7f009d","#81009d","#83009d","#84009d","#86009d","#87009d","#89009d",
                        "#8a009d","#8b009d","#8d009d","#8f009c","#91009c","#93009c","#95009c","#96009b",
                        "#98009b","#99009b","#9b009b","#9c009b","#9d009b","#9f009b","#a0009b","#a2009b",
                        "#a3009b","#a4009b","#a6009a","#a7009a","#a8009a","#a90099","#aa0099","#ab0099",
                        "#ad0099","#ae0198","#af0198","#b00198","#b00198","#b10197","#b20197","#b30196",
                        "#b40296","#b50295","#b60295","#b70395","#b80395","#b90495","#ba0495","#ba0494",
                        "#bb0593","#bc0593","#bd0593","#be0692","#bf0692","#bf0692","#c00791","#c00791",
                        "#c10890","#c10990","#c20a8f","#c30a8e","#c30b8e","#c40c8d","#c50c8c","#c60d8b",
                        "#c60e8a","#c70f89","#c81088","#c91187","#ca1286","#ca1385","#cb1385","#cb1484",
                        "#cc1582","#cd1681","#ce1780","#ce187e","#cf187c","#cf197b","#d01a79","#d11b78",
                        "#d11c76","#d21c75","#d21d74","#d31e72","#d32071","#d4216f","#d4226e","#d5236b",
                        "#d52469","#d62567","#d72665","#d82764","#d82862","#d92a60","#da2b5e","#da2c5c",
                        "#db2e5a","#db2f57","#dc2f54","#dd3051","#dd314e","#de324a","#de3347","#df3444",
                        "#df3541","#df363d","#e0373a","#e03837","#e03933","#e13a30","#e23b2d","#e23c2a",
                        "#e33d26","#e33e23","#e43f20","#e4411d","#e4421c","#e5431b","#e54419","#e54518",
                        "#e64616","#e74715","#e74814","#e74913","#e84a12","#e84c10","#e84c0f","#e94d0e",
                        "#e94d0d","#ea4e0c","#ea4f0c","#eb500b","#eb510a","#eb520a","#eb5309","#ec5409",
                        "#ec5608","#ec5708","#ec5808","#ed5907","#ed5a07","#ed5b06","#ee5c06","#ee5c05",
                        "#ee5d05","#ee5e05","#ef5f04","#ef6004","#ef6104","#ef6204","#f06303","#f06403",
                        "#f06503","#f16603","#f16603","#f16703","#f16803","#f16902","#f16a02","#f16b02",
                        "#f16b02","#f26c01","#f26d01","#f26e01","#f36f01","#f37001","#f37101","#f37201",
                        "#f47300","#f47400","#f47500","#f47600","#f47700","#f47800","#f47a00","#f57b00",
                        "#f57c00","#f57e00","#f57f00","#f68000","#f68100","#f68200","#f78300","#f78400",
                        "#f78500","#f78600","#f88700","#f88800","#f88800","#f88900","#f88a00","#f88b00",
                        "#f88c00","#f98d00","#f98d00","#f98e00","#f98f00","#f99000","#f99100","#f99200",
                        "#f99300","#fa9400","#fa9500","#fa9600","#fb9800","#fb9900","#fb9a00","#fb9c00",
                        "#fc9d00","#fc9f00","#fca000","#fca100","#fda200","#fda300","#fda400","#fda600",
                        "#fda700","#fda800","#fdaa00","#fdab00","#fdac00","#fdad00","#fdae00","#feaf00",
                        "#feb000","#feb100","#feb200","#feb300","#feb400","#feb500","#feb600","#feb800",
                        "#feb900","#feb900","#feba00","#febb00","#febc00","#febd00","#febe00","#fec000",
                        "#fec100","#fec200","#fec300","#fec400","#fec500","#fec600","#fec700","#fec800",
                        "#fec901","#feca01","#feca01","#fecb01","#fecc02","#fecd02","#fece03","#fecf04",
                        "#fecf04","#fed005","#fed106","#fed308","#fed409","#fed50a","#fed60a","#fed70b",
                        "#fed80c","#fed90d","#ffda0e","#ffda0e","#ffdb10","#ffdc12","#ffdc14","#ffdd16",
                        "#ffde19","#ffde1b","#ffdf1e","#ffe020","#ffe122","#ffe224","#ffe226","#ffe328",
                        "#ffe42b","#ffe42e","#ffe531","#ffe635","#ffe638","#ffe73c","#ffe83f","#ffe943",
                        "#ffea46","#ffeb49","#ffeb4d","#ffec50","#ffed54","#ffee57","#ffee5b","#ffee5f",
                        "#ffef63","#ffef67","#fff06a","#fff06e","#fff172","#fff177","#fff17b","#fff280",
                        "#fff285","#fff28a","#fff38e","#fff492","#fff496","#fff49a","#fff59e","#fff5a2",
                        "#fff5a6","#fff6aa","#fff6af","#fff7b3","#fff7b6","#fff8ba","#fff8bd","#fff8c1",
                        "#fff8c4","#fff9c7","#fff9ca","#fff9cd","#fffad1","#fffad4","#fffbd8","#fffcdb",
                        "#fffcdf","#fffde2","#fffde5","#fffde8","#fffeeb","#fffeee","#fffef1","#fffef4",
                        "#fffff6"];

    function get_palette_color(v){

        v = v*(iron_palette.length-1);
        v_lo = Math.floor(v);
        //v_hi = Math.ceil(v);

        // don't interpolate

        return iron_palette[v_lo];
    }

    // only valid values are allowed otherwise - disable
    if ((settings.lowres!=0)&&(settings.lowres!=1)&&(settings.lowres!=2)&&(settings.lowres!=4)&&(settings.lowres!=8)){
      settings.lowres = 0;
    }

    var cnv_working = $("<canvas>",{id:"working"});
    var cnv_display = $("<canvas>",{id:"display"});

    obj.busy = false;

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
        obj.busy = true;
        process_image(settings.image);
    }else{
        send_request();
    }
    //end

    function send_request(){

      var rq = "";

      var http = new XMLHttpRequest();

      if (settings.port!=""&&settings.ip!=""){
        //rq = "/get-image.php?ip="+settings.ip+"&port="+settings.port+"&rel=bimg&ts="+Date.now();
        rq = "http://"+settings.ip+"/get-image.php?ip="+settings.ip+"&port="+settings.port+"&rel=img&ts="+Date.now();
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

            var contentType = http.getResponseHeader("Content-Type");

            if (contentType=="image/tiff"){
              process_image_tiff(http.response);
            }else{
              obj.blob = window.URL.createObjectURL(http.response);
              process_image(obj.blob);
            }

            delete this;
            //URL.revokeObjectURL(imgdata);
        }
      };

      obj.busy = true;
      http.send();

    }

    this.refresh = function(){
      // skip if busy?
      if (!obj.busy){
        send_request();
      }
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

    function process_image_tiff(blob){

        IMAGE_FORMAT = "TIFF";
        obj.format = IMAGE_FORMAT;

        var arrayBuffer;
        var fileReader = new FileReader();
        fileReader.onload = function(event){

            arrayBuffer = event.target.result;

            // tiff.js which is limited in capabilities
            //var tiff = new Tiff({buffer: arrayBuffer});
            //var canvas = tiff.toCanvas();

            // UTIF.js
            var ifds = UTIF.decode(arrayBuffer);
            UTIF.decodeImage(arrayBuffer, ifds[0]);

            if (ifds[0].t258==16){

                rgba_16bit = new Float32Array(ifds[0].data.filter((x,i) => i%2==0));
                rgba_16bit = rgba_16bit.map((x,i) => x + (ifds[0].data[2*i+1]<<8));

                /*
                if (ifds[0].height==122){
                    // cut off telemetry
                    ifds[0].height = 120;
                    rgba_16bit = rgba_16bit.slice(0,ifds[0].height*ifds[0].width);
                }
                */

                // convert to C
                rgba_16bit = rgba_16bit.map(x => x/100-273.15);

                // display based on histogram
                function get_t_lo(a,p=0.05,bins=1024){

                    let min = Math.min(...a);
                    let max = Math.max(...a);
                    let bin_height = (max-min)/bins;


                    for(let i=0;i<bins;i++){
                        let bin_lo = min + (i+0)*bin_height;
                        let bin_hi = min + (i+1)*bin_height;
                        let a_f = a.filter(x => x<bin_hi);
                        if(a_f.length>=p*a.length){
                            return (bin_lo+bin_hi)/2;
                        }
                    }
                    return 0;

                }

                // display based on histogram
                function get_t_hi(a,p=0.01,bins=1024){

                    let min = Math.min(...a);
                    let max = Math.max(...a);
                    let bin_height = (max-min)/bins;

                    for(let i=0;i<bins;i++){
                        let bin_lo = max - (i+1)*bin_height;
                        let bin_hi = max - (i+0)*bin_height;
                        let a_f = a.filter(x => x>bin_lo);
                        if(a_f.length>=p*a.length){
                            return (bin_lo+bin_hi)/2;
                        }
                    }
                    return 0;

                }

                // scale - 0.0-1.0
                t_lo = get_t_lo(rgba_16bit);
                t_hi = get_t_hi(rgba_16bit);

                t_lo = t_lo.toFixed(2);
                t_hi = t_hi.toFixed(2);

                //console.log(t_lo+" "+t_hi);

                rgba_16bit = rgba_16bit.map(x => (x-t_lo)/(t_hi-t_lo));

                // get index
                //rgba_16bit = rgba_16bit.map(x => iron_palette[Math.round(x*(iron_palette.length-1))]);

                // this is for linear black n white
                //rgba_16bit = rgba_16bit.map(x => 255*x*x);

            }

            var rgba_clamped = new Uint8ClampedArray(rgba_16bit.length*4);
            //rgba_clamped = rgba_clamped.map((x,i) => rgba_16bit[(i-i%4)/4]);
            rgba_clamped = rgba_clamped.map((x,i) => {

                let v = rgba_16bit[(i-i%4)/4];
                v = (v>1)?1:v;
                v = (v<0)?0:v;
                v = get_palette_color(v);

                if (i%4==0){
                    v = v.slice(1,3);
                }else if (i%4==1){
                    v = v.slice(3,5);
                }else if (i%4==2){
                    v = v.slice(5,7);
                }else{
                    return 255;
                }

                v = parseInt(v,16);
                return v;
            });

            rgba_clamped = rgba_clamped.map((x,i) => (i%4==3)?255:x);

            var rgba_idata   = new ImageData(rgba_clamped,ifds[0].width,ifds[0].height);
            var canvas = cnv_working[0];

            canvas.width = ifds[0].width;
            canvas.height = ifds[0].height;

            var ctx = canvas.getContext('2d');
            ctx.putImageData(rgba_idata, 0, 0);

            cnv_working.trigger("canvas_ready");
            obj.busy = false;

            //Elphel.Canvas.drawScaled($(canvas),cnv_display,settings.width);
            Elphel.Canvas.drawScaled(cnv_working,cnv_display,settings.width);

        }
        fileReader.readAsArrayBuffer(blob);

    }

    function process_image(imagedata){

        var canvas = cnv_working;
        //reset format
        IMAGE_FORMAT = "JPEG";

        var heavyImage = new Image();

        heavyImage.onload = function(){

          /*
          if (obj.blob){
            console.log("revoking object");
            window.URL.revokeObjectURL(obj.blob);
          }
          */


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

      //URL.revokeObjectURL($(this).source.src);
      //console.log(this);

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
            obj.busy = false;

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
        obj.busy = false;

        if (settings.refresh) {
            if (DEBUG){
                TX = Date.now();
                T0 = Date.now();
            }
            send_request();
        }

        this.terminate();
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

      //BAYER_MODE
      bayer_mode = 0; // r gr / gb b
      if (typeof MakerNote !== 'undefined'){
        bayer_mode = (MakerNote[10]>>2)&0x3;
        //console.log("Bayer mode = "+bayer_mode);
        switch(bayer_mode){
          case 0: BAYER = [["Gr","R"],["B","Gb"]];break;
          case 1: BAYER = [["R","Gr"],["Gb","B"]];break;
          case 2: BAYER = [["B","Gb"],["Gr","R"]];break;
          case 3: BAYER = [["Gb","B"],["R","Gr"]];break;
          default:BAYER = [["Gr","R"],["B","Gb"]];
        }
      }

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

      settings.mosaic = BAYER;
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
