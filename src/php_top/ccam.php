<?php
/*!*******************************************************************************
*! FILE NAME  : ccam.php
*! DESCRIPTION: Programs major sensor parameters similar (not exactly) to ccam.cgi,
*!              returns xml OK
*! Copyright (C) 2008 Elphel, Inc
*! -----------------------------------------------------------------------------**
*!
*!  This program is free software: you can redistribute it and/or modify
*!  it under the terms of the GNU General Public License as published by
*!  the Free Software Foundation, either version 3 of the License, or
*!  (at your option) any later version.
*!
*!  This program is distributed in the hope that it will be useful,
*!  but WITHOUT ANY WARRANTY; without even the implied warranty of
*!  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*!  GNU General Public License for more details.
*!
*!  You should have received a copy of the GNU General Public License
*!  along with this program.  If not, see <http://www.gnu.org/licenses/>.
*! -----------------------------------------------------------------------------**
*!  $Log: ccam.php,v $
*!  Revision 1.2  2009/12/28 06:24:17  elphel
*!  8.0.6.6 - added MakerNote to Exif, it icludes channels gains and gammas/black levels
*!
*!  Revision 1.1.1.1  2008/11/27 20:04:03  elphel
*!
*!
*!  Revision 1.4  2008/11/04 00:16:50  elphel
*!  started porting
*!
*!  Revision 1.3  2008/11/02 07:26:41  elphel
*!  started porting ccam.php/camvc2
*!
*!  Revision 1.2  2008/09/28 00:31:42  elphel
*!  Some histogram related constants renamed from *AUTOEXP*, *AEXPWND*  to *HISTWND*
*!
*!  Revision 1.8  2008/04/24 18:20:40  elphel
*!  added retrieval of circbuf structure
*!
*!  Revision 1.4  2008/04/16 20:39:32  elphel
*!  removed actions that are unneeded with the current drivers, limited number of retries to stop compressor in "mode=set", added "mode=force" that unconditionally reprograms camera without an attempt to nicely stop the compressor
*!
*!  Revision 1.3  2008/03/25 07:38:43  elphel
*!  just troubleshooting
*!
*!  Revision 1.2  2008/03/22 04:43:03  elphel
*!  few minor changes
*!
*!  Revision 1.1  2008/03/20 22:32:12  elphel
*!  sensor/compressor control, similar commands as ccam.cgi, but no images returned (use imgsrv - port 8081)
*!
*!
*/
function out1x1gif() {
         header("Content-Type: image/gif");
         header("Content-Length: 35\n");
         echo "GIF87a\x01\x00\x01\x00\x80\x01\x00\x00\x00\x00".
              "\xff\xff\xff\x2c\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x4c".
              "\x01\x00\x3b";
 }
