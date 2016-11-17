(function ( $ ) {
  
  //https://gist.github.com/leolux/c794fc63d9c362013448
  var JP4 = function(element,options){
    
    var elem = $(element);
    var obj = this;
    
    var settings = $.extend({
      port: "",
      image: "test.jp4",
      refresh: false,
      mosaic: [["Gr","R"],["B" ,"Gb"]],
      fast: false,
      precise: false,
      width: 600,
      channel: "all",
      diff: false,
      chn1: "red",
      chn2: "green",
      ndvi: false,
      callback: function(){
        console.log("callback");
      }
    },options);

    var BAYER = settings.mosaic;
    var FLIPV = 0;
    var FLIPH = 0;
    var IMAGE_FORMAT = "JPEG";
    var SATURATION = [0,0,0,0];

    var PIXELS = [];

    var cnv_working = $("<canvas>",{id:"result"}).css({
      display:"none"
    });
    var cnv_display = $("<canvas>",{id:"scaled"});
    
    elem.append(cnv_working).append(cnv_display);
        
    get_image(); 
    //end

    function get_image(){
          
          var canvas = cnv_working;
          var scaled_canvas = cnv_display;
          
          //reset format
          IMAGE_FORMAT = "JPEG";
            
          var http = new XMLHttpRequest();
          
          var rq = "";
          if (settings.port!=""){
            rq = "get-image.php?port="+settings.port+"&rel=bimg&ts="+Date.now();
            settings.refresh = true;
          }else{
            rq = settings.image;
          }
          
          console.log("my rq is "+rq);
          
          http.open("GET", rq, true);
          
          http.responseType = "blob";
          http.onload = function(e) {
            if (this.status === 200) {
              var heavyImage = new Image();
              heavyImage.onload = function(){
                EXIF.getData(this, function() {
                  //update canvas size
                  canvas.attr("width",this.width);
                  canvas.attr("height",this.height);

                  parseEXIFMakerNote(this);
                          
                  canvas.drawImage({
                    x:0, y:0,
                    source: heavyImage,
                    load: redraw,
                    //scale: 1,
                    fromCenter: false
                  });
                });
              };
              heavyImage.src = URL.createObjectURL(http.response);
            }
          };
          http.send();
        }
        
    function redraw(){
      $(this).draw({
        fn: function(ctx){
          var t0 = Date.now();
          console.log("Processing "+elem.attr("id"));
          if ((IMAGE_FORMAT=="JP4")||(IMAGE_FORMAT=="JP46")){
            if (settings.fast){
              quickestPreview(ctx);
            }else{
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
            // RGB -> YCbCr x SATURATION -> RGB
            // Taking SATURATION[0] = 1/GAMMA[0] (green pixel of GR-line)
            //saturation(ctx,SATURATION[0]);
          }
          Elphel.drawScaled(cnv_working,cnv_display,settings.width);
          console.log(elem.attr("id")+" processing finished, total time: "+(Date.now()-t0)/1000+" s");
          $(this).trigger("canvas_ready");
          
          if (settings.refresh) get_image();
        }
      });
    }
        
    function quickestPreview(ctx){
      Elphel.reorderJP4Blocks(ctx,"JP4",settings.mosaic,settings.fast);
    }
            
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
          //SATURATION[i] = 1.75; //nightmate time
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
