
importScripts('elphel.js');

self.onmessage = function(e) {

  var W = e.data.width;
  var H = e.data.height;
  var Mosaic = e.data.mosaic;
  var Format = e.data.format;
  
  var settings = e.data.settings;
  
  var Pixels = new Uint8Array(e.data.pixels);
  
  if (Format!="JPEG"){
    var reorderedPixels = Elphel.Pixels.reorderBlocksJPx(Pixels,W,H,Format,Mosaic,settings.fast);
    //reorder first then downscale
    if (settings.fast){
      W = W/2;
      H = H/2;
    }
    
    Elphel.Pixels.applySaturation(reorderedPixels,W,H,2);
    
    postMessage({
      width: W,
      height: H,
      pixels: reorderedPixels.buffer
    },[reorderedPixels.buffer]);
    
  }else{

    //just send back
    postMessage({
      width: W,
      height: H,
      pixels: Pixels.buffer
    },[Pixels.buffer]);
    
  }
  
};