/*
$debugGetPars= var_export($_GET, true);
$debugFile=fopen('/var/volatile/html/ccam.php.log','a+');
fwrite($debugFile,"\n================================\n".$debugGetPars);
fclose($debugFile);
*/
//Default parameters to be written after sensor reset
         $init_pars=array(
           "FLIP"=>3,  //!xy, +1 - flip-X, +2 - flipY
           "COLOR"=>1, //! mono - 0, color mode - 1, 2- jp4, +256 - sensor test, 512 - FPGA test
           "DCM_HOR"  => 1,  //! Decimation horizontal
           "DCM_VERT" => 1,  //! Decimation vertical
           "BIN_HOR"  => 1,  //! Binning horizontal
           "BIN_VERT" => 1,  //! Binning vertical
           "QUALITY"  => 90, //! JPEG quality (%)
           "COLOR_SATURATION_BLUE" => 200 , //! color saturation blue 200% (100% - only for gamma=1.0)
           "COLOR_SATURATION_RED" => 200 ,  //! color saturation blue 200% (100% - only for gamma=1.0)
           "BITS" => 8,                     //! 8-bit image mode (may be 16)
           "GAMMA" => 57,                   //! Gamma=57%
           "PIXEL_LOW" => 10,               //! Balck level - 10 (sensor default "fat zero")
           "PIXEL_HIGH" => 254,             //! white level
           "EXPOS" =>    100,               //! whatever? (in 100usec steps)
           "WOI_LEFT" =>  0,                 //! window left
           "WOI_TOP" =>   0,                 //! window top
           "WOI_WIDTH" =>  10000,            //! window width  (more than needed, will be truncated)
           "WOI_HEIGHT" => 10000,            //! window height (more than needed, will be truncated)
           "RSCALE" =>       256,            //! red/green*256 (no auto - it is inside ccam.cgi)
           "BSCALE" =>       256,            //! blue/green*256 (no auto - it is inside ccam.cgi)
           "GAINR" =>        512,            //! Red analog gain 2.0*256
           "GAING" =>        512,            //! Green1 (red row) analog gain 2.0*256
           "GAINB" =>        512,            //! Red analog gain 2.0*256
           "GAINGB" =>       512,            //! Green2 (blue row) analog gain 2.0*256
           "BAYER" =>          4             //! 0..3 - set, 4- use calcualted
         );
          $aexp_arr=array(
            "AUTOEXP_ON"=>0,
            "HISTWND_RWIDTH"=>0,
            "HISTWND_RHEIGHT"=>0,
            "HISTWND_RLEFT"=>0,
            "HISTWND_RTOP"=>0,
            "AUTOEXP_EXP_MAX"=>0,
            "AUTOEXP_OVEREXP_MAX"=>0,
            "AUTOEXP_S_PERCENT"=>0,
            "AUTOEXP_S_INDEX"=>0,
            "AUTOEXP_EXP"=>0,
            "AUTOEXP_SKIP_PMIN"=>0,
            "AUTOEXP_SKIP_PMAX"=>0,
            "AUTOEXP_SKIP_T"=>0,
            "HISTWND_WIDTH"=>0,
            "HISTWND_HEIGHT"=>0,
            "HISTWND_TOP"=>0,
            "HISTWND_LEFT"=>0
          );

         $keep_pars=array(
           "COLOR"=>0,
           "TRIG"=>0
         );

         $wb_thrsh=0.98;
         $wb_minfrac=0.01;
         $wb_rscale=1.0;
         $wb_bscale=1.0;
         $white_balance=false;
         $wbrslt=-1;
         $autoexp_set=false; // change autoexposure settings
         $sleep=  ($_GET["sleep"]);  if ($sleep>0) sleep($sleep);   //missing sleep() in javaScript - sleeps before other actions
         $usleep= ($_GET["usleep"]); if ($usleep>0) usleep($usleep);
         $autoexp=elphel_get_P_value (ELPHEL_AUTOEXP_ON);
         $pars=array();
         $mode=$_GET["mode"];
         $keep_pars=elphel_get_P_arr($keep_pars); // parameters, some bits of which should be preserved
         $timeout=500; //miliseconds - how long to wait for compressor stop
         $hist_in_thresh=0.0;
         $hist_out_thresh=0.0;
         $autoexp_get=false; // output autoexposure data
         $streamer_get=false; // output streamer data
         $show_written=false; // show parameters written
         $exif_get=false;
         $circbuf_get=false;
         $debug_arr=array();
         if ($mode=="reset") { // reset sensor
           $pars=$init_pars;
           $mode="set";
           if (elphel_get_state()>7) {  //! compressor is running
             if ($keep_pars["TRIG"]& 4) { // no sense to check also elphel_get_P_value (ELPHEL_TRIG+ELPHEL_NUMBER) - written value
               elphel_set_P_value(ELPHEL_TRIG,$keep_pars["TRIG"] & ~4);
               elphel_program_sensor (0); /// will stop compressor also
             }
           }
           elphel_reset_sensor();
         } // reset sensor
         $toRead=array(); // raw parameters to read by name
