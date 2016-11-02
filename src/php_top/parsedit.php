#!/usr/bin/env php -q
<?php
/*!
*! PHP script
*! FILE NAME  : parsedit.php
*! DESCRIPTION: 
*! AUTHOR     : Elphel, Inc.
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
*!  $Log: parsedit.php,v $
*!  Revision 1.12  2012/01/13 23:49:51  elphel
*!  no waits in GET mode
*!
*!  Revision 1.11  2012/01/12 08:22:47  elphel
*!  removed final waiting in HTTP GET mode
*!
*!  Revision 1.9  2010/06/08 19:40:22  elphel
*!  using bimg instead of img in imgsrv (configurable)
*!
*!  Revision 1.8  2010/06/01 08:30:36  elphel
*!  support for the FPGA code 03534022  with optional master time stamp over the inter-camera sync line(s)
*!
*!  Revision 1.7  2010/04/30 00:29:33  elphel
*!  new urls, example with explanations
*!
*!  Revision 1.6  2010/04/28 02:27:13  elphel
*!  Added link to control "photofinish" (line scan) modes
*!
*!  Revision 1.5  2010/02/20 20:34:50  elphel
*!  Added color gains control panel, with nothing else
*!
*!  Revision 1.4  2008/12/05 03:32:31  elphel
*!  minor  circbuf format change, support fro COMPRESSOR_SINGLE
*!
*!  Revision 1.3  2008/11/30 05:32:59  elphel
*!  Added WB_EN to be able to turn white balance on/off independently of the WB_MASK
*!
*!  Revision 1.2  2008/11/30 05:01:03  elphel
*!  Changing gains/scales behavior
*!
*!  Revision 1.1.1.1  2008/11/27 20:04:03  elphel
*!
*!
*!  Revision 1.20  2008/11/20 23:23:19  elphel
*!  Now shows descriptions for composite names - bit selections and indexed
*!
*!  Revision 1.19  2008/11/20 07:04:15  elphel
*!  support for parameter descriptions
*!
*!  Revision 1.18  2008/11/18 07:36:27  elphel
*!  Added "Select All" button
*!
*!  Revision 1.17  2008/11/17 23:42:04  elphel
*!  changed myval() to accept numbers in ""
*!
*!  Revision 1.16  2008/11/16 17:34:32  elphel
*!  default settings
*!
*!  Revision 1.15  2008/11/16 00:24:21  elphel
*!  Added "Uncheck All" button
*!
*!  Revision 1.14  2008/11/15 07:04:27  elphel
*!  new parameters to modify analog gains while white balancing
*!
*!  Revision 1.13  2008/11/15 03:10:13  elphel
*!  Some parameters renamed, reassigned.
*!
*!  Revision 1.12  2008/11/13 05:40:45  elphel
*!  8.0.alpha16 - modified histogram storage, profiling
*!
*!  Revision 1.11  2008/11/08 05:53:33  elphel
*!  removed commented out code
*!
*!  Revision 1.10  2008/11/05 02:01:25  elphel
*!  Added bit field manipulation in parameters
*!
*!  Revision 1.9  2008/11/03 19:21:03  elphel
*!  wrong value for TRIG_PERIOD (it is in pixel clocks, not usec)
*!
*!  Revision 1.8  2008/11/02 00:55:10  elphel
*!  added analog gain control (in white balance page)
*!
*!  Revision 1.7  2008/11/02 00:34:55  elphel
*!  added initialization of the camera, multiple demo links on default page
*!
*!  Revision 1.6  2008/11/01 06:37:59  elphel
*!  minor bug fix
*!
*!  Revision 1.5  2008/11/01 06:26:12  elphel
*!  added optional image to the control page
*!
*!  Revision 1.4  2008/10/31 23:16:28  elphel
*!  Now generates last acquired images, annotating them with sequencer commands
*!
*!  Revision 1.3  2008/10/31 18:26:32  elphel
*!  Adding support for constants like SENSOR_REGS32 (defined constant plus 32 to simplify referencing sensor registers from PHP
*!
*!  Revision 1.2  2008/10/29 05:29:20  elphel
*!  snapshot
*!
*!  Revision 1.1  2008/10/29 04:29:29  elphel
*!  started a new script to edit arbitrary camera parameters through web interface
*!
*/

//main()
///globals
    $PARS_FRAMES=16;
    $PARS_FRAMES_MASK = $PARS_FRAMES - 1;
    $sensor_port=0; /// TODO: NC393 - add sensor port control, initially will use $sensor_port=0 for all php functions that require it    
    $autocampars='/www/pages/autocampars.php';
    $descriptions=getParDescriptions($autocampars);
    $default_ahead= 3;
    $maxahead=6; /// maximal ahead of the current frame that tasks can currently be set to driver; //NC393 - is it the same?
    $minahead=4; /// skip to frame $minahead from the soonest next task before programming
    $brief=true;
    $ahead_separator='*';
    $refreshSig="refresh";
    $testMode=-1; /// don't even show
    $showSeqMode=-1;/// don't even show
    $testBefore=2;/// Start compressor $testBefore frames before first task
    $testAfter=2; /// Stop compressor $testAfter frames after the last task
    $framesBeforeStart=2; ///In test mode - compressor will be started theis frames after "now"
//    $imgsrv="http://".$_SERVER['SERVER_ADDR'].":8081";
    $imgsrv_base="http://".$_SERVER['SERVER_ADDR'].":";
    $imgsrv_ports= array ("2323","2324","2325","2326");
    
    /// $imglink="img"; /// use this for faster output (and safer in simultaneous accesses from multiple hosts
    $imglink="bimg"; ///It was "img" - faster, but image may get corrupted if buffer is overrun before it is trasferred (network congestion)
    $defaultImgScale=0.2; /// 20% image size
    $defaultImagesPerRow=3;
    $defaultImagesNumber= 9; // 12
    $isPost=$_SERVER["REQUEST_METHOD"]=="POST";
    $ignoreVals=$isPost;
    $imagesNumber=$defaultImagesNumber;
    $imagesPerRow=$defaultImagesPerRow;
    $imgScale=$defaultImgScale;
    $embedImageScale=0;
    $defaultEmbedImageScale=0.3; 
    $encoded_todo="";
    if ($isPost) {
      parsePost();
      $todo=preparePost();
    }

    if (array_key_exists ( 'sensor_port', $_GET )) {
    	$GLOBALS [sensor_port] = (myval($_GET ['sensor_port'])) & 3;
    }
    $page_title="Default control/test page for the NC393L camera, sensor port ".$GLOBALS [sensor_port];
    if ((count($_GET)==0) || ((count($_GET)==1) && array_key_exists ( 'sensor_port', $_GET ))) {
         startPage($page_title, "");
         printDefaultPage();
         endPage();
         exit (0);
    }
    
    $imgsrv = $imgsrv_base.$imgsrv_ports[$GLOBALS [sensor_port]];
    $elp_const=get_defined_constants(true);
    $elp_const=$elp_const["elphel"];
    $immediateMode=parseGetNames(); 
    if ($immediateMode){
      convertImmediateMode();
      $todo=preparePost();
      if ($showSeqMode>0) { // use regular html page
        startPage($page_title, "");
        echo "<!--\n";
        echo "global_params:\n";
        var_dump($global_params);
        echo "frame_params:\n";
        var_dump($frame_params);
        echo "posted_params:\n";
        var_dump($posted_params);
        echo "todo:\n";
        var_dump($todo);

        echo "\n-->\n";
        showSequence($todo,0);
        addGammas($todo);
        applyPost($todo,true); // no final wait
        showSequence($todo,$frame_zero);
        endPage();
        exit(0); // that's all
      } else { // return XML page with specified parameters values
        addGammas($todo);
        $names=array_merge(extractNames($global_params),extractNames($frame_params));
        $currentParameters=elphel_get_P_arr($GLOBALS [sensor_port], $names);
        applyPost($todo,true); // no final wait
        $msg="<?xml version=\"1.0\"?>\n<parameters>\n";
        foreach ($currentParameters as $key=>$value) {
          $msg.=sprintf("  <%s>%d</%s>\n",$key,$value,$key);
        }
        $msg.="</parameters>\n";
        header("Content-Type: text/xml");
        header("Content-Length: ".strlen($msg)."\n");
        header("Pragma: no-cache\n");
        printf($msg);
        exit(0); // that's all
      }
    }
//    startPage();
    startPage($page_title, mainJavascript($refreshSig));

    if ($isPost) {
      if ($showSeqMode>0) showSequence($todo,0);
      addGammas($todo);
      applyPost($todo);
      if ($showSeqMode>0) showSequence($todo,$frame_zero);
      $encoded_todo=encodeTodo($todo,$frame_zero); /// do it in all modes

    }
    readCurrentParameterValues();
//   echo "<pre>";
//    print_r($frame_params);
//   print_r($posted_params);
//    print_r($global_params);
//   var_dump($todo);
//   echo "</pre>";
    printPage($encoded_todo);
    endPage();

    exit (0);
/**
 * @brief Print the default page that has some demo links
 */

