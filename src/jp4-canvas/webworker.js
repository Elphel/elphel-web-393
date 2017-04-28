
importScripts('elphel.js');

self.onmessage = function(e) {

  var W = e.data.width;
  var H = e.data.height;
  var Mosaic = e.data.mosaic;
  var Format = e.data.format;
  
  var settings = e.data.settings;
  
  var Pixels = new Uint8Array(e.data.pixels);
  
  if (settings.lowres==0){
    var reorderedPixels = Elphel.Pixels.reorderBlocksJPx(Pixels,W,H,Format,Mosaic,settings.fast);
    //reorder first then downscale
    if (settings.fast){
      W = W/2;
      H = H/2;
    }
  }else{
    var reorderedPixels = Elphel.Pixels.reorderBlocksJP4_lowres(Pixels,W,H,Format,Mosaic,settings.lowres);
    W = W/2;
    H = H/2;
  }
  
  Elphel.Pixels.applySaturation(reorderedPixels,W,H,2);
  
  postMessage({
    width: W,
    height: H,
    pixels: reorderedPixels.buffer
  },[reorderedPixels.buffer]);
  
  //Elphel.test();
  
};