// now parse/add parameters to be programmed
         foreach($_GET as $key=>$value) {
           switch($key) {
/// _time - will look at FPGA time and program both FPGA and system, _stime - unconditionally program both
            case "_time":
              if (elphel_get_fpga_time() > 100000000) break; // time already set
            case "_stime":
              $a=((float) $value)/1000;
              elphel_set_fpga_time ($a); // set FPGA time
              exec("date -s ".date("mdHiY.s",(int)$a),$out,$ret); // set system time
              break;
            case "timeout":
              $timeout=(int) ($value+0);
            case "mode":
            case "acquire":
            case "out":
            case "compressor":
            case "imgsrv":

               break; // not processed here
            case "color": 
              switch ($value) {
                case "jp4":  $value=2; break;
                case "color":$value=1; break;
                case "mono": $value=0; break;
              }
              $keep_pars["COLOR"]=$pars["COLOR"]=((integer) $value) | ($keep_pars["COLOR"] & ~7); // 3 LSBs - for color mode
              break;
            case "test": 
              switch ($value) {
                case "fpga":  $value=2; break;
                case "sensor":$value=1; break;
              }
              $keep_pars["COLOR"]=$pars["COLOR"]=(((integer) $value) << 8) | ($keep_pars["COLOR"] & ~0x700); // 3 LSBs - for color mode
              break;
            case "flip":  $pars["FLIP"]= ((strpos($value,"x")===false)?0:1)+((strpos($value,"y")===false)?0:2); break;
            case "trig": 
              switch ($value) {
                case "sync":  $value=0; break;
                case "async": $value=4; break;
              }
              $keep_pars["TRIG"]=$pars["TRIG"]=($value? 4: 0) | ($keep_pars["TRIG"] & ~0x4); // only change bit 2
              break;
// options "m,..." - not implemented
            case "gam":
            case "gamma":
              $pars["GAMMA"]=(integer) $value;
              break;
            case "pxl":
            case "pixel_low":
              $pars["PIXEL_LOW"]=(integer) $value;
              break;
            case "pxh":
            case "pixel_high":
              $pars["PIXEL_HIGH"]=(integer) $value;
              break;
            case "iq":
            case "quality":
              $pars["QUALITY"]=(integer) $value;
              break;
            case "iq":
            case "quality":
              $pars["QUALITY"]=(integer) $value;
              break;
            case "byr":
            case "bayer":
              if ($value="auto") $value=4;
              $pars["BAYER"]=(integer) $value;
              break;
            case "fpns":
            case "fpn_sub":
              $pars["FPNS"]=(integer) $value;
              break;
            case "fpnm":
            case "fpn_mpy":
              $pars["FPNM"]=(integer) $value;
              break;
            case "expos": // in seconds
            case "exposure":
              if (is_numeric($value)) $value=$value*10000; // now in 1/10000 sec)
            case "e": //1/10000 sec, currently in  ccam.cgi
              switch ($value) {
                case "auto":
                  if ($autoexp==0) {
                    $pars["AUTOEXP_ON"]=1;
                    $autoexp_set=true;
                  }
                  break;
                case "manual":
                  if ($autoexp!=0) {
                    $pars["AUTOEXP_ON"]=0;
                    $autoexp_set=true;
                  }
                  break;
                default:
                  $value=(integer) $value;
                  if ($value>0) {
                     $pars["EXPOS"]=$value;
                    if ($autoexp!=0) {
                      $pars["AUTOEXP_ON"]=0;
                      $autoexp_set=true;
                    }
                  }
              }
              break;
            case "ve": // in scan lines - don't change autoexposure!
              $pars["VEXPOS"]=(integer) $value;
              break;
            case "vw":
              $pars["VIRT_WIDTH"]=(integer) $value;
              break;
            case "vh":
              $pars["VIRT_HEIGHT"]=(integer) $value;
              break;
            case "wl":
            case "left":
              $pars["WOI_LEFT"]=(integer) $value;
              break;
            case "wt":
            case "top":
              $pars["WOI_TOP"]=(integer) $value;
              break;
            case "ww":
            case "width":
              $pars["WOI_WIDTH"]=(integer) $value;
              break;
            case "wh":
            case "height":
              $pars["WOI_HEIGHT"]=(integer) $value;
              break;
            case "pfh": // lower 16 bits - "pfh", higher bits - "ts<<16"
              $pars["PF_HEIGHT"]=(integer) $value;
              break;
            case "fsd":
            case "fsync_dly": // mostly debug - delay frame sync by $value lines
              $pars["FRAMESYNC_DLY"]=(integer) $value;
              break;
            case "dh":
              $pars["DCM_HOR"]=(integer) $value;
              break;
            case "dv":
              $pars["DCM_VERT"]=(integer) $value;
              break;
            case "bh":
              $pars["BIN_HOR"]=(integer) $value;
              break;
            case "bv":
              $pars["BIN_VERT"]=(integer) $value;
              break;
            case "clk": // not used
            case "shl": // not used
              break;
            case "bit":
            case "bits":
              $pars["BITS"]=(integer) $value;
              break;
            case "gr":
            case "red":
              $pars["GAINR"]=(integer) ($value*256);
              break;
            case "gg":
            case "green":
              $pars["GAING"]=(integer) ($value*256);
              break;
            case "gb":
            case "blue":
              $pars["GAINB"]=(integer) ($value*256);
              break;
            case "ggb":
            case "gg2":
            case "green2":
              $pars["GAINGB"]=(integer) ($value*256);
              break;
            case "sens": // just set all gains together
            case "gain":
              $value= (integer) ($value*256); 
              $pars["GAINR"] = $value;
              $pars["GAING"] = $value;
              $pars["GAINB"] = $value;
              $pars["GAINGB"]= $value;
              break;
            case "rscale":
              $pars["RSCALE"]=(integer) ($value*256);
              break;
            case "bscale":
              $pars["BSCALE"]=(integer) ($value*256);
              break;
            case "csb":
              $pars["COLOR_SATURATION_BLUE"]=(integer) $value;
              break;
            case "saturation_blue":
              $pars["COLOR_SATURATION_BLUE"]=(integer)(100*$value);
              break;
            case "csr":
              $pars["COLOR_SATURATION_RED"]=(integer) $value;
              break;
            case "saturation_red":
              $pars["COLOR_SATURATION_RED"]=(integer)(100*$value);
              break;
            case "eol": // probably not used
              $pars["OVERLAP"]=(integer)(100*$value);
              break;
            case "vtrig": // probably not used
              $pars["VIRTTRIG"]=(integer)(100*$value);
              break;
            case "fclk": // FPGA clock, MHz (likely will not work)
              $pars["CLK_FPGA"]=(integer)(1000000*$value);
              break;
            case "sclk": // sensor clock, MHz (likely will not work )
              $pars["CLK_SENSOR"]=(integer)(1000000*$value);
              break;
            case "xtra": // number of additional (to number of macroblocks*768)  clock cycles needed to compress a frame
              $pars["FPGA_XTRA"]=(integer) $value; 
              break;
//#define P_FP1000SLIM     9 // FPS limit, frames per 1000 sec
//#define P_FPSFLAGS      10 // FPS limit mode - bit 0 - limit fps (not higher than), bit 1 - maintain fps (not lower than)

            case "fps":
              $value=(integer)  (1000 *$value); 
              $pars["FP1000SLIM"]=$value;
              if ($_GET["fpslm"]!==NULL) $pars["FPSFLAGS"]=(integer) $_GET["fpslm"];
              else $pars["FPSFLAGS"]= 1;// old behavior - just upper limit, if fps !=0
              break;
            case "fpslm":
              if ($_GET["fps"]===NULL) $pars["FPSFLAGS"]=(integer) $_GET["fpslm"];
              break;
//!white balance parameters - if calculated OK, they will overwrite manual rscale, bscale if any
            case "wb_thrsh":
              if (is_numeric ($value) && ($value > 0) && ($value <= 1)) $wb_thrsh=$value;
              $white_balance=true;
              break;
            case "wb_minfrac":
              if (is_numeric ($value) && ($value > 0) && ($value <  1)) $wb_minfrac=$value;
              $white_balance=true;
              break;
            case "wb_rscale":
              if (is_numeric ($value) && ($value >= 0.1) && ($value <=  10.1)) $wb_rscale=$value;
              $white_balance=true;
              break;
            case "wb_bscale":
              if (is_numeric ($value) && ($value >= 0.1) && ($value <=  10.1)) $wb_rscale=$value;
              $white_balance=true;
              break;
            case "wbalance":
            case "balance":
              $white_balance=true;
              break;
            case "aexp_get":
              $autoexp_get=true;
              break;
//! additional autoexposure parameters. Support both integer values (same as internal) and fractions of 1.0, seconds, etc.
            case "aexp_on": /// same as e=auto
              $pars["AUTOEXP_ON"]=($value)?1:0;
              $autoexp_set=true;
             break;
            case "aexp_width": //relative width of the autoexposure window (<1.0)
              if ($value < 1) $value=100*$value; // now %
              $pars["HISTWND_RWIDTH"]=(integer) $value; 
              $autoexp_set=true;
             break;
            case "aexp_height": //relative height of the autoexposure window (<1.0)
              if ($value < 1) $value=100*$value; // now %
              $pars["HISTWND_RHEIGHT"]=(integer) $value; 
              $autoexp_set=true;
             break;
            case "aexp_left": //relative left of the autoexposure window (<1.0)
              if ($value < 1) $value=100*$value; // now %
              $pars["HISTWND_RLEFT"]=(integer) $value; 
              $autoexp_set=true;
             break;
            case "aexp_top": //relative top of the autoexposure window (<1.0)
              if ($value < 1) $value=100*$value; // now %
              $pars["HISTWND_RTOP"]=(integer) $value; 
              $autoexp_set=true;
             break;
            case "aexp_exp_max": // maximal exposure in seconds (<20)
              if ($value < 20) $value=1000*$value; // now in milliseconds
              $pars["AUTOEXP_EXP_MAX"]=(integer) $value; 
              $autoexp_set=true;
             break;
            case "aexp_overexp": // maximal fraction of overexposed pixels (both fractions and integer 1/100%)
              if ($value < 1) $value=10000*$value; // now in 0.01%
              $pars["AUTOEXP_OVEREXP_MAX"]=(integer) $value; 
              $autoexp_set=true;
             break;
            case "aexp_below": // maximal fraction of pixels exposed less than the threshold (both fractions and integer 1/100%)
              if ($value < 1) $value=10000*$value; // now in 0.01%
              $pars["AUTOEXP_OVEREXP_MAX"]=(integer) $value; 
              $autoexp_set=true;
             break;
            case "aexp_threshold": // threshold, (0..0.999 or 1..255) used together with 
            case "aexp_thresh":
            case "aexp_index":
              if ($value < 1) $value=255.5*$value; // now in 0..255
              $pars["AUTOEXP_S_INDEX"]=(integer) $value; 
              $autoexp_set=true;
             break;
            case "aexp_frac": // fraction of pixels below given threshold
            case "aexp_fraction": // fraction of pixels below given threshold
              if ($value < 1) $value=10000*$value; // now in 0..255
              $pars["AUTOEXP_S_PERCENT"]=(integer) $value; 
              $autoexp_set=true;
             break;

            case "aexp_chng_min": // minimal realtive change in exposure to be applied (0..1.0 and in 0.001%)
              if ($value < 1) $value=10000*$value; // now in 0.01%
              $pars["AUTOEXP_SKIP_PMIN"]=(integer) $value; 
              $autoexp_set=true;
             break;
            case "aexp_chng_minabs": // minimal absoluteve change in exposure to be applied (in "ticks"=1/10000 s, default=2)
              $pars["AUTOEXP_SKIP_T"]=(integer) $value; 
              $autoexp_set=true;
             break;
            case "aexp_chng_max": // maximal realtive change in exposure to be applied (0..1.0 and in 0.001%)
              if ($value < 1) $value=10000*$value; // now in 0.01%
              $pars["AUTOEXP_SKIP_PMAX"]=(integer) $value; 
              $autoexp_set=true;
             break;
/*
            case "hist_in_thresh": // calculate the input and output levels
             $hist_in_thresh=$value+0.0;
             break;
            case "hist_out_thresh": // calculate the input and output levels
             $hist_out_thresh=$value+0.0;
             break;
*/
            case "written":      // show parameters written (accepted)
            case "show_written":
             $show_written=true;
             break;
            case "streamer_get":
             $streamer_get=true;
             break;
            case "exif":
              $exif_get=$value+0; //page number
              break;
            case "description":
              if ( $value!==null)  elphel_set_exif_field(0x10e, $value.chr(0));
              break;
            case "circbuf":
              $circbuf_get=true;
              break;
            default: /// treat as parameter names
             $toRead[$key]="";
             if ($value!=="") $pars[$key]=(integer) $value+0;
           }
         }
         $npars=elphel_set_P_arr($pars);
         if ($white_balance) {
           $wbrslt=elphel_white_balance ($wb_thrsh, $wb_minfrac, $wb_rscale, $wb_bscale); // remember result - if OK - needs to be updated
         } 
         if ($autoexp_set) {
           elphel_autoexposure_set();
         }