function printDefaultPage() {
   $url_port = "sensor_port=".$GLOBALS [sensor_port]."&";	
//   $imgsrv="http://".$_SERVER['SERVER_ADDR'].":8081";
   $url_init=  $url_port."embed&test&showseq&title=Camera+initialization+parameters"
              ."&TASKLET_CTL=0*0"          /// at least ELPHEL_CONST_TASKLET_CTL_NOSAME bit should be 0 so initialization will not wait for the frame sync
              ."&MAXAHEAD=2"               /// When servicing interrupts, try programming up to 2 frames ahead of the due to program time)
              ."&FPGA_XTRA=1000"           /// Extra clock cycles needed to compress a frame (in addition to per macroblocs time)
              ."&EXTERN_TIMESTAMP=1"       /// Use external timestamp when available
              ."&BITS=8"                   /// 8 bit data mode 
              ."&QUALITY=80"               /// 80 percent JPEG image quality
              ."&COLOR=0"                  /// regular color mode (not mono or JP4 flavors)
              ."&COLOR_SATURATION_BLUE=200"/// 2.0 (200% blue/green color saturation (to compensate for effect of non-unity gamma)
              ."&COLOR_SATURATION_RED=200" /// 2.0 (200% blue/green color saturation
              ."&BAYER=0"                  /// No bayer shift
              ."&SENSOR_RUN=".    ELPHEL_CONST_SENSOR_RUN_CONT     /// turn on sensor in continuous mode
              ."&COMPRESSOR_RUN=".ELPHEL_CONST_COMPRESSOR_RUN_CONT /// run compressor in continuous mode
              ."&GTAB_R=0x0a390400"        /// red component: black level=10, gamma=0.57, scale=1.0 (will force calculation gamma table)
              ."&GTAB_G=0x0a390400"        /// same for green (main one, red row)
              ."&GTAB_GB=0x0a390400"       /// same for green (second, blue row)
              ."&GTAB_B=0x0a390400"        /// same for blue
              ."&SENSOR=0*0"               /// setting sensor to zero will initiate sensor detection attempt (should be frame 0)
              ;
   $url_debug= $url_port."test&showseq&title=Debug control"
              ."&DEBUG=0x3fc"
              ."&TASKLET_CTL"
              ."&PROFILING_EN"
              ."&HISTMODE_C"
              ."&HISTMODE_Y"
              ."&HIST_LAST_INDEX=@"
              ."&THIS_FRAME=@"
              ."&HIST_Y_FRAME=@"
              ."&HIST_C_FRAME=@"
              ."&CIRCBUFWP=@"
              ;
   $url_woi_control=  $url_port."embed&test&showseq&title=Camera+WOI+Controls".
                      "&SENSOR_RUN&COMPRESSOR_RUN&WOI_LEFT&WOI_TOP&WOI_WIDTH&WOI_HEIGHT&FLIPH&FLIPV&DCM_HOR&DCM_VERT&THIS_FRAME=@&CIRCBUFWP=@";
   $url_woi_control_test=$url_port."embed=.4&test=1&showseq=1&title=Camera+WOI+Controls+(test+mode)".
                      "&SENSOR_RUN&COMPRESSOR_RUN&WOI_LEFT&WOI_TOP&WOI_WIDTH&WOI_HEIGHT&FLIPH=1*3&FLIPV=1*5&DCM_HOR&DCM_VERT&THIS_FRAME=@&CIRCBUFWP=@";
   $url_images=       $url_port."images=9:3:.2";
$url_aexp_all= $url_port."embed=0.1&title=Autoexposure/White+Balance/HDR+controls+(full)"
              ."&COMPRESSOR_RUN=".ELPHEL_CONST_COMPRESSOR_RUN_CONT /// run compressor in continuous mode
              ."&DAEMON_EN=1"         /// Daemons are controlled by bits in this register. Autoexposure bit is 0 (1<<0 == 1)
              ."&AUTOEXP_ON=1"        /// setting it to 0 will only disable autoexposure, but not HDR modes or white balancing
              ."&AEXP_FRACPIX=0xff80" /// Fraction of all pixels that should be below P_AEXP_LEVEL (16.16 - 0x10000 - all pixels)
              ."&AEXP_LEVEL=0xf800"   /// Target output level:  [AEXP_FRACPIX]/0x10000 of all pixels should have value below it (0x10000 - full output scale)
              ."&AE_PERIOD=4"         /// Autoexposure period (will be increased if below the latency)
              ."&AE_THRESH=500"       /// AE error (logariphmic exposures) is integrated between frame and corrections are scaled when error is below thershold (500)
              ."&THIS_FRAME=@"        /// Current frame
              ."&NEXT_AE_FRAME=@"     /// Next frame to be processed by autoexposure
              ."&NEXT_WB_FRAME=@"     /// Next frame to be processed by the white balance
              ."&VEXPOS"              /// exposure measured in sensor scanlines (autoexposure modifies number of exposure lines)
              ."&EXPOS"               /// exposure in microseconds
              ."&NEXT_WB_FRAME=@"     /// Next frame to be processed by the white balance
              ."&HIST_DIM_01=0x0a000a00" /// Percentiles measured for colors 0 (lower 16 bits) and 1 (high 16 bits) for  VEXPOS=1 (darkest)
                                      /// Setting it to 0xffffffff will initiate dark levels re-calibration (making 2 dark drames)
              ."&HIST_DIM_23=0x0a000a00" /// Same for colors 2 and 3
              ."&WB_EN=0x1"           /// 1 - enable, 0 - disable white balance
              ."&WB_MASK=0xd"         /// bitmask - which colors to correct (1 - correct, 0 - ignore).
              ."&WB_PERIOD=16"        /// White balance period (will be increased if below the latency)
              ."&WB_WHITELEV=0xfae1"  /// White balance level of white (16.16 - 0x10000 is full scale, 0xfae1 - 98%, default)
              ."&WB_WHITEFRAC=0x028f" /// White balance fraction (16.16) of all pixels that have level above [P_WB_WHITELEV] for the brightest color
                                      /// locally [WB_WHITELEV] will be decreased if needed to satisfy [WB_WHITELEV]. default is 1% (0x028f)

              ."&WB_SCALE_R=0x10000"  /// additional correction for R from calulated by white balance (16.16)
              ."&WB_SCALE_GB=0x10000" /// additional correction for GB (second green) from calulated by white balance (16.16)
              ."&WB_SCALE_B=0x10000"  /// additional correction for B from calulated by white balance (16.16)
              ."&WB_THRESH=0"         /// How many frames the white balance correction has to be the same sign before it
                                      /// will be applied (<128,for each color independently)
              ."&GTAB_R"              /// red component: black level=10, gamma=0.57, scale=1.0 (will force calculation gamma table)
              ."&GTAB_G"              /// same for green (main one, red row)
              ."&GTAB_GB"             /// same for green (second, blue row)
              ."&GTAB_B"              /// same for blue
              ."&HDR_DUR=0"           /// 0 - HDR 0ff, >1 - duration of same exposure (currently 1 or 2 - for free running)
              ."&HDR_VEXPOS=0x40000"  /// if less than 0x10000 - number of lines of exposure, >=10000 - relative to "normal" exposure
              ."&EXP_AHEAD=3"         /// How many frames ahead of the current frame write exposure to the sensor
              ."&AE_INTEGERR=@"       ///  current integrated error in the AE loop
              ."&HISTWND_RWIDTH"      /// Relative histogram window width (0x10000 - 100%)
              ."&HISTWND_RHEIGHT"     /// Relative histogram window height (0x10000 - 100%)
              ."&HISTWND_RLEFT"       /// Relative histogram window left   (0x10000 - 100%)
              ."&HISTWND_RTOP"        /// Relative histogram window top    (0x10000 - 100%)
              ."&HISTWND_WIDTH=@"     /// Absolute (as written to FPGA) histogram window width
              ."&HISTWND_HEIGHT=@"    /// Absolute (as written to FPGA) histogram window height
              ."&HISTWND_LEFT=@"      /// Absolute (as written to FPGA) histogram window left (counted from the left of WOI margin)
              ."&HISTWND_TOP=@"       /// Absolute (as written to FPGA) histogram window top  (counted from the top of WOI)
              ;

$url_aexp_only= $url_port."embed=0.1&title=Autoexposure+controls"
              ."&COMPRESSOR_RUN=".ELPHEL_CONST_COMPRESSOR_RUN_CONT /// run compressor in continuous mode
              ."&DAEMON_EN=1"         /// Daemons are controlled by bits in this register. Autoexposure bit is 0 (1<<0 == 1)
              ."&AUTOEXP_ON=1"        /// setting it to 0 will only disable autoexposure, but not HDR modes or white balancing
              ."&AEXP_FRACPIX=0xff80" /// Fraction of all pixels that should be below P_AEXP_LEVEL (16.16 - 0x10000 - all pixels)
              ."&AEXP_LEVEL=0xf800"   /// Target output level:  [AEXP_FRACPIX]/0x10000 of all pixels should have value below it (0x10000 - full output scale)
              ."&AE_PERIOD=4"         /// Autoexposure period (will be increased if below the latency)
              ."&AE_THRESH=500"       /// AE error (logariphmic exposures) is integrated between frame and corrections are scaled when error is below thershold (500)
              ."&THIS_FRAME=@"        /// Current frame
              ."&NEXT_AE_FRAME=@"     /// Next frame to be processed by autoexposure
              ."&VEXPOS"              /// exposure measured in sensor scanlines (autoexposure modifies number of exposure lines)
              ."&EXPOS"               /// exposure in microseconds
              ."&HIST_DIM_01=0x0a000a00" /// Percentiles measured for colors 0 (lower 16 bits) and 1 (high 16 bits) for  VEXPOS=1 (darkest)
                                      /// Setting it to 0xffffffff will initiate dark levels re-calibration (making 2 dark drames)
              ."&HIST_DIM_23=0x0a000a00" /// Same for colors 2 and 3
              ."&EXP_AHEAD=3"         /// How many frames ahead of the current frame write exposure to the sensor
              ."&HISTWND_RWIDTH"      /// Relative histogram window width (0x10000 - 100%)
              ."&HISTWND_RHEIGHT"     /// Relative histogram window height (0x10000 - 100%)
              ."&HISTWND_RLEFT"       /// Relative histogram window left   (0x10000 - 100%)
              ."&HISTWND_RTOP"        /// Relative histogram window top    (0x10000 - 100%)
              ."&HISTWND_WIDTH=@"     /// Absolute (as written to FPGA) histogram window width
              ."&HISTWND_HEIGHT=@"    /// Absolute (as written to FPGA) histogram window height
              ."&HISTWND_LEFT=@"      /// Absolute (as written to FPGA) histogram window left (counted from the left of WOI margin)
              ."&HISTWND_TOP=@"       /// Absolute (as written to FPGA) histogram window top  (counted from the top of WOI)
              ."&AE_INTEGERR=@"       /// Current integrated error in the AE loop
              ;
$url_wb_only= $url_port."embed=0.1&title=White+Balance+controls"
              ."&COMPRESSOR_RUN=".ELPHEL_CONST_COMPRESSOR_RUN_CONT /// run compressor in continuous mode
              ."&DAEMON_EN=1"         /// Daemons are controlled by bits in this register. Autoexposure bit is 0 (1<<0 == 1)
              ."&THIS_FRAME=@"        /// Current frame
              ."&NEXT_WB_FRAME=@"     /// Next frame to be processed by the white balance
              ."&NEXT_WB_FRAME=@"     /// Next frame to be processed by the white balance
              ."&WB_EN=0x1"           /// 1 - enable, 0 - disable white balance
              ."&WB_MASK=0xd"         /// bitmask - which colors to correct (1 - correct, 0 - ignore).
              ."&WB_PERIOD=16"        /// White balance period (will be increased if below the latency)
              ."&WB_WHITELEV=0xfae1"  /// White balance level of white (16.16 - 0x10000 is full scale, 0xfae1 - 98%, default)
              ."&WB_WHITEFRAC=0x028f" /// White balance fraction (16.16) of all pixels that have level above [P_WB_WHITELEV] for the brightest color
                                      /// locally [WB_WHITELEV] will be decreased if needed to satisfy [WB_WHITELEV]. default is 1% (0x028f)
              ."&WB_SCALE_R=0x10000"  /// additional correction for R from calulated by white balance (16.16)
              ."&WB_SCALE_GB=0x10000" /// additional correction for GB (second green) from calulated by white balance (16.16)
              ."&WB_SCALE_B=0x10000"  /// additional correction for B from calulated by white balance (16.16)
              ."&WB_THRESH=500"       /// WB errors are integrated between frame and corrections are scaled when error is below thershold (500)
              ."&GAIN_MIN=0x18000"    /// minimal sensor analog gain (0x100 - 1.0)
              ."&GAIN_MAX=0xfc000"    /// maximal sensor analog gain (0x100 - 1.0)
//              ."&GAIN_STEP=0x20"      /// minimal correction to be applied to the analog gain (should be set larger that sensor
//                                      /// actual gain step to prevent oscillations (0x100 - 1.0, 0x40 - 1/8)
              ."&ANA_GAIN_ENABLE=1"       /// Enable analog gain controls in white balancing
              ."&GTAB_R"              /// red component: black level=10, gamma=0.57, scale=1.0 (will force calculation gamma table)
              ."&GTAB_G"              /// same for green (main one, red row)
              ."&GTAB_GB"             /// same for green (second, blue row)
              ."&GTAB_B"              /// same for blue
/// Analog gains
              ."&GAINR=0x10000"         /// R  channel gain (mono gain)  8.8 0x100 - 1.0
              ."&GAING=0x10000"         /// G  channel gain (mono gain)  8.8 0x100 - 1.0
              ."&GAINGB=0x10000"        /// GB channel gain (mono gain)  8.8 0x100 - 1.0
              ."&GAINB=0x10000"         /// B  channel gain (mono gain)  8.8 0x100 - 1.0
              ."&SENSOR_REGS45=@"     /// Sensor register gain R
              ."&SENSOR_REGS43=@"     /// Sensor register gain G
              ."&SENSOR_REGS46=@"     /// Sensor register gain GB
              ."&SENSOR_REGS44=@"     /// Sensor register gain B
              ."&WB_INTEGERR=@"       /// current integrated error in the WB loop
              ;

$url_colors_only= $url_port."embed=0.1&title=Color+gains+controls"
/// Analog gains
              ."&GAINR=0x10000"         /// R  channel gain (mono gain)  8.8 0x100 - 1.0
              ."&GAING=0x10000"         /// G  channel gain (mono gain)  8.8 0x100 - 1.0
              ."&GAINGB=0x10000"        /// GB channel gain (mono gain)  8.8 0x100 - 1.0
              ."&GAINB=0x10000"         /// B  channel gain (mono gain)  8.8 0x100 - 1.0
              ."&SENSOR_REGS45=@"     /// Sensor register gain R
              ."&SENSOR_REGS43=@"     /// Sensor register gain G
              ."&SENSOR_REGS46=@"     /// Sensor register gain GB
              ."&SENSOR_REGS44=@"     /// Sensor register gain B
              ;

$url_hdr_exp= $url_port."embed=0.1&title=HDR+exposure+controls"
              ."&COMPRESSOR_RUN=".ELPHEL_CONST_COMPRESSOR_RUN_CONT /// run compressor in continuous mode
              ."&DAEMON_EN=1"         /// Daemons are controlled by bits in this register. Autoexposure bit is 0 (1<<0 == 1)
              ."&AUTOEXP_ON=1"        /// setting it to 0 will only disable autoexposure, but not HDR modes or white balancing
              ."&VEXPOS"              /// exposure measured in sensor scanlines (autoexposure modifies number of exposure lines)
              ."&EXPOS"               /// exposure in microseconds
              ."&HDR_DUR=0"           /// 0 - HDR 0ff, >1 - duration of same exposure (currently 1 or 2 - for free running)
              ."&HDR_VEXPOS=0x40000"  /// if less than 0x10000 - number of lines of exposure, >=10000 - relative to "normal" exposure
              ."&THIS_FRAME=@"        /// Current frame
              ;
$url_ext_trigger=$url_port."embed=0.1&title=External+trigger+controls"
              ."&TRIG=4*5"            ///  External trigger mode (should be set after dealys are set?)
                                      /// bit 0  - "old" external mode (0- internal, 1 - external )
                                      /// bit 1 - enable(1) or disable(0) external trigger to stop clip
                                      /// bit 2 - async (snapshot, ext trigger) mode, 0 - continuous NOTE: Only this bit is used now !
                                      /// bit 3 - no overlap,  single frames: program - acquire/compress same frame
                                      /// bit 4 - Global reset release mode

              ."&TRIG_PERIOD=25000000"/// 0.25 sec @100MHz output sync period (32 bits, in pixel clocks)
                                      /// >=256 repetitive with specified period.
                                      /// NOTE: Currently there is no verification that period is longer than sensor/compressor can handle
              ."&TRIG_DELAY"          /// trigger delay, 32 bits in pixel clocks (needed when multiple cameras are synchronized)
              ."&EXTERN_TIMESTAMP=1"  /// Use external timestamp if available
              ."&TRIG_BITLENGTH=31"   /// bit lengh minus 1 in pixel clocks (when sending timestamp over sync line)
              ."&XMIT_TIMESTAMP=1"    /// transmit timestamp when sending sync
              ."&THIS_FRAME=@"        /// Current frame
///Next parameters are non-zero only for external connections and should match particular I/O boards/connectors
              ."&TRIG_CONDITION=0"    /// trigger condition, 0 - internal, else dibits ((use<<1) | level) for each GPIO[11:0] pin
		                              /// 0x0 - from FPGA, 0x80000 - ext, 0x8000 - int, 0x88000 - any, 0x95555 - add ext, 0x59999 - add int 
              ."&TRIG_OUT=0x65555"    /// trigger output to GPIO, dibits ((use << 1) | level_when_active). Bit 24 - test mode,
                                      ///  when GPIO[11:10] are controlled by other internal signals
                                      /// 0x56555 - ext connector, 0x65555  - internal connector 0x66555 - both, 0x55555 - none
              ."&SENSOR_REGS30=@"     /// Sensor register MODE1 (trigger bit)
              ;
$url_cable_delay=$url_port."embed=0.3&title=Cable+delay+/+Sensor+phase+adjustment"
              ."&SENSOR_PHASE"        /// Sensor phase - use | 0x80000 to reset DCM (needed if phase went too far)
              ."&DAEMON_EN=0"         /// disable autoexposure/color balance
              ."&TESTSENSOR=0x10008"  /// 0x10008 - color bars
              ;
$url_sample=   $url_port
			  ."embed=0.3&title=Sample+camera+control+page"
              ."&SENSOR_REGS32=@"
              ."&SENSOR_REGS32__0106"
              ."&SENSOR_REGS160=@"
              ."&SENSOR_REGS160__0403"
              ."&TESTSENSOR=0x10008"
              ."&FPGATEST"
              ;
$url_ext_photofinish=$url_port."embed=0.2&&title=Photofinish+and+ oversize+controls"
              ."&WOI_TOP"             /// WOI top - here used to adjust the acquisition line to the center of the screen 
              ."&WOI_HEIGHT"          /// Total WOI height (composite image),<16384
              ."&WOI_WIDTH"           /// Just WOI width (image height in photofinish mode)
              ."&PF_HEIGHT"           /// Photofinish stripe height - minimal 2. Set to zero to turn photofinish mode off
              ."&OVERSIZE"            /// include black pixels in the output. WOI_WIDTH and WOI_HEIGHT are not verified in this mode
              ."&DAEMON_EN"           /// bitwise enabling daemons (+1 - autoexposure, +2 - streamer)
              ."&EXPOS"               /// exposure time in microseconds
              ."&GAINR"               /// Red gain (0x10000 corresponds to unity gain)
              ."&GAING"               /// Green in red line gain (0x10000 corresponds to unity gain)
              ."&GAINGB"              /// Green in blue line gain (0x10000 corresponds to unity gain)
              ."&GAINB"               /// Blue gain (0x10000 corresponds to unity gain)
              ."&VIRT_HEIGHT"         /// height of the single photofinish strip (use to slow down line scanning rate - minimal is calculated automatically)
              ."&VIRT_KEEP"           /// 1 - use specified value of VIRT_HEIGHT, 0 - use minimal possible
              ."&ACTUAL_HEIGHT=@"     /// actual height of the composite image, readonly
              ."&SENSOR_REGS6=@"      /// vertical blank in each strip, readonly
              ."&refresh"
              ;
$prefix_url='http://'.$_SERVER['SERVER_ADDR'].$_SERVER['SCRIPT_NAME'];
         echo <<<USAGE
   <h4> Control links:</h4>
   <ul>
   <li><a href="?$url_init">Initialization Parameters (i.e. after powerup)</a></li>
   <li><a href="?$url_colors_only">Control of color channel gains</a></li>
   <li><a href="?$url_woi_control">Demo to change image size, position and mirroring</a></li>
   <li><a href="?$url_woi_control_test">Demo to change image size, position and mirroring - test mode</a></li>
   <li><a href="?$url_aexp_only">Controls related to autoexposure</a></li>
   <li><a href="?$url_wb_only">Controls related to white balance</a></li>
   <li><a href="?$url_hdr_exp">Controls related to HDR exposure - all</a></li>
   <li><a href="?$url_aexp_all">Controls related to autoexposure/white balance/HDR exposure - all</a></li>
   <li><a href="?$url_ext_trigger">External trigger controls</a></li>
   <li><a href="?$url_ext_photofinish">Photofinish and oversize (with black pixels around actiove pixels) modes controls</a></li>
   <li><a href="?$url_cable_delay">Cable delay / sensor phase adjustment. low 16 bits - signed number of ~22ps steps, bits 17:16 - 90 degree steps. Add 0x80000 to reset delays (needed if small delay was set too far for the internal FPGA DCM - phase adjustment limits are frequency dependent, but less than +/-255).</a></li>
   <li><a href="?$url_images">last 9 images, 3 per row, scale=0.2</a></li>
   <li><a href="?$url_debug">Debug on/off control (usually requires to redirect printk() output with 'printk_mod &'</a></li>
   </ul>
   <br/>
   <h2> You may construct custom URLs that combine parameters you need to control or monitor using the included links URLs as examples.</h2>
   </p>Just including parameter (&SOME_PARAMETER) will create form to edit it, adding "&SOME_PARAMETER=1234" will put "1234" in the value filed, &SOME_PARAMETER=@" creates a read-only field to monitor parameter value. You may view/modify sensor registers using meta names SENSOR_REGS with the register number, you may also view edit particular bit fileds of the parameters, adding "__" (double underscore), then WW (two decimals specifying the bit width of the fields) followed by BB - (two decimal numbers specifying the lowest bit in the field (0..31) . In the following example</p>
  <p><a href="?$url_sample">$prefix_url?$url_sample</a></p>
   <ul>
     <li><b>embed=0.3</b> - embedd the camera image scaled down to 30% of the original size</li>
     <li<b>title=Sample+camera+control+page</b> - page title with spaces replaced by "+" signs</li>
     <li<b>SENSOR_REGS32=@</b> - show sensor register 32 in read-only mode</li>
     <li<b>SENSOR_REGS32__0106</b> - show sensor register 32 selected bit [6] (in MT9P031 it controls black level calibration, should be off for the test patterns), allow editing</li>
     <li<b>SENSOR_REGS160=@</b> - show sensor register 160 in read-only mode</li>
     <li<b>SENSOR_REGS160__0403</b> - show sensor register 160 selected bits [6:3] (in MT9P031 they control sensor test pattern), allow editing</li>
     <li<b>TESTSENSOR=0x10008</b> - preffered (register-independent) way to select sensor test pattern, suggest particular value. 0x10000 turns the mode on, 8 selects color bars pattern.</li>
     <li<b>FPGATEST</b> - Add field for FPGA internally generated image (currently just two values - 0 - off, 1 - gray gradient columns).</li>
   </ul>
   <p>Each parameter on a generated page has a "tooltip" - if  you hover a mouse pointer over it's name it will show short description of the parameter.</p>
   <h4> Changing parameters with the HTTP GET method</h4>
   <p>If "&immediate" is added the the URL, the program will apply parameteres (those that have values specified) without opening any forms. If the parameter <b>showseq</b> is specified and positive, the HTML page will show the sequence of parameter application, if it is ommitted or less or equal to 0, the request will generate an XML response with the previous parameter values (before application of the new ones). You may specify different program-ahead values and/or multiple settings for the same parameters - program-ahead values are separated from the parameter values with "*", multiple value/program-ahead pairs for the same parameter are separated with ":". Non-specified program-ahead is currently set to 3.</p>

USAGE;
}


/// show table with last acquired images and meta data
/// TODO: show changed parameters for those frames? ***
function showImgData($meta,$skipped,$prev,$imgScale,$done) {
  global $imgsrv,$imglink;
  $width= $meta['meta']['width'];
  $height=$meta['meta']['height'];
  $title="";
  $frame=$meta['Exif']['FrameNumber'];
  foreach ($meta['Exif'] as $key=>$value) {
    $title.=sprintf("%s=%s    \n",$key,$value);
  }
/*
echo "<pre>";
 print_r($done);
echo "</pre>";
*/
  printf ("<table border='1'>");
  printf ("<tr>");
    printf ("<td colspan=4>");
  printf("<img src='$imgsrv/%d/$imglink' style='width:%dpx; height:%dpx;' title='%s'/>\n",
   $meta['circbuf_pointer'],
   floor($width*$imgScale),
   floor($height*$imgScale),
   $title);
    printf ("</td>");
  printf ("</tr>");
  printf ("<tr>");
    printf ("<td>%s</td><td>%d (0x%x)</td>",'FrameNumber',$frame,$frame);
    printf ("<td>%s</td><td>%d (0x%x)</td>",'width',$meta['meta']['width'],$meta['meta']['width']);

  printf ("</tr>");
  printf ("<tr>");
      $just_time=end(explode(" ", trim($meta['Exif']['DateTimeOriginal'])));
/// just seconds
      $just_time=end(explode(":", $just_time));
    if ($skipped)
      printf ("<td><b>Skipped</b></td><td><b>%d</b></td>",$skipped);
    else
      printf ("<td>&nbsp;</td><td>&nbsp;</td>");
    printf ("<td>%s</td><td>%d (0x%x)</td>",'height',$meta['meta']['height'],$meta['meta']['height']);
  printf ("</tr>");
  printf ("<tr>");
    printf ("<td>%s</td><td>%s</td>",'TimeOriginal',$just_time);
    printf ("<td>%s</td><td>%d (0x%x)</td>",'quality2',$meta['meta']['quality2'],$meta['meta']['quality2']);
  printf ("</tr>");
  printf ("<tr>");
    printf ("<td>%s</td><td>%s</td>",'ExposureTime',trim($meta['Exif']['ExposureTime']));
    printf ("<td>%s</td><td>%d</td>",'color',$meta['meta']['color']);
  printf ("</tr>");
  if ($done) {
    printf ("<tr>");
    printf ("<td colspan='4'>");

    printf   ("<table border='1' style='width:100%s'>\n",'%');
    foreach ($done as $action_frame=>$actions) {
      $firstRow=true;
       printf("<tr><td rowspan='%d'>%s</td>",count($actions),($action_frame==$frame)?"now":("prev ".($frame-$action_frame)));
       foreach ($actions as $key=>$value) {
         if (!$firstRow) printf ("<tr>");
         else $firstRow=false;
         printf("<td>%s</td><td style='text-align:right'>%d</td><td>0x%x</td>",$key,$value,$value);
         printf ("</tr>\n");
       }
    }
    printf ("</table>");

//print_r($done);
    printf ("</td>");
    printf ("</tr>");
  }
  printf ("</table>");

//  print_r($meta['Exif']);
}

///TODO:if $todo is provided in $_GET - try to find the correct images even if they are not the latest

function showLastImages($numImg, $imagesPerRow, $imgScale) {
//	elphel_update_exif(); // just for testing
	$done = decodeTodo ( $_GET ['done'] );
	// $this_exif=elphel_get_exif_elphel(0);
	$circbuf_pointers = elphel_get_circbuf_pointers ( $GLOBALS [sensor_port], 1 );
	echo "<!--";
	var_dump($circbuf_pointers);
	var_dump($done);
	var_dump($numImg);
	echo "-->";
	$framesAgo = 0;
	// echo "<pre>\n";
	end ( $circbuf_pointers );
	if ($done) {
		end ( $done );
		$lastFrameNumber = key ( $done );
//		$lastFrameNumber = key ( $done ) + 9; // NC393 debugging
		$cur_ptr = current ( $circbuf_pointers );
		while ( $cur_ptr ['frame'] > $lastFrameNumber ) {
			if (! prev ( $circbuf_pointers )) { // / failed to find the right frame in circbuf - probably overwritten
				end ( $circbuf_pointers );
				break;
			}
			$cur_ptr = current ( $circbuf_pointers );
			$framesAgo ++;
		}
	}
	// /TODO: If all changes were later than the images shown - disregard $todo
	// print_r($circbuf_pointers);
	// print_r($done);
	
	// echo "</pre>\n";
	$meta = array ();
	// end($circbuf_pointers);
	$lastFrameIndex = key ( $circbuf_pointers );
	for($i = 0; $i <= min ( ($numImg - 1), $lastFrameIndex ); $i ++) {
		$meta [$i] = array (
				'circbuf_pointer' => $circbuf_pointers [$lastFrameIndex - ($numImg - 1) + $i] ['circbuf_pointer'],
				'meta' => elphel_get_interframe_meta (
						$GLOBALS [sensor_port],
						$circbuf_pointers [$lastFrameIndex - ($numImg - 1) + $i] ['circbuf_pointer'] ),
				'Exif' => elphel_get_exif_elphel (
						$GLOBALS [sensor_port],
						$circbuf_pointers [$lastFrameIndex - ($numImg - 1) + $i] ['exif_pointer'] ) 
		);
		$lastFrameNumber = $circbuf_pointers [$lastFrameIndex - ($numImg - 1) + $i] ['frame'];
	}
	echo "<!--";
	var_dump($meta);
	echo "-->";
	
	$running = (elphel_get_P_value ( $GLOBALS [sensor_port], ELPHEL_COMPRESSOR_RUN ) == ELPHEL_CONST_COMPRESSOR_RUN_CONT) && (elphel_get_P_value ( $GLOBALS [sensor_port], ELPHEL_SENSOR_RUN ) == ELPHEL_CONST_SENSOR_RUN_CONT);
	$page_title = sprintf ( "%s %d images acquired to the circular buffer (circbuf). Acquisition is %s. Last frame is %d", $framesAgo ? "$framesAgo frames (stored) ago" : "Latest", $numImg, $running ? "on - these frames are/will be overwritten in the camera memory" : "off", $lastFrameNumber );
	/*
	 * $page_title=sprintf("%d: %s %d images acquired to the circular buffer (circbuf). Acquisition is %s"
	 * ,$lastFrameNumber
	 * ,$framesAgo?"$framesAgo frames ago":"Latest"
	 * ,$numImg
	 * ,$running?"on - these frames are/will be overwritten in the camera memory":"off"
	 * );
	 */
	startPage ( $page_title, "" );
	printf ( "<h4>%s</h4>\n", $page_title );
	printf ( "<table>\n" );
	$rowOpen = $false;
	$lastFrame = 0;
	$done_left = count ( $done );
	reset ( $done );
	$slice_start = 0;
	$slice_count = 0;
	for($i = 0; $i < $numImg; $i ++) {
		$slice_start += $slice_count;
		$slice_count = 0;
		$frame = $meta [$i] ['Exif'] ['FrameNumber'];
		while ( $done_left && (key ( $done ) <= $frame) ) {
			$slice_count ++;
			$done_left --;
			next ( $done );
		}
		// /$done per image
		$this_done = array_slice ( $done, $slice_start, $slice_count, true );
		if (! ($i % $imagesPerRow)) {
			if ($rowOpen) {
				printf ( "</tr>\n" );
			}
			printf ( "<tr>\n" );
			$rowOpen = true;
		}
		printf ( "<td style='vertical-align: top;'>" );
		$skipped = ($i > 0) ? ($frame - $lastFrame - 1) : 0;
		$lastFrame = $frame;
		showImgData ( $meta [$i], $skipped, $numImg - $i - 1, $imgScale, $this_done );
		printf ( "</td>\n" );
	}
	while ( $i ++ % $imagesPerRow ) {
		printf ( "<td>&nbsp;</td>\n" );
	}
	printf ( "</tr>\n" );
	printf ( "</table>\n" );
	endPage ();
}

/**
 * @brief Encode $todo to a string that can be passed in GET HTTP request
 * @param $todo - array of arrays of parameter chnages
 * @param $frame_zero - sequence start frame number that should be added to keys in $todo to get absolute frame numbers
 * @return string representation of $todo
 */
function encodeTodo($todo,$frame_zero) {
   $result="";
   foreach ($todo as $frame=>$actions) {
     $result.=sprintf("%d/",$frame+$frame_zero);
     foreach ($actions as $par=>$value) {
       $result.=sprintf("%s:%d/",$par,$value);
     }
   }
  return $result;
}

/**
 * @brief Reverse endodeTodo() - create $todo array from the encoded string
 * @param $encoded_todo - string representation of todo array
 * @return array representation of $encoded_todo
 */
function decodeTodo ($encoded_todo) {
  $todo=array();
  $frame=0;
  if (!$encoded_todo) return $todo;
  $done=explode("/",rtrim($encoded_todo,'/'));
  foreach ($done as $term) {
    $term=explode(":",$term);
    if (count($term)==1) {
      $frame=$term[0];
      $todo[$frame]=array();
    } else {
      $todo[$frame][$term[0]]=(int)$term[1];
    }
  }
  return $todo;
}


function  showSequence($todo,$frame_zero) {
   printf("<h4>Command Sequence (%s)</h4>\n",$frame_zero?"absolute frame numbers":"relative frame numbers");
   printf   ("<table border='1' style='font-family: Courier, monospace;'>\n");
   printf   ("<tr style='text-align:center'>".
             "<td colspan=2>Frame</td>".
             "<td rowspan=2>Parameter name</td>".
             "<td colspan=2>Value</td>".
             "</tr>\n");
   printf   ("<tr style='text-align:center'>".
             "<td>dec</td>".
             "<td>hex</td>".
             "<td>dec</td>".
             "<td>hex</td>".
             "</tr>\n");
   foreach ($todo as $frame=>$actions) {
     $first_act=true;
     printf ("<tr style='text-align:right'>\n");
     foreach ($actions as $name=>$value) {
       if ($first_act) {
         printf ("<td rowspan=%d>%d</td><td rowspan=%d>0x%x</td>",count($actions),($frame+$frame_zero),count($actions),($frame+$frame_zero));
       }
       printf ("<td style='text-align:left'>%s</td>".
               "<td>%d</td>".
               "<td>0x%x</td>".
               "</tr>\n",$name,$value,$value);
       $first_act=false;
     } 
   }

   printf ("</table>\n"); 
}

function  applyPost_debug($todo,$noFinalWait=false) {
   global $maxahead,$minahead,$frame_zero,$showSeqMode;
   if ($showSeqMode>0) {
     printf("<h4>Running sequence...</h4>\n");
     echo "<pre>";
   }
//   print_r($_POST);
//   print_r($posted_params);
//   var_dump($todo);
//   print_r($todo);

/// Skip to the next frame (so more deterministic phase - maximal time till next frame)
   $waitingEnabled=true;
   /*
   foreach ($todo as $pars) if (array_key_exists('SENSOR', $pars)) {
     $waitingEnabled=false;
     break;
   }
   */
   if (elphel_get_frame($GLOBALS [sensor_port])< 8) $waitingEnabled=false; /// or is "==0" enough?
   if ($waitingEnabled && !$noFinalWait) elphel_skip_frames($GLOBALS [sensor_port],1); // in GET mode, do not skip any frames
/// store the current frame number as reference for all actions delays
   $frame_zero=elphel_get_frame($GLOBALS [sensor_port]);
   $frame_since=0;
   $frame_now=$frame_zero;
///Iterate through $todo array, programming the parameter changes
   foreach ($todo as $since=>$pgmpars) {
     if (($since-$maxahead) >$frame_since ) { /// too early to program, need to wait
       $frame_since=$since-$minahead;
       $frame_now=$frame_since+$frame_zero;
			if ($waitingEnabled) {
				if ($showSeqMode > 0) {
					printf ( "waiting frame %d (0x%x) ...s=%d, fs=%d noFinalWait=",
							$frame_now, $frame_now, $since, $frame_since, $noFinalWait);
					ob_flush ();
					flush ();
				}
				elphel_wait_frame_abs ( $GLOBALS [sensor_port], $frame_now );
				if ($showSeqMode > 0) {
					printf ( "done\n" );
					ob_flush ();
					flush ();
				}
			}
     }
     elphel_set_P_arr ($GLOBALS [sensor_port], $pgmpars, $frame_zero+$since,ELPHEL_CONST_FRAMEPAIR_FORCE_NEWPROC); /// Are these flags needed?
//     if ($showSeqMode > 0) {
//     	printf ( "frame_zero=%d, since=%d",$frame_zero,$since);
//     	printf ( "elphel_set_P_arr ($GLOBALS [sensor_port], $pgmpars, $frame_zero+$since,ELPHEL_CONST_FRAMEPAIR_FORCE_NEWPROC)");
//     	ob_flush ();
//     	flush ();
//     }
   }
   if (!$noFinalWait) {
     $frame_now=$since+$frame_zero+1; /// wait just 1 frame longer that the target of the last command in $todo
//     $frame_now+=256;
     //     if ($showSeqMode > 0) {
//     	printf ( "frame_zero=%d, since=%d",$frame_zero,$since);
//     	printf ( "elphel_set_P_arr ($GLOBALS [sensor_port], $pgmpars, $frame_zero+$since,ELPHEL_CONST_FRAMEPAIR_FORCE_NEWPROC)");
//     	ob_flush ();
//     	flush ();
//     }
      
     //   echo "since=$since\n"; ob_flush();  flush();
     if ($showSeqMode>0) {printf ("(final) waiting frame %d (0x%x) ... ",$frame_now,$frame_now);  ob_flush();  flush();}
     if ($waitingEnabled) {
        $rslt = elphel_wait_frame_abs($GLOBALS [sensor_port], $frame_now);
     } else {
       $timeout_step=  100000; /// 0.1 sec
       $timeout=      3000000; /// 3.0sec
       for ($i=0 ; $i < $timeout; $i+=$timeout_step) {
        if (elphel_get_frame($GLOBALS [sensor_port])>=$frame_now) break;
        usleep($timeout_step);
       }
     }
   }
//   $rslt2 = elphel_wait_frame_abs($GLOBALS [sensor_port], 100);
//   $rslt3 = elphel_wait_frame_abs($GLOBALS [sensor_port], $frame_now);
//   $rslt4 = elphel_skip_frames($GLOBALS [sensor_port],4);
    
   if ($showSeqMode>0) {printf ("done, frame is %d, rslt=%d, frame_now was %d, waitingEnabled=%d\n",
   		elphel_get_frame($GLOBALS [sensor_port]), $rslt, $frame_now, $waitingEnabled);
   		ob_flush();  flush();
   }
   if ($showSeqMode>0) echo "</pre>";
//   exit (0);
}

function  applyPost($todo,$noFinalWait=false) {
	global $maxahead,$minahead,$frame_zero,$showSeqMode;
	if ($showSeqMode>0) {
		printf("<h4>Running sequence...</h4>\n");
		echo "<pre>";
	}
	//   print_r($_POST);
	//   print_r($posted_params);
	//   var_dump($todo);
	//   print_r($todo);
	/// Skip to the next frame (so more deterministic phase - maximal time till next frame)
	$waitingEnabled=true;
	foreach ($todo as $pars) if (array_key_exists('SENSOR', $pars)) {
		$waitingEnabled=false;
		break;
	}
	if (elphel_get_frame($GLOBALS [sensor_port])< 8) $waitingEnabled=false; /// or is "==0" enough?
	if ($waitingEnabled && !$noFinalWait) elphel_skip_frames($GLOBALS [sensor_port],1); // in GET mode, do not skip any frames
	/// store the current frame number as reference for all actions delays
	$frame_zero=elphel_get_frame($GLOBALS [sensor_port]);
	$frame_since=0;
	$frame_now=$frame_zero;
	///Iterate through $todo array, programming the parameter changes
	foreach ($todo as $since=>$pgmpars) {
		if (($since-$maxahead) >$frame_since ) { /// too early to program, need to wait
			$frame_since=$since-$minahead;
			$frame_now=$frame_since+$frame_zero;
//			$frame_now=$frame_since+$frame_zero+2; // NC393: adding 2 extra
			if ($waitingEnabled) {
//			$frame_now=$frame_since+$frame_zero;
				if ($showSeqMode>0) {printf ("waiting frame %d (0x%x) ... ",$frame_now,$frame_now);  ob_flush();  flush();}
				elphel_wait_frame_abs($GLOBALS [sensor_port], $frame_now);
				if ($showSeqMode>0) {printf ("done (%d)\n", elphel_get_frame($GLOBALS [sensor_port]));   ob_flush();  flush();}
			}
		}
		elphel_set_P_arr ($GLOBALS [sensor_port], $pgmpars, $frame_zero+$since,ELPHEL_CONST_FRAMEPAIR_FORCE_NEWPROC); /// Are these flags needed?
	}
	if (!$noFinalWait) {
		$frame_now=$since+$frame_zero+1; /// wait just 1 frame longer that the target of the last command in $todo
//		$frame_now=$since+$frame_zero+4; /// wait just 1 frame longer that the target of the last command in $todo
		//   echo "since=$since\n"; ob_flush();  flush();
		if ($showSeqMode>0) {printf ("waiting frame %d (0x%x) ... ",$frame_now,$frame_now);  ob_flush();  flush();}
		if ($waitingEnabled) {
			elphel_wait_frame_abs($GLOBALS [sensor_port], $frame_now);
		} else {
			$timeout_step=  100000; /// 0.1 sec
			$timeout=      3000000; /// 3.0sec
			for ($i=0 ; $i < $timeout; $i+=$timeout_step) {
				if (elphel_get_frame($GLOBALS [sensor_port])>=$frame_now) break;
				usleep($timeout_step);
			}
		}
//	} else { // just debugging - still waiting
//		elphel_skip_frames($GLOBALS [sensor_port],8); // skip 8 frames - debugging NC393 - no differnce!
	}
	if ($showSeqMode>0) {printf ("done (%d, 0x%x)\n", elphel_get_frame($GLOBALS [sensor_port]), elphel_get_frame($GLOBALS [sensor_port]));   ob_flush();  flush();}
	if ($showSeqMode>0) echo "</pre>";
	//   exit (0);
}


function  parsePost() {
   global $_POST,$testMode,$showSeqMode,$posted_params;
   $posted_params=array();
/// parse to an array of parameters/changes
   $testMode=$_POST['test_mode'];
   $showSeqMode=$_POST['show_seq'];
   foreach ($_POST as $post=>$value) {
     if (($post=="test_mode") || ($post=="show_seq")) {
// nop;
     } else {
       list ($name,$index)=explode('_',$post);
       $index = (int) $index;
       if (!$posted_params[$index]) $posted_params[$index]=array();
       $posted_params[$index][$name]=$value;
     }
   }
}
/// Simulate POST from URL parameters if 'immediate' is present in the URL
function convertImmediateMode(){
   global $posted_params,$global_params,$frame_params;
   $posted_params=array();
   $index=0;
   foreach ($global_params as $param) {
       $posted_params[$index++]=array(
                'name'   =>   $param['name'],
                'oldval' =>   $param['cur_value'],
                'paramdec' => $param['value'], /// 'paramhex' is not used
                'delay'=>     $param['ahead'],
                'apply' =>    $param['write_en'] && $param['modified']
             );
   }
   foreach ($frame_params as $param) {
       $posted_params[$index++]=array(
                'name'   =>   $param['name'],
                'oldval' =>   $param['cur_value'],
                'paramdec' => $param['value'], /// 'paramhex' is not used
                'delay'=>     $param['ahead'],
                'apply' =>    $param['write_en'] && $param['modified']
             );
   }
}
function extractNames($arr){
  $names=array();
  foreach ($arr as $element) $names[$element['name']]=0; // values will not be used;
  return $names;
}

function  preparePost() {
   global $testMode,$testBefore,$testAfter,$framesBeforeStart,$posted_params;
   $todo=array();
/// Now need to find minimal index in $todo and add compressor stop/copmpressor start
/// Extract data about actual actions, organize them in frames ahead (all globals will have ahead=0)
   foreach ($posted_params as $par) if ($par['apply']){
     if (array_key_exists('delay',$par)) {
       $delay = (int) $par['delay']  + (($testMode>0)?($testBefore+$framesBeforeStart):0);
     } else $delay=0;
     if (!$todo[$delay]) $todo[$delay]=array();
     $todo[$delay][$par['name']]=(int) $par['paramdec'];
   }
/// sort $todo using time (key)
   ksort($todo); /// needed to process parameters in temporal sequence
   if ($testMode>0) {
     end($todo);
     $todo[key($todo)+$testAfter]=array("COMPRESSOR_RUN"=>ELPHEL_CONST_COMPRESSOR_RUN_STOP);
     $todo[$framesBeforeStart]=array("COMPRESSOR_RUN"=>ELPHEL_CONST_COMPRESSOR_RUN_CONT);
     ksort($todo); /// re-sort it after adding start/stop
   }
   return $todo;
}

//   global $_POST,$testMode,$showSeqMode,$testBefore,$testAfter,$framesBeforeStart,$posted_params;


/**
 * @brief Scan commands for possible changing gamma tables, calculate them in advance
 * (driver can only scale gamma, not calculate prototypes)
 * @param todo - array of arrays of parameter chnages
 */

function  addGammas($todo) {
   global $showSeqMode;
   $gammas=array();
   foreach ($todo as $pars) {
     if (array_key_exists('GTAB_R', $pars)) $gammas[$pars['GTAB_R' ]>>16]=1; /// duplicates will be eliminated
     if (array_key_exists('GTAB_G', $pars)) $gammas[$pars['GTAB_G' ]>>16]=1;
     if (array_key_exists('GTAB_GB',$pars)) $gammas[$pars['GTAB_GB']>>16]=1;
     if (array_key_exists('GTAB_B', $pars)) $gammas[$pars['GTAB_B' ]>>16]=1;
   }
//   var_dump($gammas);
   foreach ($gammas as $gamma_black=>$whatever) {
     $black=($gamma_black>>8) & 0xff;
     $gamma=($gamma_black & 0xff)*0.01;
     if ($showSeqMode>0) printf("<pre>Adding gamma table (gamma=%f, black level=%d)\n</pre>\n",$gamma,$black);
     elphel_gamma_add ($gamma, $black); // does not need $GLOBALS [sensor_port]
   }
}

function myval ($s) {
  $s=trim($s,"\" ");
  if (strtoupper(substr($s,0,2))=="0X")   return intval(hexdec($s));
  else return intval($s);
}


function parseGetNames() {
   global $_GET,$_POST,$isPost,$elp_const,$frame_params,$global_params,
          $page_title,$default_ahead,$maxahead,$brief,$ahead_separator,
          $refreshSig,$ignoreVals,$testMode,$showSeqMode,$posted_params,$defaultImgScale,$defaultImagesPerRow,$defaultImagesNumber,
          $imagesNumber,$imagesPerRow,$imgScale,$embedImageScale, $defaultEmbedImageScale;
   $index=0;
   $frame_params=array();
   $global_params=array();
   $immediateMode=false; /// if true - no dialog, just apply parameters from the URL
   foreach ($_GET as $key=>$value) {
      if ($key=="immediate") {
        $immediateMode=true;
      } else if ($key=="title") {
        $page_title=$value;
      } else if ($key=="shownumbers") {
        $brief=false;
      } else if ($key==$refreshSig) {
        $ignoreVals=true;
      } else if ($key=='test') {
        $testMode=   myval($isPost?$_POST['test_mode']:$value);
      } else if ($key=='showseq') {
        $showSeqMode=myval($isPost?$_POST['show_seq']:$value);
      } else if (($key=='images') || ($key=='link_images')) {
        if ($value) $value=explode(':',$value);
        else $value=array();
        $imagesNumber=$value[0]?$value[0]:$defaultImagesNumber;
        $imagesPerRow=$value[1]?$value[1]:$defaultImagesPerRow;
        $imgScale=    $value[2]?$value[2]:$defaultImgScale;
        if ($key=='images') {
          showLastImages($imagesNumber,$imagesPerRow,$imgScale);
          exit(0);
        }
      } else if ($key=='embed') {
        $embedImageScale=   $value;
        if (!$embedImageScale) $embedImageScale=$defaultEmbedImageScale;
      } else if ($key=='_time') {
      } else if ($key=='sensor_port') {
      		 
      } else {
/// locate $key among constants, accept numeric values also
        $address=myval ($key);
        if (($address==0) && (strlen($key)>3)) { /// suspect constant
          $address=elphel_parse_P_name($key); // does not need $GLOBALS [sensor_port],
        }
        if ($address==0) {
          $xml = new SimpleXMLElement("<?xml version='1.0'?><framepars/>");
          $xml->addChild ('ERROR','"Wrong address==0, probably misspelled constant: \''.$key.'\'"' );
          $rslt=$xml->asXML();
          header("Content-Type: text/xml");
          header("Content-Length: ".strlen($rslt)."\n");
          header("Pragma: no-cache\n");
          printf($rslt);
          exit (0);
        }
        $write_en=(!($value[0]=="@"));
        if (!$write_en) {
          if (strlen($value)==1) $value="";
          else $value=substr($value,1);
        }
        $values=explode(":", $value);
        foreach ($values as $value) {
          $modified=false;
          $value=explode($ahead_separator,$value);
          if (count($value)>1) {
            $ahead=myval($value[1]);
            if ($ahead<0) $ahead=0;
          } else {
            $ahead=$default_ahead;
          }
          $modified=(strlen($value[0])>0);
          $value=myval($value[0]);
          if ($isPost) {
            $ahead=$posted_params[$index]['delay'];
          }
          if (elphel_is_global_par($address)) { // does not need $GLOBALS [sensor_port],

            $global_params[$index++]=array("number"=>$address,
                                           "name"=>$key,
                                           "value"=>$value,
                                           "write_en"=>$write_en,
                                           "cur_value"=>"",
                                           'modified'=>$modified,
                                           'ahead'=>$ahead);
          } else {
            $frame_params [$index++]=array("number"=>$address,
                                           "name"=>$key,
                                           "value"=>$value,
                                           "write_en"=>$write_en,
                                           "cur_value"=>"",
                                           'modified'=>$modified,
                                           'ahead'=>$ahead);
          }
       }
     }
   }
   $page_title.=': port '.$GLOBALS [sensor_port];
   return  $immediateMode;
}
function readCurrentParameterValues() {
   global $frame_params,$global_params,$page_title,$ignoreVals;
   $pars=array();
   foreach ($frame_params as $par) {
      $pars[$par['name']]=0;                /// may be duplicates (they will use the same $pars[] element
   }
   foreach ($global_params as $par) {
      $pars[$par['name']]=0;
   }
// echo "<pre>";
//print_r($pars);
   $pars=elphel_get_P_arr($GLOBALS [sensor_port], $pars); /// next2 frame/globals
//print_r($pars);
   foreach ($frame_params as $key=>$par) {
      $frame_params[$key]['cur_value']=$pars[$par['name']];
      $par['cur_value']=$pars[$par['name']];
      if (!$par['modified'] || $ignoreVals) $frame_params[$key]['value']=$par['cur_value'];
//echo "pars[par['name']]=".$pars[$par['name']]."\n";
//print_r($par);
   }
   foreach ($global_params as $key=>$par) {
      $global_params[$key]['cur_value']=$pars[$par['name']];
      $par['cur_value']=$pars[$par['name']];
      if (!$par['modified'] || $ignoreVals) $global_params[$key]['value']=$par['cur_value'];
//print_r($par);
   }
}
///============
function mainJavascript($refreshSig) {
   global $refreshSig,$frame_params,$global_params;
   $checkboxNumbers="";
   foreach ($frame_params as $num=>$par) {
     $checkboxNumbers.=$num.",";
   }
   foreach ($global_params as $num=>$par) {
     $checkboxNumbers.=$num.",";
   }
   $checkboxNumbers=rtrim ($checkboxNumbers,",");

return <<<JAVASCRIPT
function hex2dec(h) {
 var dec=0;
 var d;
 h=h.toUpperCase();
 while (h.length>0) {
   d=h.charCodeAt(0)-"0".charCodeAt(0);
   if (d>9) d= (h.charCodeAt(0))-("A".charCodeAt(0))+10;
   dec=dec*16+d;
   h=h.substring(1,h.length);
 }
 return dec;
}
function dec2hex(d) {
 var hex="";
 var d0,d1;
 if (d<0) d+=4294967296;
 if ((d<0) || ( d>4294967296)) return "NaN";
 while (d>0) {
  d1=Math.floor(d/16);
  d0=d-16*d1;
  d=d1;
  hex=("0123456789abcdef".charAt(d0))+hex;
 }
 return hex;
}
function onchangeDec(elem,id_hex,id_apply) {
//  alert ("onchangeDec("+elem.id+","+id_hex+","+id_apply+")");
  document.getElementById(id_hex).value=dec2hex(document.getElementById(elem.id).value);
  document.getElementById(id_apply).checked=true;
}
function onchangeHex(elem,id_dec,id_apply) {
//  alert ("onchangeHex("+elem.id+","+id_dec+","+id_apply+")");
  document.getElementById(id_dec).value=hex2dec(document.getElementById(elem.id).value);
  document.getElementById(id_apply).checked=true;
}
function onchangeDelay(elem,id_apply) {
//  alert ("onchangeDelay("+elem.id+","+id_apply+")");
  var d=document.getElementById(elem.id).value;
  if (d<0) document.getElementById(elem.id).value=0;
  document.getElementById(id_apply).checked=true;
}
function refreshPage(mode) {
  var url=window.location.href;
  var refreshSign="&"+"$refreshSig";
  var refIndex,index1;
///remove &$refreshSig from the url (if any)
  while (url.indexOf(refreshSign)>=0) {
    refIndex=url.indexOf(refreshSign);
    url=url.substr(0,refIndex)+url.substr(refIndex+refreshSign.length);
  }
  if (mode) url+=refreshSign;
///Preserve test_mode through GET
  var testModeName="test";
  refIndex=url.indexOf("&"+testModeName);
  if (refIndex<0)refIndex=url.indexOf("?"+testModeName);
  if (refIndex>=0){ // test should be in the URL or it will not be shown on a page
    index1=url.indexOf("&",refIndex+testModeName.length);
    if (index1<0) index1= url.length;
    refIndex+=testModeName.length+1;
    url=url.substr(0,refIndex)+"="+(document.getElementById("id_test_mode").checked?1:0)+url.substr(index1);
  }
//  alert ("url="+url);
///Preserve show sequence through GET
  var showSeqName="showseq";
  refIndex=url.indexOf("&"+showSeqName);
  if (refIndex<0)refIndex=url.indexOf("?"+showSeqName);
  if (refIndex>=0){ // test should be in the URL or it will not be shown on a page
    index1=url.indexOf("&",refIndex+showSeqName.length);
    if (index1<0) index1= url.length;
    refIndex+=showSeqName.length+1;
    url=url.substr(0,refIndex)+"="+(document.getElementById("id_show_seq").checked?1:0)+url.substr(index1);
  }
//  alert ("url="+url);
  window.location.href=url;
}
function unCheckAll() {
  checkboxes=Array($checkboxNumbers);
  for (i in checkboxes) {
    if (((cb=document.getElementById('id_apply_'+checkboxes[i])))) {
      cb.checked=false;
    }
  }
}
function checkAll() {
  checkboxes=Array($checkboxNumbers);
  for (i in checkboxes) {
    if (((cb=document.getElementById('id_apply_'+checkboxes[i])))) {
      cb.checked=true;
    }
  }
}
JAVASCRIPT;
}



function startPage($page_title, $javascript) {
echo <<<HEAD
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
                      "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
 <title>$page_title</title>
 <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<script type="text/javascript"><!--
$javascript
//-->
</script>
</head>
<body>
HEAD;
}
function  endPage(){
  echo "\n</body></html>\n";
}

/// Called twice from printPage($encoded_todo)
function showControlButtonsRow($table_width,$readonly,$testMode,$showSeqMode,$encoded_todo,$imagesNumber,$imagesPerRow,$imgScale,$checkNotUncheck) {
   $url_port = "sensor_port=".$GLOBALS [sensor_port]."&";
   printf ("<tr style=''><td colspan=%d>",$table_width-($readonly?0:1));
   if (!$readonly) {
     if ($testMode>=0) {
       printf ("<label style='border: 1px solid #000;' for='id_test_mode' title='Add compressor start (before) and compressor stop (after) the sequence'>".
               "Test".
               "<input type='checkbox' name='test_mode' id='id_test_mode' value='1' %s".
               " />".
               "</label>",
               ($testMode>0)?'checked':'');
     }
     if ($showSeqMode>=0) {
       printf ("<label style='border: 1px solid #000;' for='id_show_seq' title='Show command sequence and the sequence progress'>".
               "ShowSeq".
               "<input type='checkbox' name='show_seq' id='id_show_seq' value='1' %s".
               " />".
               "</label>",
               ($showSeqMode>0)?'checked':'');
     }
     printf ("<input type='submit' value='Apply' title='Program the parameters (selected by \"Apply\" checkboxes) to the camera'/>\n");
   }
   printf ("<input type='button' value='Refresh'onclick='refreshPage(1);' title='Reload the page, keep camera parameters (does not preserve delays yet)'/>\n");
   printf ("<input type='button' value='Restart'onclick= 'refreshPage(0);' title='Reload the page, keep URL parameters'/>\n");
   if ($encoded_todo) { // show link to last images
    printf ("<a href='?".$url_port."images=%d:%d:%f&done=%s' target='new'>Last acquired images</a>\n",$imagesNumber,$imagesPerRow,$imgScale,$encoded_todo);
   }
   if (!$readonly) {
     if ($checkNotUncheck)
       printf ("</td><td style='text-align:center'><input type='button' value='&nbsp;Select&nbsp;\nAll'onclick='checkAll();' title='Check all \"Apply\" the checkboxes'/>\n");
     else
       printf ("</td><td style='text-align:center'><input type='button' value='Deselect\nAll'onclick='unCheckAll();' title='Uncheck all \"Apply\" the checkboxes'/>\n");
   }
   printf ("</td></tr>\n");
}

function printPage($encoded_todo) {
   global $frame_params,$global_params,$page_title,$brief,$testMode,$showSeqMode,
                    $imagesNumber,$imagesPerRow,$imgScale,$embedImageScale,$imgsrv,$imglink,$descriptions;

   $readonly=true;
   foreach ($frame_params as $par) if ($par['write_en']) {
      $readonly=false;
      break;
   }
   if ($readonly) foreach ($global_params as $par) if ($par['write_en']) {
      $readonly=false;
      break;
   }
   $table_width=$readonly?5:9;
   if ($brief) $table_width-=2;
//   printf ("<center><h3>$page_title</h3></center>\n");
///   printf ("<h3>$page_title</h3>\n");
   printf ("<form action=\"$self\" method=\"post\">");
   printf ("<table border='1' style='font-family: Courier, monospace;'>\n");

   if ($embedImageScale) {
     $fd_circ=fopen("/dev/circbuf".strval($GLOBALS['sensor_port']),"r");
     fseek($fd_circ, ELPHEL_LSEEK_CIRC_LAST,SEEK_END);
     $circbuf_pointer=ftell($fd_circ);
     fclose($fd_circ);

     if ($circbuf_pointer>=0) {
       $meta=elphel_get_interframe_meta($GLOBALS [sensor_port], $circbuf_pointer);
       $width= floor($meta['width']*$embedImageScale);
       $height=floor($meta['height']*$embedImageScale);
//echo "width=$width, height=$height, embedImageScale=$embedImageScale<br />\n";
       printf ("<tr style='text-align:center'>".
               "<td colspan=$table_width>");
///       printf ("<a href='$imgsrv/%d/$imglink'><img src='$imgsrv/%d/$imglink' style='width:%dpx; height:%dpx;' /></a>",$circbuf_pointer,$circbuf_pointer, $width,$height); 
       printf ("<a href='$imgsrv/$imglink' target='new'><img src='$imgsrv/%d/$imglink' style='width:%dpx; height:%dpx;' title='Click on the image to open the last acquired one - it may be different than this one (if acquisition is going on)' /></a>",$circbuf_pointer, $width,$height); 
       printf ("</td></tr>\n");
     }

   }
   showControlButtonsRow($table_width,$readonly,$testMode,$showSeqMode,$encoded_todo,$imagesNumber,$imagesPerRow,$imgScale,true);

   printf ("<tr style='text-align:center'>".
           "<td rowspan=2>Parameter name</td>");
   if (!$brief) {printf (
           "<td colspan=2>Number</td>");
   }
   printf ("<td colspan=2>Current value</td>");
   if (!$readonly) {printf (
            "<td colspan=2>New value</td>".
            "<td rowspan=2>Program<br/>ahead</td>".
            "<td rowspan=2>Apply</td></tr>");
   }
   printf   ("</tr>");
   printf ("<tr style='text-align:center'>");
   if (!$brief) {printf (
           "<td>&nbsp;dec&nbsp;</td>".
           "<td>&nbsp;hex&nbsp;</td>");
   }
   printf ("<td>&nbsp;&nbsp;&nbsp;dec&nbsp;&nbsp;&nbsp;&nbsp;</td>".
           "<td>&nbsp;&nbsp;&nbsp;hex&nbsp;&nbsp;&nbsp;&nbsp;</td>");
   if (!$readonly) {printf (
           "<td>&nbsp;&nbsp;&nbsp;dec&nbsp;&nbsp;&nbsp;&nbsp;</td>".
           "<td>&nbsp;&nbsp;&nbsp;hex&nbsp;&nbsp;&nbsp;&nbsp;</td>");
   }
   printf   ("</tr>");
   if (count($frame_params)) {
     printf ("<tr><td colspan='$table_width' style='text-align:center'>Frame-related parameters</td></tr>\n");
     foreach ($frame_params as $num=>$par) {
//       printf ("<tr title='%s'>",$descriptions[$par['name']]);
       printf ("<tr title='%s'>",getDescription ($par['name'],$descriptions));
       printf ("<td>%s</td>",$par['name']);
   if (!$brief) {
       printf ("<td style='text-align:right'>%d</td>",$num);
       printf ("<td style='text-align:right'>0x%x</td>",$num);
      }
       printf ("<td style='text-align:right'>%d</td>",$par['cur_value']);
       printf ("<td style='text-align:right'>0x%x".
                  "<input type='hidden' name='oldval_%d' value='%d'>".
                  "<input type='hidden' name='name_%d' value='%s'>".
                  "</td>",$par['cur_value'],$num,$par['cur_value'],$num,$par['name']);
       if ($par['write_en']) {
         printf ("<td><input name='paramdec_%d' type='text' size='8' value='%d' id='id_dec_%d' style='text-align:right'".
                 " onchange='onchangeDec(this,\"id_hex_%d\",\"id_apply_%d\");'/></td>",$num,$par['value'],$num,$num,$num);
         printf ("<td  style='white-space:nowrap'>0x<input name='paramhex_%d' type='text' size='8' value='%x' id='id_hex_%d' style='text-align:left'".
                 " onchange='onchangeHex(this,\"id_dec_%d\",\"id_apply_%d\");'/></td>",$num,$par['value'],$num,$num,$num);
         printf ("<td style='text-align:center'><input name='delay_%d' type='text' size='4' value='%d' id='id_delay_%d' style='text-align:right'".
                 " onchange='onchangeDelay(this,\"id_apply_%d\");'/></td>",$num,$par['ahead'],$num,$num);
         printf ("<td style='text-align:center'><input type='checkbox' name='apply_%d' value='1' %s id='id_apply_%d'/></td>",$num,$par['modified']?'checked':'',$num);
       }else {
         if (!$readonly) printf ("<td colspan='4'>&nbsp;</td>");
       }
       printf ("</tr>\n");
     }
   }
   if (count($global_params)) {
     if ($table_width<5) printf ("<tr><td colspan='$table_width' style='text-align:center'>Global parameters</td></tr>\n");
     else                printf ("<tr><td colspan='$table_width' style='text-align:center'>Global (not related to particular frames) parameters</td></tr>\n");
     foreach ($global_params as $num=>$par) {
//       printf ("<tr title='%s'>",$descriptions[$par['name']]);
       printf ("<tr title='%s'>",getDescription ($par['name'],$descriptions));
       printf ("<td>%s</td>",$par['name']);
   if (!$brief) {
       printf ("<td style='text-align:right'>%d</td>",$num);
       printf ("<td style='text-align:right'>0x%x</td>",$num);
      }
       printf ("<td style='text-align:right'>%d</td>",$par['cur_value']);
       printf ("<td style='text-align:right'>0x%x".
                  "<input type='hidden' name='oldval_%d' value='%d'>".
                  "<input type='hidden' name='name_%d' value='%s'>".
                  "</td>",$par['cur_value'],$num,$par['cur_value'],$num,$par['name']);
       if ($par['write_en']) {
         printf ("<td><input name='paramdec_%d' type='text' size='8' value='%d' id='id_dec_%d' style='text-align:right'".
                 " onchange='onchangeDec(this,\"id_hex_%d\",\"id_apply_%d\");'/></td>",$num,$par['value'],$num,$num,$num);
         printf ("<td  colspan='2' style='white-space:nowrap'>0x<input name='paramhex_%d' type='text' size='8' value='%x' id='id_hex_%d'  style='text-align:left'".
                 " onchange='onchangeHex(this,\"id_dec_%d\",\"id_apply_%d\");'/></td>",$num,$par['value'],$num,$num,$num);
         printf ("<td style='text-align:center'><input type=\"checkbox\" name=\"apply_%d\" value=\"1\" %s id='id_apply_%d'/></td>",$num,$par['modified']?'checked':'',$num);
       }else {
         if (!$readonly) printf ("<td colspan='4'>&nbsp;</td>");
       }
       printf ("</tr>\n");
     }
   }
   showControlButtonsRow($table_width,$readonly,$testMode,$showSeqMode,$encoded_todo,$imagesNumber,$imagesPerRow,$imgScale,false);
   printf ("</table>\n");
   printf ("</form>\n");
}
function getDescription ($compositeName,$descriptions){
  if (array_key_exists($compositeName,$descriptions)) return $descriptions[$compositeName];
/// try to find a parent name
  $number=elphel_parse_P_name($compositeName); // does not need $GLOBALS [sensor_port],
  if (!$number) return "Unknown name";
  $prefix="";
// is it a bit field?
  $width= ($number >> 21) & 0x1f;
  $bit=   ($number >> 16) & 0x1f;
  $name=$compositeName;
  if ($bit || $width) {
    $name=substr($compositeName,0,strlen($compositeName)-strlen('__XXYY'));
    if ($width==1) $prefix.= sprintf("This is the bit %d of %s - ",$bit,$name);
    else           $prefix.= sprintf("This is a bit selection - bits %d through %d of %s - ",$bit,($bit+$width-1),$name);
    $number=elphel_parse_P_name($name); // does not need $GLOBALS [sensor_port],
    if (!$number) {
        echo "Internal error in parsedit.php:Unknown name $name of $compositeName";
        exit (1);
    }
  }

  if (array_key_exists($name,$descriptions)) return $prefix.$descriptions[$name];
  $index="";
/// see if it has displacement from a known name
  while ($name && strpos (" 0123456789", $name[strlen($name)-1])) {
    $index= $name[strlen($name)-1].$index;
    $name=substr($name, 0, strlen($name)-1);
  }
  if ($prefix) $prefix.="offset ".$index." of ".$name." - ";
  else $prefix.="This is the parameter at offset ".$index." of ".$name." - ";
  if (array_key_exists($name,$descriptions)) return $name.": ".$prefix."++".$descriptions[$name];
  return $prefix."no description available";
}

/*
/// compose bit fields to be OR-ed to the parameter number bit 16..25: b - start bit (0..31), w - width 1..32
#define FRAMEPAIR_FRAME_BITS(w,b) ((((w) & 0x1f)<<21) | (((b) & 0x1f)<<16))
/// Shift new data (nd), apply mask and combine with old data (od), taking shift/width information from bits 16..25 of (a)
#define FRAMEPAIR_FRAME_MASK_NEW(a,od,nd) ((((od) ^ ((nd) << (((a)>>16) & 0x1f))) & (((1 << (((a)>>21) & 0x1f))-1) << (((a)>>16) & 0x1f))) ^ (od))


*/
function getParDescriptions($autocampars) {
  $file=file($autocampars);
  $path="";
  foreach ($file as $line) if (strpos($line,'$configPath')!==false ){
    $name= strtok ($line,'"');
    if (strpos ($name,'//')===false) {
      $path=strtok ('"');
      break;
    }
  }
  $path = str_replace("0.",strval($GLOBALS [sensor_port]).".",$path); // NC393: here it does not really matter
  $xml = simplexml_load_file($path);
  $descriptions=array();
  foreach ($xml->descriptions->children() as $entry) {
    $descriptions[$entry->getName()]=str_replace('`',"\n",trim((string)$entry,"\""));
  }
  return $descriptions;
}
///============
?>
