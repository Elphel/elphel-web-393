/** 
 * @file elphel.js
 * @brief functions for pixel manipulation (canvas)
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

var Elphel = {

    /* Name: reorderJP4Blocks
     * Description: clear from the function's name
     * 
     * ctx - canvas 2D context
     * format:
     *   'jpeg' - skip reordering
     *   'jp4' - jp4 reordeing
     *   'jp46' - jp46 reordering
     * mosaic - [["Gr","R"],["B","Gb"]] = 
     *    odd lines:  Gr,R,Gr,R
     *    even lines: B,Gb,B,Gb
     * nwd - true/false - demosaicing: 'Nearest Neighbor' with 1/2 scale - 
     *    put mosaic (different channels) for GrRBGb into one pixel for 
     *    faster performance
     */
    reorderJP4Blocks: function(ctx, format="JP4", mosaic=[["Gr","R"],["B" ,"Gb"]], nwd=false) {
      
      var t0 = Date.now();
      
      var width = ctx.canvas.width;
      var height = ctx.canvas.height;

      var inputImage = ctx.getImageData(0,0,width,height);
      var iPixels = inputImage.data;

      var outputImage = ctx.createImageData((nwd)?width/2:width,(nwd)?height/2:height);
      var oPixels = outputImage.data;
      
      // img.data is a long 1-D array with the following structure:
      // pix[i+0] - red
      // pix[i+1] - green
      // pix[i+2] - blue
      // pix[i+3] - alpha

      // buffer for reordering pixels
      var macroblock = new Array(); //16x16
      for (var y=0;y<16;y++) macroblock[y]=new Array();

      // in JP4 format the 16x16 block is 32x8 (GRBG)
      // the 1st line of 32x8 blocks is the left half of the image
      // the 2nd line of 32x8 blocks is the right half

      // vertical step = 16 pixels
      for (yb=0;yb<(height>>4);yb++){
        // horizontal step = 16 pixels 
        for (xb=0;xb<(width>>4);xb++) {
          if (format=="JP4") {
            // 32x8 block reorder into 16x16
            for (nb=0;nb<4;nb++) {
              xbyr= nb&1; // horizontal odd-even
              ybyr=(nb>>1)&1; // vertical odd-even
              for (y=0;y<8;y++) {
                // xb <  half image -> 1st line of 32x8
                // xb >= half image -> 2nd line of 32x8
                //offset=(((yb<<4)+y)*width)+(nb<<3)+((xb>=(width>>5))?(((xb<<5)-width)+(width<<3)):(xb<<5));
                offset=(((yb<<4)+y)*width)+(nb<<3)+(xb<<5)+((xb>=(width>>5))?((width<<3)-width):0);
                for (x=0;x<8;x++) {
                  macroblock[(y<<1)|ybyr][(x<<1)|xbyr]=iPixels[4*(offset+x)];
                }
              }
            }
          }  
          if (format=="JP46") {
            for (y=0;y<16;y++) {
              offset=((yb<<4)+y)*width+(xb<<4);
              for (x=0;x<16;x++) {
                //Red value only
                macroblock[((y<<1)&0xe)|((y>>3)&0x1)][((x<<1)&0xe)|((x>>3)&0x1)]=iPixels[4*(offset+x)];
              }
            }
          }    

          if (nwd){
            for (y=0;y<8;y++){
              offset=width/2*((yb<<4)/2+y)+(xb<<4)/2;
              for (x=0;x<8;x++) {
                
                for(k=0;k<4;k++){
                  y0 = 2*y+((k>>1)&1);
                  x0 = 2*x+(k&1);
                  if      (mosaic[y0&1][x0&1]=="R")   r = macroblock[y0][x0];
                  else if (mosaic[y0&1][x0&1]=="Gr") gr = macroblock[y0][x0];
                  else if (mosaic[y0&1][x0&1]=="Gb") gb = macroblock[y0][x0];
                  else if (mosaic[y0&1][x0&1]=="B")   b = macroblock[y0][x0];
                }
                g = (gr+gb)>>1;
                oPixels[4*(offset+x)+0] = r;//4*(8*y+x);
                oPixels[4*(offset+x)+1] = g;//4*(8*y+x);
                oPixels[4*(offset+x)+2] = b;//4*(8*y+x);
                oPixels[4*(offset+x)+3] = 255;
              }
            }
          }else{
            for (y=0;y<16;y++){
              offset=width*((yb<<4)+y)+(xb<<4);
              for (x=0;x<16;x++) {
                //red +0, green +1, blue +2, alpha +3
                // thinking: GRBG
                oPixels[4*(offset+x)+0] = ((mosaic[y&1][x&1]=="R" )                               )?macroblock[y][x]:0;
                oPixels[4*(offset+x)+1] = ((mosaic[y&1][x&1]=="Gr")||(mosaic[y%2][x%2]=="Gb"))?macroblock[y][x]:0;
                oPixels[4*(offset+x)+2] = ((mosaic[y&1][x&1]=="B" )                               )?macroblock[y][x]:0;
                oPixels[4*(offset+x)+3] = 255;
              }
            }
          }
        }
      }
      if (nwd) {
        ctx.canvas.width = width/2;
        ctx.canvas.height = height/2;
      }
      ctx.putImageData(outputImage,0,0);
      
      console.log("reorderJP4Blocks(): "+(Date.now()-t0)/1000+" s");
    },

    /* Name: demosaicNearestNeighbor
     * Description: A separate function, just in case, same as in
     *              reorderJP4Blocks
     *
     * ctx - canvas 2D context
     * mosaic - [["Gr","R"],["B","Gb"]] = 
     *    odd lines:  Gr,R,Gr,R
     *    even lines: B,Gb,B,Gb
     */
    demosaicNearestNeighbor: function(ctx, mosaic=[["Gr","R"],["B" ,"Gb"]]){

      
      var t0 = Date.now();
      
      var width = ctx.canvas.width;
      var height = ctx.canvas.height;

      var inputImage = ctx.getImageData(0,0,width,height);
      var iPixels = inputImage.data;

      var outputImage = ctx.createImageData(width/2,height/2);
      var oPixels = outputImage.data;
      
      for(var y=0;y<height/2;y++){
        for(var x=0;x<width/2;x++){
          for(var k=0;k<4;k++){
            y0 = (k>>1)&1;
            x0 = k&1;
            if      (mosaic[y0&1][x0&1]=="R")  r  = iPixels[4*(width*(2*y+y0)+2*x+x0)+0];
            else if (mosaic[y0&1][x0&1]=="Gr") gr = iPixels[4*(width*(2*y+y0)+2*x+x0)+1];
            else if (mosaic[y0&1][x0&1]=="Gb") gb = iPixels[4*(width*(2*y+y0)+2*x+x0)+1];
            else if (mosaic[y0&1][x0&1]=="B")  b  = iPixels[4*(width*(2*y+y0)+2*x+x0)+2];
          }
          g = (gr+gb)>>1;
          oPixels[4*(width/2*y+x)+0] = r;
          oPixels[4*(width/2*y+x)+1] = g;
          oPixels[4*(width/2*y+x)+2] = b;
          oPixels[4*(width/2*y+x)+3] = 255;
        }
      }
      ctx.canvas.width = width/2;
      ctx.canvas.height = height/2;
      ctx.putImageData(outputImage,0,0);
      
      console.log("demosaicNearestNeighbor(): "+(Date.now()-t0)/1000+" s");
    },
      
    /* Name: demosaicBilinear
     * Description: a simple bilinear demosaicing
     *
     * ctx - canvas 2D context
     * px - pixel array as in "ctx.getImageData(0,0,width,height)"
     *      or use pixelsToArray(ctx,true) to get linear array from canvas
     * mosaic - [["Gr","R"],["B","Gb"]] = 
     *    odd lines:  Gr,R,Gr,R
     *    even lines: B,Gb,B,Gb
     * precise - true/false:
     *    true  - calculate values then apply gamma - provide linear pixel array
     *            px_linear (8bit) = px^gamma (32bit), gamma=2
     *    false - calculate values from gamma encoded pixels - 2-4x times faster
     */
    demosaicBilinear: function(ctx, px, mosaic=[["Gr","R"],["B" ,"Gb"]], precise=false){

      var t0 = Date.now();
      
      var width = ctx.canvas.width;
      var height = ctx.canvas.height;
      
      var outputImage = ctx.getImageData(0,0,width,height);
      var oPixels = outputImage.data; 
      
      var x_l = 0, x_r = 0;
      var y_t = 0, y_b = 0;
      
      var x = width;
      var y = height;
                
      for(var y=0;y<height;y++){
        for(var x=0;x<width;x++){
          x_l = (x==0)?1:(x-1);
          x_r = (x==(width-1))?(width-2):(x+1);
          y_t = (y==0)?1:(y-1);
          y_b = (y==(height-1))?(height-2):(y+1);
          
          //Gr
          if (mosaic[y%2][x%2]=="Gr"){
            Pr_y0xl = px[4*(width*(y+0)+(x_l))+0];
            Pr_y0xr = px[4*(width*(y+0)+(x_r))+0];
            Pb_ybx0 = px[4*(width*(y_b)+(x+0))+2];
            Pb_ytx0 = px[4*(width*(y_t)+(x+0))+2];
            
            if (precise){
              //Pr = pixel_l2g(1/2*(pixel_g2l(Pr_y0xl)+pixel_g2l(Pr_y0xr)));
              //Pb = pixel_l2g(1/2*(pixel_g2l(Pb_ybx0)+pixel_g2l(Pb_ytx0)));
              Pr = this.l2g(1/2*(Pr_y0xl+Pr_y0xr));
              Pb = this.l2g(1/2*(Pb_ybx0+Pb_ytx0));
            }else{
              Pr = 1/2*(Pr_y0xl+Pr_y0xr);
              Pb = 1/2*(Pb_ybx0+Pb_ytx0);
            }
            
            oPixels[4*(width*y+x)+0]=Math.round(Pr);
            oPixels[4*(width*y+x)+2]=Math.round(Pb);
          }
          //R
          if (mosaic[y%2][x%2]=="R"){
              
            Pg_ytx0 = px[4*(width*(y_t)+(x+0))+1];
            Pg_y0xl = px[4*(width*(y+0)+(x_l))+1];
            Pg_y0xr = px[4*(width*(y+0)+(x_r))+1];
            Pg_ybx0 = px[4*(width*(y_b)+(x+0))+1];
            
            Pb_ytxl = px[4*(width*(y_t)+(x_l))+2];
            Pb_ytxr = px[4*(width*(y_t)+(x_r))+2];
            Pb_ybxl = px[4*(width*(y_b)+(x_l))+2];
            Pb_ybxr = px[4*(width*(y_b)+(x_r))+2];
            
            if (precise){
              Pg = this.l2g(1/4*(Pg_ytx0+Pg_y0xl+Pg_y0xr+Pg_ybx0));
              Pb = this.l2g(1/4*(Pb_ytxl+Pb_ytxr+Pb_ybxl+Pb_ybxr));
            }else{
              Pg = 1/4*(Pg_ytx0+Pg_y0xl+Pg_y0xr+Pg_ybx0);
              Pb = 1/4*(Pb_ytxl+Pb_ytxr+Pb_ybxl+Pb_ybxr);
            }
            
            oPixels[4*(width*y+x)+1]=Math.round(Pg);
            oPixels[4*(width*y+x)+2]=Math.round(Pb);
            
          }
          //B
          if (mosaic[y%2][x%2]=="B"){
              
            Pr_ytxl = px[4*(width*(y_t)+(x_l))+0];
            Pr_ytxr = px[4*(width*(y_t)+(x_r))+0];
            Pr_ybxl = px[4*(width*(y_b)+(x_l))+0];
            Pr_ybxr = px[4*(width*(y_b)+(x_r))+0];
            
            Pg_ytx0 = px[4*(width*(y_t)+(x+0))+1];
            Pg_y0xl = px[4*(width*(y+0)+(x_l))+1];
            Pg_y0xr = px[4*(width*(y+0)+(x_r))+1];
            Pg_ybx0 = px[4*(width*(y_b)+(x+0))+1];
            
            if (precise){
              Pr = this.l2g(1/4*(Pr_ytxl+Pr_ytxr+Pr_ybxl+Pr_ybxr));
              Pg = this.l2g(1/4*(Pg_ytx0+Pg_y0xl+Pg_y0xr+Pg_ybx0));
            }else{
              Pr = 1/4*(Pr_ytxl+Pr_ytxr+Pr_ybxl+Pr_ybxr);
              Pg = 1/4*(Pg_ytx0+Pg_y0xl+Pg_y0xr+Pg_ybx0);
            }
            
            oPixels[4*(width*y+x)+0]=Math.round(Pr);
            oPixels[4*(width*y+x)+1]=Math.round(Pg);
          }
          //Gb
          if (mosaic[y%2][x%2]=="Gb"){
            
            Pr_ytx0 = px[4*(width*(y_t)+(x+0))+0];
            Pr_ybx0 = px[4*(width*(y_b)+(x+0))+0];
            Pb_y0xl = px[4*(width*(y+0)+(x_l))+2];
            Pb_y0xr = px[4*(width*(y+0)+(x_r))+2];
            
            if (precise){
              Pr = this.l2g(1/2*(Pr_ytx0+Pr_ybx0));
              Pb = this.l2g(1/2*(Pb_y0xl+Pb_y0xr));
            }else{
              Pr = 1/2*(Pr_ytx0+Pr_ybx0);
              Pb = 1/2*(Pb_y0xl+Pb_y0xr);
            }
            
            oPixels[4*(width*y+x)+0]=Math.round(Pr);
            oPixels[4*(width*y+x)+2]=Math.round(Pb);
          }
        }
      }
      ctx.putImageData(outputImage,0,0);
      console.log("demosaicBilinear(): "+(Date.now()-t0)/1000+" s");
    },
    
    /* Name: showSingleColorChannel
     * Description: make a BW from selected channel
     * 
     * ctx - canvas 2D context
     * channel - "red","green","blue"
     */
    showSingleColorChannel: function(ctx, channel){
      var width = ctx.canvas.width;
      var height = ctx.canvas.height;
      var inputImage = ctx.getImageData(0,0,width,height);
      var iPixels = inputImage.data;
      for(var y=0;y<height;y++){
        for(var x=0;x<width;x++){
            r = iPixels[4*(width*y+x)+0];
            g = iPixels[4*(width*y+x)+1];
            b = iPixels[4*(width*y+x)+2];
            
            if (channel=="red"){
                iPixels[4*(width*y+x)+0]=r;
                iPixels[4*(width*y+x)+1]=r;
                iPixels[4*(width*y+x)+2]=r;
            }else if (channel=="green"){
                iPixels[4*(width*y+x)+0]=g;
                iPixels[4*(width*y+x)+1]=g;
                iPixels[4*(width*y+x)+2]=g;
            }else if (channel=="blue"){
                iPixels[4*(width*y+x)+0]=b;
                iPixels[4*(width*y+x)+1]=b;
                iPixels[4*(width*y+x)+2]=b;
            }
        }
      }
      ctx.putImageData(inputImage,0,0);
    },
    
    /* Name: applySaturation
     * Description: get data from ctx saturate and redraw
     * 
     * ctx - canvas 2D context
     * s - value
     */
    applySaturation: function(ctx,s){
      var width = ctx.canvas.width;
      var height = ctx.canvas.height;
      
      var inputImage = ctx.getImageData(0,0,width,height);
      var iPixels = inputImage.data;
      
      var r,g,b;
      var Y,Cb,Cr;
      
      for(var y=0;y<height;y++){
        for(var x=0;x<width;x++){
          r = iPixels[4*(width*y+x)+0];
          g = iPixels[4*(width*y+x)+1];
          b = iPixels[4*(width*y+x)+2];
          
          Y =  0.299*r+0.5870*g+ 0.144*b;
          
          Cb = 128+s*(-0.1687*r-0.3313*g+ 0.500*b);
          Cr = 128+s*(    0.5*r-0.4187*g-0.0813*b);
                
          if (Cb<0) Cb=0; if (Cb>255) Cb=255;
          if (Cr<0) Cr=0; if (Cr>255) Cr=255;
          
          r = Y + 1.402*(Cr-128);
          g = Y - 0.34414*(Cb-128)-0.71414*(Cr-128);
          b = Y + 1.772*(Cb-128);
          
          if (r<0) r=0; if (r>255) r=255;
          if (g<0) g=0; if (g>255) g=255;
          if (b<0) b=0; if (b>255) b=255;
          
          iPixels[4*(width*y+x)+0]=r;
          iPixels[4*(width*y+x)+1]=g;
          iPixels[4*(width*y+x)+2]=b;
          iPixels[4*(width*y+x)+3]=255;
        }
      }
      ctx.putImageData(inputImage,0,0);
    },

    /* Name: l2g
     * Desctiptin: convert a value from linear to gamma encoded,
     *             close to square root
     */
    l2g: function(pv){
      // assuming gamma=2
      var tmp = Math.sqrt(pv);
      if (tmp>255) tmp = 255;
      return tmp;
    },

    /* Name: g2l
     * Desctiptin: convert a value gamma encoded to linear,
     *             close to square
     */    
    g2l: function(pv){
        // assuming gamma = 2
        var tmp = pv*pv;
        return tmp;
    },
    
    /* Name: pixelsToArray
     * Desctiptin: get pixel data from canvas context and copy to
     *             an array. The array will be Uint8ClampedArray
     */
    pixelsToArray: function(ctx){
      t0 = Date.now();
      
      var px = [];
      
      var width = ctx.canvas.width;
      var height = ctx.canvas.height;
      
      var image = ctx.getImageData(0,0,width,height);
      var pixels = image.data;
      
      px = pixels.slice(0);

      console.log("pixelsToArray(): "+(Date.now()-t0)/1000+" s");
      return px;
    },
    
    /* Name: pixelsToArrayLinear
     * Desctiptin: get pixel data from canvas context, convert to linear,
     *             then copy to an array.
     */
    pixelsToArrayLinear: function(ctx){
      t0 = Date.now();
      
      var px = [];
      
      var width = ctx.canvas.width;
      var height = ctx.canvas.height;
      
      var image = ctx.getImageData(0,0,width,height);
      var pixels = image.data;
      
      for(var i=0;i<pixels.length;i+=4){
        px[i+0] = this.g2l(pixels[i+0]);
        px[i+1] = this.g2l(pixels[i+1]);
        px[i+2] = this.g2l(pixels[i+2]);
        px[i+3] = pixels[i+3]; //alpha
      }

      console.log("pixelsToArrayLinear(): "+(Date.now()-t0)/1000+" s");
      return px;
    },
    
    /* Name: drawScaled
     * Description: Plugin specific. Takes source canvas - draws a scaled 
     *              version on destination canvas
     */
    drawScaled: function(cnv_src,cnv_dst,width){
      var t0 = Date.now();
      
      var ctx = cnv_src[0].getContext('2d');
      
      var tw = ctx.canvas.width;
      var th = ctx.canvas.height;
      var tr = tw/th;
      
      var sctx = cnv_dst[0].getContext('2d');
      
      var w = Math.round(width);
      var h = Math.round(w/tr);
      
      sctx.canvas.width = w;
      sctx.canvas.height = h;
      
      cscale = Math.round(w/tw*100)/100;
      
      sctx.scale(cscale,cscale);
      sctx.drawImage(cnv_src[0],0,0);

      console.log("drawScaled(): "+(Date.now()-t0)/1000+" s");
    },
    
    /* Name: diffColorChannels
     * Description: color channel difference
     * 
     */
    diffColorChannels: function(px,chn1,chn2,k=1){
      
      var t0 = Date.now();
      
      var i1 = 0;
      if (chn1=="green") i1 = 1;
      if (chn1=="blue")  i1 = 2;

      var i2 = 0;
      if (chn2=="green") i2 = 1;
      if (chn2=="blue")  i2 = 2;
      
      for(var i=0;i<(px.length>>2);i++){
        diff = k*px[4*i+i1] - px[4*i+i2];
        px[4*i+0] = diff;
        px[4*i+1] = diff;
        px[4*i+2] = diff;
        px[4*i+3] = 255;
      }
      
      console.log("diffColorChannels(): "+(Date.now()-t0)/1000+" s");
      return px;
    },
    
    /* Name: ndvi
     * Description: get ndvi
     */
    ndvi: function(px){
      var r,g,b;
      var NIR,RED,NDVI;
      for(var i=0;i<(px.length>>2);i++){
        r = px[4*i+0];
        g = px[4*i+1];
        b = px[4*i+2];
        
        if ((b>r)||(g>r)) {
          console.log("Color error!");
          NIR = 0;
          RED = 1;
          //if (b>r) px[4*(width*y+x)+2]=255;
          if (b>r) px[4*i+2]=0;
          if (g>r) px[4*i+1]=0;
          px[4*i+0]=200;
        }else{
          if ((r-b)<20) b = 0;
          k = 0.6;
          RED = (r-g)/(1-k);
          NIR = (r - RED)*2.5;
          NDVI = (NIR-RED)/(NIR+RED);
          
          //color
          if (NDVI<0.00){r=0;g= 0;b= 0;}
          else if (NDVI<0.50){r=200         ;g=400*(0.5-NDVI);b=0;}
          else if (NDVI<1.00){r=400*(1-NDVI);g=200;           b=0;}
          else{
            r = 150; g = 150; b = 150;
          }
          
          //console.log("RED="+RED+" NIR="+NIR);
          px[4*i+0]=r;
          px[4*i+1]=g;
          px[4*i+2]=b;
        }
      }
      return px;
    },
    
    /* Name: gammaEncode
     * Description: -
     */
    gammaEncode: function(px){
      for(var i=0;i<(px.length>>2);i++){
        px[4*i+0] = this.l2g(px[4*i+0]);
        px[4*i+1] = this.l2g(px[4*i+1]);
        px[4*i+2] = this.l2g(px[4*i+2]);
      }
      return px;
    },
    
    /* Name: gammaDecode
     * Description: -
     */
    gammaDecode: function(px){
      for(var i=0;i<(px.length>>2);i++){
        px[4*i+0] = this.g2l(px[4*i+0]);
        px[4*i+1] = this.g2l(px[4*i+1]);
        px[4*i+2] = this.g2l(px[4*i+2]);
      }
      return px;      
    },
    
    /* Name: drawImageData
     * Description: -
     */
    drawImageData: function(ctx,px){
      var t0 = Date.now();
      
      var img = ctx.createImageData(ctx.canvas.width,ctx.canvas.height);
      
      var imgdata = img.data;
      
      for(var i=0;i<(imgdata.length>>2);i++){
        imgdata[4*i+0] = px[4*i+0];
        imgdata[4*i+1] = px[4*i+1];
        imgdata[4*i+2] = px[4*i+2];
        imgdata[4*i+3] = px[4*i+3];
      }
      
      ctx.putImageData(img,0,0);
      
      console.log("drawImageData(): "+(Date.now()-t0)/1000+" s");
    }

};