///add mode=safe. "set" if not running, otherwise return error (not needed - mode=safe&STATE is enough)

         $was_running=(elphel_get_state()>7);
         if (($mode=="safe") && !$was_running) $mode=$set;
         if (($npars>0) || ($wbrslt>=0)  || ($mode=="skipbad") || ($mode=="set") || ($mode=="force")){
           if (($mode=="skipbad") || ($mode=="set") || ($mode=="force")) {
             if ($was_running && !($mode=="force")) elphel_compressor_stop(); //! stop it
             if (!($mode=="force")) { /// Even if it was not running, compressor might be acquiring the (last) frame 
                 for ($i=0; $i<=($timeout/50);$i++) // wait for some time (but not too long) trying to be nice and stop compressor gracefully
                    if (!elphel_is_compressor_idle()) usleep (50000) ; //0.05sec
                    else break;
 $debug_arr["stopping_force"]=$i;
             }
             if ($mode=="skipbad") elphel_program_sensor (1); // will stop compressor if it was running
             else                  elphel_program_sensor (0); // will stop compressor if it was running
           } else {
 $debug_arr["program_sensor"]=1;
//             elphel_program_sensor (1); // nonstop, just update on the fly obsolete in 8.x
           }
         }
// now control compressor
// (Try to) Stop it if quality is specified and different from the current
         if (($pars["QUALITY"] !==null) &&
             ($pars["QUALITY"] != elphel_get_P_value (ELPHEL_QUALITY))) {
             if (elphel_get_state()>7) elphel_compressor_stop(); //! stop it
             for ($i=0; $i<=($timeout/50);$i++) // wait for some time trying to stop compressor gracefully
                    if (!elphel_is_compressor_idle()) usleep (50000) ; //0.05sec
                    else break;
 $debug_arr["stopping_quality"]=$i;
         }

         switch ($_GET["compressor"]) {
           case "stop":
             elphel_compressor_stop();
            break;
           case "wait":
             while (elphel_get_state()>7) usleep (100000) ; //! just wait - will wait forever if async mode
            break;
           case "restore": // run if was running before programming
$debug_arr["was_running"]=$was_running;
$debug_arr["before_restore_state"]=elphel_get_state();
             if ($was_running && (elphel_get_state() <=7)) elphel_compressor_run();
             else elphel_fpga_write (4,5); /// restore acquisition for autoexposure to work
            break;
           case "run":
             elphel_compressor_run();
            break;
           case "single":
             elphel_compressor_frame();
            break;
           case "reset":
// try this.
// if running and async turn to sync, stop,reset,reset,turn back to sync
// if running and sync - just stop,reset,reset
             if (elphel_get_state()>7) {  //! compressor is running
               if (elphel_get_P_value (ELPHEL_TRIG) & 4) { // keep_pars may have new value, not yet set - we use here current one
                 elphel_set_P_value(ELPHEL_TRIG,$keep_pars["TRIG"] & ~4);
                 elphel_program_sensor (0); // set internal trigger mode, frames might be broken
               }
               elphel_compressor_stop(); //! stop it
               while (elphel_get_state()>7) usleep (100000) ; //! just wait - will wait forever if async mode
               elphel_compressor_reset(); //! Maybe needed twice
               elphel_compressor_reset(); //! Maybe needed twice
//!turn back to async if it was set

               if ($keep_pars["TRIG"]& 4) { // no sense to check also elphel_get_P_value (ELPHEL_TRIG+ELPHEL_NUMBER) - written value
                 elphel_set_P_value(ELPHEL_TRIG,$keep_pars["TRIG"]);
                 elphel_program_sensor (0);
               }
             }
            break;
         }
         if ($_GET["out"]=="gif") {
              out1x1gif();
              exit (0);
         }
         $xml = new SimpleXMLElement("<?xml version='1.0'?><pars/>");
         if (count($toRead)>0) $toRead=elphel_get_P_arr($toRead);
         if ($_GET["STATE"]!==NULL) $toRead["STATE"]=elphel_get_state();
         if ($_GET["imgsrv"]!==NULL) $toRead["imgsrv"]='http://'.$_SERVER['HTTP_HOST'].':8081/';
         foreach ($debug_arr as $key=>$value) {
            $xml->addChild ($key,$value);
         }
         foreach ($toRead as $key=>$value) {
            $xml->addChild ($key,$value);
         }
         if ($exif_get!==false) {
           $exif_got=elphel_get_exif_elphel($exif_get);
           if ($exif_got) {
             $xml->addChild ('Exif');
             $xml->Exif->addChild ("Exif_page",$exif_get);
             foreach ($exif_got as $key=>$value) {
                $xml->Exif->addChild ($key,$value);
             }
           }
         }

