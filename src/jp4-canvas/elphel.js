/** 
 * @file elphel.js
 * @brief functions for pixel manipulation and drawing on canvas
 * @copyright Copyright (C) 2017 Elphel Inc.
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
  
  // Drawing
  Canvas:{

    /**
      * Name: putImageData - the same but not the same
      * Description: -
      */
    putImageData: function(ctx,px,width,height){
      
      //var t0 = Date.now();
      
      ctx.canvas.width = width;
      ctx.canvas.height = height;
      
      var imgdata = new ImageData(new Uint8ClampedArray(px), width, height);
      ctx.putImageData(imgdata,0,0);

      //console.log("drawImageData(): "+(Date.now()-t0)/1000+" s");
      
      /*
      // new: http://stackoverflow.com/questions/15908179/draw-image-from-pixel-array-on-canvas-with-putimagedata
      // obsolete:
       
      //var img = ctx.createImageData(ctx.canvas.width,ctx.canvas.height);
      //var imgdata = img.data;
      
      // byte-copy?!!
      for(var i=0;i<(imgdata.length>>2);i++){
        imgdata[4*i+0] = px[4*i+0];
        imgdata[4*i+1] = px[4*i+1];
        imgdata[4*i+2] = px[4*i+2];
        imgdata[4*i+3] = px[4*i+3];
      }
      ctx.putImageData(img,0,0);
      */

    },
    
    /**
    * Name: drawScaled
    * Description: Plugin specific. Takes source canvas - draws a scaled 
    *              version on destination canvas
    */
    drawScaled: function(cnv_src,cnv_dst,width){
      //var t0 = Date.now();
      
      var ctx = cnv_src[0].getContext('2d');
      
      var tw = ctx.canvas.width;
      var th = ctx.canvas.height;
      var tr = tw/th;
      
      var sctx = cnv_dst[0].getContext('2d');
      
      var w = Math.round(width);
      var h = Math.round(w/tr);
      
      sctx.canvas.width = w;
      sctx.canvas.height = h;
      
      //cscale = Math.round(w/tw*100)/100;
      
      //option 1
      //cscale = w/tw;
      //sctx.scale(cscale,cscale);
      //sctx.drawImage(cnv_src[0],0,0);
      
      // option 2
      sctx.drawImage(cnv_src[0],0,0,ctx.canvas.width,ctx.canvas.height,0,0,sctx.canvas.width,sctx.canvas.height);

      //console.log("drawScaled(): "+(Date.now()-t0)/1000+" s");
    }
    
  },
  
  // Pixel manipulation
  Pixels:{
    
    reorderBlocksJP4_lowres: function(pixels,width,height,format="JP4",mosaic=[["Gr","R"],["B" ,"Gb"]],lowres){
      
      var oPixels = new Uint8Array(pixels.length/4);
      
      // check
      if ((lowres!=1)&&(lowres!=2)&&(lowres!=4)&&(lowres!=8)){
        lowres = 4;
      }
      
      var K0 = 3-Math.log(lowres)/Math.log(2);
      var K1 = 1<<K0;
      var K2 = K1-1;
      
      for(var y=0;y<height/2;y++){
        for(var x=0;x<width/2;x++){
          
          offset = y*width/2+x;

          y0 = 2*K1*(y>>K0)+(y&K2);
          x0 = 4*K1*(x>>K0)+(x&K2);
                      
          if (x0>=width){
            x0 = x0 - width;
            y0 = y0 + K1;
          }
          
          offset0 = width*y0 + x0;
          
          for(var k=0;k<4;k++){
            ym = (k>>1)&1;
            xm = k&1;
            if      (mosaic[ym][xm]=="R")   r = pixels[4*(offset0+K1*k)+0];
            else if (mosaic[ym][xm]=="Gr") gr = pixels[4*(offset0+K1*k)+0];
            else if (mosaic[ym][xm]=="Gb") gb = pixels[4*(offset0+K1*k)+0];
            else if (mosaic[ym][xm]=="B")   b = pixels[4*(offset0+K1*k)+0];
          }
          g = (gr+gb)>>1;
          
          oPixels[4*(offset)+0] = r;//pixels[4*(4*offset+1)+0];
          oPixels[4*(offset)+1] = g;//pixels[4*(4*offset+0)+0];
          oPixels[4*(offset)+2] = b;//pixels[4*(4*offset+2)+0];
          oPixels[4*(offset)+3] = 255;
        }
      }

      return oPixels;
      
    },
    
    /**
    * Name: reorderJP4Blocks
    * Description: clear from the function's name
    * 
    * @pixels - pixel array, read from origin canvas
    *  pixels is a long 1-D array with the following structure:
    *  pix[i+0] - red
    *  pix[i+1] - green
    *  pix[i+2] - blue
    *  pix[i+3] - alpha
    * @width - origin canvas width
    * @height - origin canvas height
    * @format - value comes from exif.js function
    *   'jpeg' - skip reordering
    *   'jp4' - jp4 reordeing
    *   'jp46' - jp46 reordering
    * @mosaic - [["Gr","R"],["B","Gb"]] - value comes from application
    *    odd lines:  Gr,R,Gr,R
    *    even lines: B,Gb,B,Gb
    * @nwd - true/false - comes from application - demosaicing: 'Nearest Neighbor' with 1/2 scale - 
    *    put mosaic (different channels) for GrRBGb into one pixel for 
    *    faster performance
    */
    reorderBlocksJPx: function(pixels,width,height,format="JP4",mosaic=[["Gr","R"],["B" ,"Gb"]],nwd=false){
    
      var nwd2 = true;
      
      //var t0 = Date.now();
      
      // pixels is a long 1-D array with the following structure:
      // pix[i+0] - red
      // pix[i+1] - green
      // pix[i+2] - blue
      // pix[i+3] - alpha
      var oPixels = new Uint8Array((nwd)?pixels.length/4:pixels.length);
      
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
                  macroblock[(y<<1)|ybyr][(x<<1)|xbyr]=pixels[4*(offset+x)];
                }
              }
            }
          }  
          if (format=="JP46") {
            for (y=0;y<16;y++) {
              offset=((yb<<4)+y)*width+(xb<<4);
              for (x=0;x<16;x++) {
                //Red value only
                macroblock[((y<<1)&0xe)|((y>>3)&0x1)][((x<<1)&0xe)|((x>>3)&0x1)]=pixels[4*(offset+x)];
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
      //console.log("reorderJP4Blocks: "+(Date.now()-t0)/1000+" s");
      return oPixels;
    },
    
    /**
    * Name: demosaicNearestNeighbor with downscale
    * Description: A separate function, just in case, same as in
    *              reorderJP4Blocks
    *
    * @pixels - pixel array, read from origin canvas
    *  pixels is a long 1-D array with the following structure:
    *  pix[i+0] - red
    *  pix[i+1] - green
    *  pix[i+2] - blue
    *  pix[i+3] - alpha
    * @width - origin canvas width
    * @height - origin canvas height
    * @mosaic - [["Gr","R"],["B","Gb"]] - value comes from application
    *    odd lines:  Gr,R,Gr,R
    *    even lines: B,Gb,B,Gb
    */
    demosaicNearestNeighbor: function(pixels,width,height,mosaic=[["Gr","R"],["B" ,"Gb"]]){

      //var t0 = Date.now();
      
      var oPixels = new Uint8Array(pixels.length/4);
      
      for(var y=0;y<height/2;y++){
        for(var x=0;x<width/2;x++){
          for(var k=0;k<4;k++){
            y0 = (k>>1)&1;
            x0 = k&1;
            if      (mosaic[y0&1][x0&1]=="R")  r  = pixels[4*(width*(2*y+y0)+2*x+x0)+0];
            else if (mosaic[y0&1][x0&1]=="Gr") gr = pixels[4*(width*(2*y+y0)+2*x+x0)+1];
            else if (mosaic[y0&1][x0&1]=="Gb") gb = pixels[4*(width*(2*y+y0)+2*x+x0)+1];
            else if (mosaic[y0&1][x0&1]=="B")  b  = pixels[4*(width*(2*y+y0)+2*x+x0)+2];
          }
          g = (gr+gb)>>1;
          oPixels[4*(width/2*y+x)+0] = r;
          oPixels[4*(width/2*y+x)+1] = g;
          oPixels[4*(width/2*y+x)+2] = b;
          oPixels[4*(width/2*y+x)+3] = 255;
        }
      }
      
      //console.log("demosaicNearestNeighbor(): "+(Date.now()-t0)/1000+" s");
      return oPixels;
    },
    
    /** 
    * Name: demosaicBilinear
    * Description: a simple bilinear demosaicing
    *
    * @pixels - pixel array, read from origin canvas
    *  pixels is a long 1-D array with the following structure:
    *  pix[i+0] - red
    *  pix[i+1] - green
    *  pix[i+2] - blue
    *  pix[i+3] - alpha
    * @width - origin canvas width
    * @height - origin canvas height
    * @mosaic - [["Gr","R"],["B","Gb"]] - value comes from application
    *    odd lines:  Gr,R,Gr,R
    *    even lines: B,Gb,B,Gb
    * @precise - true/false:
    *    true  - calculate values then apply gamma - provide linear pixel array
    *            px_linear (8bit) = px^gamma (32bit), gamma=2
    *    false - calculate values from gamma encoded pixels - 2-4x times faster
    */
    demosaicBilinear: function(pixels, width, height, mosaic=[["Gr","R"],["B" ,"Gb"]], precise=false){

      //var t0 = Date.now();
      
      var oPixels = new Uint8Array(pixels.length);
      
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
            Pr_y0xl = pixels[4*(width*(y+0)+(x_l))+0];
            Pr_y0xr = pixels[4*(width*(y+0)+(x_r))+0];
            Pb_ybx0 = pixels[4*(width*(y_b)+(x+0))+2];
            Pb_ytx0 = pixels[4*(width*(y_t)+(x+0))+2];
            
            if (precise){
              //Pr = pixel_l2g(1/2*(pixel_g2l(Pr_y0xl)+pixel_g2l(Pr_y0xr)));
              //Pb = pixel_l2g(1/2*(pixel_g2l(Pb_ybx0)+pixel_g2l(Pb_ytx0)));
              Pr = this.Functions.l2g(1/2*(Pr_y0xl+Pr_y0xr));
              Pb = this.Functions.l2g(1/2*(Pb_ybx0+Pb_ytx0));
            }else{
              Pr = 1/2*(Pr_y0xl+Pr_y0xr);
              Pb = 1/2*(Pb_ybx0+Pb_ytx0);
            }
            
            oPixels[4*(width*y+x)+0]=Math.round(Pr);
            oPixels[4*(width*y+x)+2]=Math.round(Pb);
          }
          //R
          if (mosaic[y%2][x%2]=="R"){
              
            Pg_ytx0 = pixels[4*(width*(y_t)+(x+0))+1];
            Pg_y0xl = pixels[4*(width*(y+0)+(x_l))+1];
            Pg_y0xr = pixels[4*(width*(y+0)+(x_r))+1];
            Pg_ybx0 = pixels[4*(width*(y_b)+(x+0))+1];
            
            Pb_ytxl = pixels[4*(width*(y_t)+(x_l))+2];
            Pb_ytxr = pixels[4*(width*(y_t)+(x_r))+2];
            Pb_ybxl = pixels[4*(width*(y_b)+(x_l))+2];
            Pb_ybxr = pixels[4*(width*(y_b)+(x_r))+2];
            
            if (precise){
              Pg = this.Functions.l2g(1/4*(Pg_ytx0+Pg_y0xl+Pg_y0xr+Pg_ybx0));
              Pb = this.Functions.l2g(1/4*(Pb_ytxl+Pb_ytxr+Pb_ybxl+Pb_ybxr));
            }else{
              Pg = 1/4*(Pg_ytx0+Pg_y0xl+Pg_y0xr+Pg_ybx0);
              Pb = 1/4*(Pb_ytxl+Pb_ytxr+Pb_ybxl+Pb_ybxr);
            }
            
            oPixels[4*(width*y+x)+1]=Math.round(Pg);
            oPixels[4*(width*y+x)+2]=Math.round(Pb);
            
          }
          //B
          if (mosaic[y%2][x%2]=="B"){
              
            Pr_ytxl = pixels[4*(width*(y_t)+(x_l))+0];
            Pr_ytxr = pixels[4*(width*(y_t)+(x_r))+0];
            Pr_ybxl = pixels[4*(width*(y_b)+(x_l))+0];
            Pr_ybxr = pixels[4*(width*(y_b)+(x_r))+0];
            
            Pg_ytx0 = pixels[4*(width*(y_t)+(x+0))+1];
            Pg_y0xl = pixels[4*(width*(y+0)+(x_l))+1];
            Pg_y0xr = pixels[4*(width*(y+0)+(x_r))+1];
            Pg_ybx0 = pixels[4*(width*(y_b)+(x+0))+1];
            
            if (precise){
              Pr = this.Functions.l2g(1/4*(Pr_ytxl+Pr_ytxr+Pr_ybxl+Pr_ybxr));
              Pg = this.Functions.l2g(1/4*(Pg_ytx0+Pg_y0xl+Pg_y0xr+Pg_ybx0));
            }else{
              Pr = 1/4*(Pr_ytxl+Pr_ytxr+Pr_ybxl+Pr_ybxr);
              Pg = 1/4*(Pg_ytx0+Pg_y0xl+Pg_y0xr+Pg_ybx0);
            }
            
            oPixels[4*(width*y+x)+0]=Math.round(Pr);
            oPixels[4*(width*y+x)+1]=Math.round(Pg);
          }
          //Gb
          if (mosaic[y%2][x%2]=="Gb"){
            
            Pr_ytx0 = pixels[4*(width*(y_t)+(x+0))+0];
            Pr_ybx0 = pixels[4*(width*(y_b)+(x+0))+0];
            Pb_y0xl = pixels[4*(width*(y+0)+(x_l))+2];
            Pb_y0xr = pixels[4*(width*(y+0)+(x_r))+2];
            
            if (precise){
              Pr = this.Functions.l2g(1/2*(Pr_ytx0+Pr_ybx0));
              Pb = this.Functions.l2g(1/2*(Pb_y0xl+Pb_y0xr));
            }else{
              Pr = 1/2*(Pr_ytx0+Pr_ybx0);
              Pb = 1/2*(Pb_y0xl+Pb_y0xr);
            }
            
            oPixels[4*(width*y+x)+0]=Math.round(Pr);
            oPixels[4*(width*y+x)+2]=Math.round(Pb);
          }
        }
      }
      //console.log("demosaicBilinear(): "+(Date.now()-t0)/1000+" s");
      return oPixels;
    },
    
    /**
    * Name: showSingleColorChannel
    * Description: make a BW from selected channel
    * 
    * @pixels - pixel array, read from origin canvas
    *  pixels is a long 1-D array with the following structure:
    *  pix[i+0] - red
    *  pix[i+1] - green
    *  pix[i+2] - blue
    *  pix[i+3] - alpha
    * @width - origin canvas width (not really needed)
    * @height - origin canvas height (not really needed)
    * @channel - string - "red","green","blue"
    */
    showSingleColorChannel: function(pixels, width, height, channel){
      for(var y=0;y<height;y++){
        for(var x=0;x<width;x++){
            r = pixels[4*(width*y+x)+0];
            g = pixels[4*(width*y+x)+1];
            b = pixels[4*(width*y+x)+2];
            
            if (channel=="red"){
                pixels[4*(width*y+x)+0]=r;
                pixels[4*(width*y+x)+1]=r;
                pixels[4*(width*y+x)+2]=r;
            }else if (channel=="green"){
                pixels[4*(width*y+x)+0]=g;
                pixels[4*(width*y+x)+1]=g;
                pixels[4*(width*y+x)+2]=g;
            }else if (channel=="blue"){
                pixels[4*(width*y+x)+0]=b;
                pixels[4*(width*y+x)+1]=b;
                pixels[4*(width*y+x)+2]=b;
            }
        }
      }
      return pixels;
    },
    
    /**
    * Name: applySaturation
    * Description: get data from ctx saturate and redraw
    * 
    * @pixels - pixel array, read from origin canvas
    *  pixels is a long 1-D array with the following structure:
    *  pix[i+0] - red
    *  pix[i+1] - green
    *  pix[i+2] - blue
    *  pix[i+3] - alpha
    * @width - origin canvas width
    * @height - origin canvas height
    * @s - saturation coefficient
    */
    applySaturation: function(pixels,width,height,s){

      var r,g,b;
      var Y,Cb,Cr;
      
      for(var y=0;y<height;y++){
        for(var x=0;x<width;x++){
          r = pixels[4*(width*y+x)+0];
          g = pixels[4*(width*y+x)+1];
          b = pixels[4*(width*y+x)+2];
          
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
          
          pixels[4*(width*y+x)+0]=r;
          pixels[4*(width*y+x)+1]=g;
          pixels[4*(width*y+x)+2]=b;
          pixels[4*(width*y+x)+3]=255;
        }
      }
      return pixels;
    },

    /**
    * Name: diffColorChannels
    * Description: color channel difference
    * 
    */
    diffColorChannels: function(pixels,chn1,chn2,k=1){
      
      //var t0 = Date.now();
      
      var i1 = 0;
      if (chn1=="green") i1 = 1;
      if (chn1=="blue")  i1 = 2;

      var i2 = 0;
      if (chn2=="green") i2 = 1;
      if (chn2=="blue")  i2 = 2;
      
      for(var i=0;i<(pixels.length>>2);i++){
        diff = k*pixels[4*i+i1] - pixels[4*i+i2];
        pixels[4*i+0] = diff;
        pixels[4*i+1] = diff;
        pixels[4*i+2] = diff;
        pixels[4*i+3] = 255;
      }
      
      //console.log("diffColorChannels(): "+(Date.now()-t0)/1000+" s");
      return pixels;
    },
    
    /**
    * Name: ndvi_experimental
    * Description: get ndvi
    */
    ndvi_experimental: function(pixels){
      var r,g,b;
      var NIR,RED,NDVI;
      for(var i=0;i<(pixels.length>>2);i++){
        r = pixels[4*i+0];
        g = pixels[4*i+1];
        b = pixels[4*i+2];
        
        if ((b>r)||(g>r)) {
          console.log("Color error!");
          NIR = 0;
          RED = 1;
          //if (b>r) pixels[4*(width*y+x)+2]=255;
          if (b>r) pixels[4*i+2]=0;
          if (g>r) pixels[4*i+1]=0;
          pixels[4*i+0]=200;
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
          pixels[4*i+0]=r;
          pixels[4*i+1]=g;
          pixels[4*i+2]=b;
        }
      }
      return pixels;
    },
    
    /** 
    * Name: gammaEncode
    * Description: -
    */
    gammaEncode: function(pixels){
      for(var i=0;i<(pixels.length>>2);i++){
        pixels[4*i+0] = this.Functions.l2g(pixels[4*i+0]);
        pixels[4*i+1] = this.Functions.l2g(pixels[4*i+1]);
        pixels[4*i+2] = this.Functions.l2g(pixels[4*i+2]);
      }
      return pixels;
    },
    
    /**
    * Name: gammaDecode
    * Description: -
    */
    gammaDecode: function(pixels){
      for(var i=0;i<(pixels.length>>2);i++){
        pixels[4*i+0] = this.Functions.g2l(pixels[4*i+0]);
        pixels[4*i+1] = this.Functions.g2l(pixels[4*i+1]);
        pixels[4*i+2] = this.Functions.g2l(pixels[4*i+2]);
      }
      return pixels;      
    }
    
  },
    
  // simple funcitons
  Functions:{
    
    /** 
    * Name: l2g
    * Desctiptin: convert a value from linear to gamma encoded,
    *             close to square root
    */
    l2g: function(pv){
      // assuming gamma=2
      var tmp = Math.sqrt(pv);
      if (tmp>255) tmp = 255;
      return tmp;
    },

    /* 
    * Name: g2l
    * Desctiptin: convert a value gamma encoded to linear,
    *             close to square
    */
    g2l: function(pv){
        // assuming gamma = 2
        var tmp = pv*pv;
        return tmp;
    }
    
  },
  
  test: function(){
    console.log("Test message from elphel.js: ok");
  }
  
}