
importScripts('elphel.js');

onmessage = async (e) => {

    let W = e.data.width;
    let H = e.data.height;
    let Mosaic = e.data.mosaic;
    let Format = e.data.format;

    let settings = e.data.settings;

    let Pixels = new Uint8Array(e.data.pixels);

    let reorderedPixels;

    if (settings.lowres==0){
        reorderedPixels = Elphel.Pixels.reorderBlocksJPx(Pixels,W,H,Format,Mosaic,settings.fast);
        //reorder first then downscale
        if (settings.fast){
            W = W/2;
            H = H/2;
        }
    }else{
        reorderedPixels = await Elphel.Pixels.reorderBlocksJP4_lowres(Pixels,W,H,Format,Mosaic,settings.lowres);
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
    this.close();
};