/// Calculate and output histogram levels if requested (both input and output in the range of 0.0..1.0, inclusive)
/// here $hist_in_thresh corresponds to input signals as fractions of the full scale input data)
         if ($hist_in_thresh) {
              $xml->addChild ('hist_in');
              $xml->hist_in->addChild ('hist_in_thresh',$hist_in_thresh);
              $xml->hist_in->addChild ('hist_in_r', elphel_histogram(0,elphel_gamma(0,$hist_in_thresh)));
              $xml->hist_in->addChild ('hist_in_g', elphel_histogram(1,elphel_gamma(1,$hist_in_thresh)));
              $xml->hist_in->addChild ('hist_in_g2',elphel_histogram(2,elphel_gamma(2,$hist_in_thresh)));
              $xml->hist_in->addChild ('hist_in_b', elphel_histogram(3,elphel_gamma(3,$hist_in_thresh)));
         }
/// here $hist_out_thresh corresponds to output (8-bit) pixel values as fractions of the 8-bit full scale (255)
         if ($hist_out_thresh) {
              $xml->addChild ('hist_out');
              $xml->hist_out->addChild ('hist_out_thresh',$hist_out_thresh);
              $xml->hist_out->addChild ('hist_out_r', elphel_histogram(0,$hist_out_thresh));
              $xml->hist_out->addChild ('hist_out_g', elphel_histogram(1,$hist_out_thresh));
              $xml->hist_out->addChild ('hist_out_g2',elphel_histogram(2,$hist_out_thresh));
              $xml->hist_out->addChild ('hist_out_b', elphel_histogram(3,$hist_out_thresh));
         }

//! read autoexposure data
         if ($autoexp_get) {
              $xml->addChild ('autoexposure');
              elphel_autoexposure_get();
              $aexp_arr=elphel_get_P_arr ($aexp_arr);
              foreach ($aexp_arr as $key=>$value) {
                 $xml->autoexposure->addChild ($key,$value);
              }
         }

         if ($white_balance) {
                $xml->addChild ('white_balance');
                $xml->white_balance->addChild ('wb_thrsh',  $wb_thrsh);
                $xml->white_balance->addChild ('wb_minfrac',$wb_minfrac);
                $xml->white_balance->addChild ('wb_rscale', $wb_rscale);
                $xml->white_balance->addChild ('wb_bscale', $wb_bscale);
                $xml->white_balance->addChild ('result',($rslt>=0)?"OK":"failure");
                if ($wbrslt>=0) {
                   $xml->white_balance->addChild ('after');
                   $balance_pars=elphel_get_P_arr(array("RSCALE"=>0,"BSCALE"=>0,"GSCALE"=>0));
                   foreach ($balance_pars as $key=>$value) {
                     $xml->white_balance->after->addChild ($key,$value);
                   }
                }
          }
// Output streamer state
          if ($streamer_get) {
            $xml->addChild ('streamer');
            $streamer_run=false;
///FIXME: for now just disable
///            if (($fd=fopen('/dev/stream','r'))) fclose ($fd) ; else $streamer_run=true;

            $streamer_conf=file($streamer_run?'/var/state/streamer.conf':'/etc/streamer.conf');
            foreach ($streamer_conf as $line) {
              $conf_cmd=split('=',$line);
              if (trim($conf_cmd[0])=='pid') {
                exec ('kill -0 '.$conf_cmd[1], $outv,$retv);
                $streamer_run=($retv==0);
              } else {
                $xml->streamer->addChild ('S_'.trim($conf_cmd[0]),trim($conf_cmd[1]));
              }
            }
            $xml->streamer->addChild ('S_STREAM',$streamer_run?1:0);
          }
///circbuf+exif pointres
          if ($circbuf_get) {
            $xml->addChild ('circbuf');
             $circbuf=elphel_get_circbuf_pointers();
            if (is_array  ($circbuf)) {
              $circbuf_count=count($circbuf);
              $xml->circbuf->addChild ('circbuf_count',$circbuf_count);
              for ($i=0;$i<$circbuf_count;$i++) {
                $xml->circbuf->addChild ('frame'.$i);
                $xml->circbuf->{'frame'.$i}->addChild ('circbuf_pointer',$circbuf[$i]['circbuf_pointer']);
                $xml->circbuf->{'frame'.$i}->addChild ('exif_pointer'   ,$circbuf[$i]['exif_pointer']);
              }
            }
          }
          switch ($_GET["out"]) { // Remove completely?
            case "all":
              $xml->addChild ('state',elphel_get_state());
              $xml->addChild ('FRAME',elphel_get_P_value (ELPHEL_FRAME));
              $xml->addChild ('TRIG',elphel_get_P_value (ELPHEL_TRIG));
              break;
          }
          if (count($pars)) { // last item - what was written 
            $xml->addChild ('pars_written');
            $xml->pars_written->addChild ('number_written',$npars);
            if ($show_written) {
               foreach ($pars as $key=>$value) {
                 $xml->pars_written->addChild ($key,$value);
               }
            }
          }

          $rslt=$xml->asXML();
          header("Content-Type: text/xml");
          header("Content-Length: ".strlen($rslt)."\n");
          header("Pragma: no-cache\n");
          printf($rslt);

?>
