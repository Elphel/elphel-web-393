<?php
/*!*******************************************************************************
*! FILE NAME  : diag_utils.php
*! DESCRIPTION: Provides development/diagnostic data
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
*!  $Log: diag_utils.php,v $
*!  Revision 1.5  2010/04/30 21:09:00  elphel
*!  added forced synchronization on/off links, parsedit.php link
*!
*!  Revision 1.4  2008/11/30 21:56:39  elphel
*!  Added enforcing limit on the overall gains in the color channels, storage of exposure and gains in the histograms cache (to be used with autoexposure/white balance)
*!
*!  Revision 1.3  2008/11/30 06:41:43  elphel
*!  changed default IP back to 192.168.0.9 (temporarily was 192.168.0.7)
*!
*!  Revision 1.2  2008/11/30 05:02:27  elphel
*!  wrong links
*!
*!  Revision 1.1.1.1  2008/11/27 20:04:03  elphel
*!
*!
*!  Revision 1.1  2008/11/27 09:29:22  elphel
*!  new file - diag_utils.php that includes somewhat cleaned-up version of utilities from framepars.php. That file installation  is moved to attic
*!
*!
*/

    $imgsrv="http://".$_SERVER['SERVER_ADDR'].":8081";
    if (count($_GET)==0) {
       showUsage();
       exit (0);
    }
//$_SERVER["SERVER_ADDR"] . ":8081
    $elp_const=get_defined_constants(true);
    $elp_const=$elp_const["elphel"];
    foreach($_GET as $key=>$value)  switch($key) {
       case "profile":           profileShow(myval($value));                          break;
       case "histogram_direct":
       case "histogram_reverse":
       case "gamma_direct":
       case "gamma_reverse":     showHistGamma ($key,floatval($value));               break;
       case "jpegheader":        showJpegHeader();                                    break;
       case "constants":         echo "<pre>\n";print_r($elp_const); echo "</pre>\n"; break;
       case "frame":             printRawFrame(myval($value));                        break;
       case "hist_raw":          printRawHistogram(0xfff,myval($value));              break;
       case "hist":
       case "histogram":         printHistogram(myval($value));                       break;
       case "gamma":             printGammaStructure();                               break;
       case "gamma_page":        printRawGamma(myval($value));                        break;
       default:                  showUsage();
     }
exit (0);
///==========================================================================================================
function showUsage() {
global $imgsrv;
         echo <<<USAGE
   <p>This is a collection of tools for the 8.0 software development/testing, you can provide parameters in HTTP GET request.</p>
   <p>Here are some of the current options</p>
   <ul>
    <li><a href="?constants">constants</a> - list all defined PHP constants related to Elphel camera</li>
    <li><a href="?profile=50">profile=50</a> - show last 50 (up to ELPHEL_CONST_PASTPARS_SAVE_ENTRIES=2048) profile entries - recorded if profiling is enabled</li>
    <li><a href="?histogram_direct=0.5">histogram_direct=0.5</a> - show part of the pixels below specified output value (on 0..1.0 scale - 0.5 in this example) for each color channel </li>
    <li><a href="?histogram_reverse=0.5">histogram_reverse=0.5</a> - show pixel output value (on 0..1.0 scale) so that specified part of all pixels have output (after gamma conversion) valuebelow it, for each color channel </li>
    <li><a href="?gamma_direct=0.5">gamma_direct=0.5</a> - show output pixel value (on 0..1.0 scale) for the specified (0..1.0 scale) sensor pixel value, for each color channel </li>
    <li><a href="?gamma_reverse=0.5">gamma_reverse=0.5</a> - show what sensor pixel value would produce specified output (after gamma conversion) value (on 0..1.0 scale), for each color channel </li>
    <li><a href="?jpegheader">jpegheader</a> - show data in the current JPEG header</li>
    <li><a href="?frame=0">frame=0</a> - show raw data for the frame parameters internal data. There are 8 parameter pages used in sequence, so only the 3 least significant bits of the frame number are used</li>
    <li><a href="?histogram=prev">histogram=prev</a> - Show histogram data for the specified frame - in addition to the absolute frame number you may specify prev1...prev6. Giving future or too long ago frame will result in an (uncaught) error</li>
    <li><a href="?hist_raw=prev">hist_raw=prev</a> - Show raw histogram data for the specified frame - in addition to the absolute frame number you may specify prev1...prev6. Giving future or too long ago frame will result in an (uncaught) error. It shows the same data as <a href="?histogram=prev">histogram=prev</a> - just uses different access to the histogram arrays.</li>
    <li><a href="?gamma">gamma</a> - show structure of the gamma tables cache. Among othe data it provides links to the individual gamma tables pages. This command takes morfe than 10 seconds to complete.</li>
    <li><a href="?gamma_page=255">gamma_page=255</a> - contents of the gamma tables cache page (0 - index page, 1..255 - cache pages). Individual pages links are provided by the <a href="?gamma">?gamma</a>  command. Some of the tables in the cache (reverse, FPGA) may be missing if they were never requested.</li> 
  </ul>
   <p>There are additional development pages links available:</p>
   <ul>
    <li><a href="parsedit.php">parsedit.php</a> - this page includes links to multiple useful parameter controls as well as explanation how to create custom control pages by just modifying the URL line</li>
    <li><a href="/tuneseq.php">tuneseq.php</a> - run-time tuning of sequencer latencies;</li>
    <li><a href="$imgsrv">imgsrv</a> getting images from the camera internal buffer (you have to acquire them there first)</li>
    <li><i>forced synchronization mode</i>: <a href="/fpga.php?a=0x4e&d=0x60">ON</a> and <a href="/fpga.php?a=0x4e&d=0x40">OFF</a> (default). When this mode is activated the camera will force re-synchronization between the sensor and the FPGA compressor at each frame start. Normally it is not needed and loosing synchronization is usually an indication of a software/fpga bug (there are some rare conditions when this is normal), this is why this mode is disabled by default so the problems would reveal themselves.</li>
    <li><a href="/var/klog.txt">var/klog.txt</a> - read kernel messages (you first need to telnet to the camera and run "printk_mod &amp;" and enable some of the DEBUG bits)</li>
   </ul>
   <h4>Make sure to run "printk_mod &amp;" before enabling debug bits - some are from interrupt service routine, and default printk() output to console can really mess up things and make the camera &quot;freeze&quot;.</h4>


USAGE;
}

function showJpegHeader() {
    $xml = new SimpleXMLElement("<?xml version='1.0'?><framepars/>");
    $circbuf_file=fopen("/dev/circbuf","r");
    fseek($circbuf_file,ELPHEL_LSEEK_CIRC_LAST,SEEK_END);
    $jpeg_start=ftell($circbuf_file);
    $xml->addChild ('circbuf_pointer',sprintf("0x%x (0x%x)",$jpeg_start,$jpeg_start>>2));
    fclose($circbuf_file);
    $header_file=fopen("/dev/jpeghead","r");
/// Now select right frame (different frames may have different header sizes)
    fseek($header_file,$jpeg_start+1,SEEK_END); /// selects frame, creates header
    fseek($header_file,0,SEEK_END);   /// positions to the end
    $header_size=ftell($header_file); ///
    $xml->addChild ('header_size',$header_size);
    fseek($header_file,0,SEEK_SET);   /// positions to the beginning
    $header=fread ($header_file,8192);
    $xml->addChild ('header_read_length',strlen($header));
    fclose($header_file);
    $aheader=unpack('C*',$header);
    for ($i=0; $i<count($aheader) ;$i+=16){
      $d="";
      for ($j=$i; ($j<$i+16) && ($j<count($aheader)); $j++)  $d.=sprintf(" %02x",$aheader[$j+1]);
      $xml->addChild (sprintf('header%03x',$i),$d);
    }
    $rslt=$xml->asXML();
    header("Content-Type: text/xml");
    header("Content-Length: ".strlen($rslt)."\n");
    header("Pragma: no-cache\n");
    printf($rslt);
}

function showHistGamma ($key,$value) {
          $xml = new SimpleXMLElement("<?xml version='1.0'?><framepars/>");
          switch($key) {
            case "histogram_direct":
              $xml->addChild ('histogram_direct_r', elphel_histogram(0,$value));
              $xml->addChild ('histogram_direct_g', elphel_histogram(1,$value));
              $xml->addChild ('histogram_direct_gb',elphel_histogram(2,$value));
              $xml->addChild ('histogram_direct_b', elphel_histogram(3,$value));
              break;
            case "histogram_reverse":
              $xml->addChild ('histogram_reverse_r', elphel_reverse_histogram(0,$value));
              $xml->addChild ('histogram_reverse_g', elphel_reverse_histogram(1,$value));
              $xml->addChild ('histogram_reverse_gb',elphel_reverse_histogram(2,$value));
              $xml->addChild ('histogram_reverse_b', elphel_reverse_histogram(3,$value));
              break;
            case "gamma_direct":
              $xml->addChild ('gamma_direct_r', elphel_gamma(0,$value));
              $xml->addChild ('gamma_direct_g', elphel_gamma(1,$value));
              $xml->addChild ('gamma_direct_gb',elphel_gamma(2,$value));
              $xml->addChild ('gamma_direct_b', elphel_gamma(3,$value));
              break;
            case "gamma_reverse":
              $xml->addChild ('gamma_reverse_r', elphel_reverse_gamma(0,$value));
              $xml->addChild ('gamma_reverse_g', elphel_reverse_gamma(1,$value));
              $xml->addChild ('gamma_reverse_gb',elphel_reverse_gamma(2,$value));
              $xml->addChild ('gamma_reverse_b', elphel_reverse_gamma(3,$value));
              break;
          }
         $rslt=$xml->asXML();
         header("Content-Type: text/xml");
         header("Content-Length: ".strlen($rslt)."\n");
         header("Pragma: no-cache\n");
         printf($rslt);
}

function profileShow($num_entries) {
//echo "<pre>";
//http://192.168.0.9/parsedit.php?PROFILING_EN=1
          $prof_template=array(
                 "PROFILE00"=>0,"PROFILE01"=>0,
                 "PROFILE02"=>0,"PROFILE03"=>0,
                 "PROFILE04"=>0,"PROFILE05"=>0,
                 "PROFILE06"=>0,"PROFILE07"=>0,
                 "PROFILE08"=>0,"PROFILE09"=>0,
                 "PROFILE10"=>0,"PROFILE11"=>0,
                 "PROFILE12"=>0,"PROFILE13"=>0,
                 "PROFILE14"=>0,"PROFILE15"=>0
          );
          $now=elphel_get_frame()-2; /// data is available 2 frames behind
          $time_start=elphel_get_fpga_time();
          $prof_raw=array();
          for ($i=$now-$num_entries-1;$i<=$now;$i++) {
            $prof_raw[$i]=elphel_get_P_arr($prof_template,$i);
          }
          $time_end=elphel_get_fpga_time();
          $prof=array();
          for ($i=$now-$num_entries;$i<=$now;$i++) {
            $prof[$i]=array ( "dt0"=>($prof_raw[$i]["PROFILE00"]-$prof_raw[$i-1]["PROFILE00"])*1000000+($prof_raw[$i]["PROFILE01"]-$prof_raw[$i-1]["PROFILE01"]),
                              "dt1"=>($prof_raw[$i]["PROFILE02"]-$prof_raw[$i]["PROFILE00"])*1000000+($prof_raw[$i]["PROFILE03"]-$prof_raw[$i]["PROFILE01"]),
                              "dt2"=>($prof_raw[$i]["PROFILE04"]-$prof_raw[$i]["PROFILE00"])*1000000+($prof_raw[$i]["PROFILE05"]-$prof_raw[$i]["PROFILE01"]),
                              "dt3"=>($prof_raw[$i]["PROFILE06"]-$prof_raw[$i]["PROFILE00"])*1000000+($prof_raw[$i]["PROFILE07"]-$prof_raw[$i]["PROFILE01"]),
                              "dt4"=>($prof_raw[$i]["PROFILE08"]-$prof_raw[$i]["PROFILE00"])*1000000+($prof_raw[$i]["PROFILE09"]-$prof_raw[$i]["PROFILE01"]),
                              "dt5"=>($prof_raw[$i]["PROFILE10"]-$prof_raw[$i]["PROFILE00"])*1000000+($prof_raw[$i]["PROFILE11"]-$prof_raw[$i]["PROFILE01"]),
                              "dt6"=>($prof_raw[$i]["PROFILE12"]-$prof_raw[$i]["PROFILE00"])*1000000+($prof_raw[$i]["PROFILE13"]-$prof_raw[$i]["PROFILE01"]),
                              "dt7"=>($prof_raw[$i]["PROFILE14"]-$prof_raw[$i]["PROFILE00"])*1000000+($prof_raw[$i]["PROFILE15"]-$prof_raw[$i]["PROFILE01"]));
           foreach ($prof[$i] as $key=>$value) if ($prof[$i][$key]<0)$prof[$i][$key]="";
         }
 if (!elphel_get_P_value(ELPHEL_PROFILING_EN)) {
echo <<<PROFILE_ENABLE
   <p><i>Interrupt service/tasklet profiling is currently disabled. You may enable it by following this link:
   <a href="/parsedit.php?PROFILING_EN=1">/parsedit.php?PROFILING_EN=1</a>
   </i></p>
PROFILE_ENABLE;
 }
echo <<<CAPTION
<p>reading profile time start=$time_start </p>
<p>reading profile time end=$time_end </p>
<ol>Profiling interrupt/tasklet execution time in microseconds, starting from the start of the frame
  <li>after updating frame pointers, Exif, parameters structures (IRQ service)</li>
  <li>start of the tasklet</li>
  <li>after Y histogram (G1) load from the FPGA (if enabled)</li>
  <li>after processing parameters (actions triggered by the parameter changes), </li>
  <li>after C histograms (R,G2,B) load from the FPGA (if enabled)</li>
  <li>When parameters are started to be written by appliaction(s) - overwritten if several calls take place during the same frame</li>
  <li>When parameters are finished to be written by appliaction(s) (may be  overwritten)</li>
</oul>
<br/><br/>
CAPTION;

 printf ("<table border='1'><tr><td>Frame</td><td>(hex)</td><td>Period</td><td>1</td><td>2</td><td>3</td><td>4</td><td>5</td><td>6</td><td>7</td></tr>\n");
 for ($i=$now-$num_entries;$i<=$now;$i++) {
   printf("<tr style='align:right'><td>%d</td><td>%08x</td><td>%d</td>",$i,$i,$prof[$i]["dt0"]);
   for ($j=1;$j<8;$j++) {
     if ($prof[$i]["dt".$j]) printf("<td>%d</td>",$prof[$i]["dt".$j]);
     else                    printf("<td>&nbsp;</td>");
   }
   printf("</tr>\n");
 }
 printf("</table>");
//echo "<pre>";print_r($prof_raw);echo"</pre>\n";

}




function printGammaStructure() {
  $gammaStructure=getGammaStructure();
   printf("<table \"border=1\">\n");
   printf(       "<tr><td>oldest_non_scaled</td><td><b>%d</b></td></tr>\n"
                ."<tr><td>newest_non_scaled</td><td><b>%d</b></td></tr>\n"
                ."<tr><td>oldest_all</td><td><b>%d</b></td></tr>\n"
                ."<tr><td>newest_all</td><td><b>%d</b></td></tr>\n"
                ."<tr><td>non_scaled_length</td><td><b>%d</b></td></tr>\n"
                ."<tr><td>num_locked</td><td><b>%d</b></td></tr>\n"
                ."<tr><td>locked_col 0</td><td><b>%d</b></td>\n"
                ."<tr><td>locked_col 1</td><td><b>%d</b></td>\n"
                ."<tr><td>locked_col 2</td><td><b>%d</b></td>\n"
                ."<tr><td>locked_col 3</td><td><b>%d</b></td>\n"
                ."</table>\n",
                $gammaStructure["oldest_non_scaled"],
                $gammaStructure["newest_non_scaled"],
                $gammaStructure["oldest_all"],
                $gammaStructure["newest_all"],
                $gammaStructure["non_scaled_length"],
                $gammaStructure["num_locked"],
                $gammaStructure["locked_col"][0],
                $gammaStructure["locked_col"][1],
                $gammaStructure["locked_col"][2],
                $gammaStructure["locked_col"][3]
);
    printf("<br/><br/>\n");

    printf("<table \"border=1\">\n");
//    printf("<tr><td>index           </td>\n"); foreach ($gammaStructure["entries"] as $entry) printf ("<td><b>%d</b></td>",$entry["index"]);printf("</tr>\n");
    printf("<tr><td>index           </td>\n"); foreach ($gammaStructure["entries"] as $entry)
       printf ("<td><a href='?gamma_page=%d'><b>%d</b></a></td>",$entry["index"],$entry["index"]);printf("</tr>\n");

    printf("<tr><td>hash32           </td>\n"); foreach ($gammaStructure["entries"] as $entry) printf ("<td><b>%08x</b></td>",$entry["hash32"]);printf("</tr>\n");
    printf("<tr><td>scale           </td>\n"); foreach ($gammaStructure["entries"] as $entry) printf ("<td><b>%01.3f</b></td>",$entry["scale"]);printf("</tr>\n");
    printf("<tr><td>gamma           </td>\n"); foreach ($gammaStructure["entries"] as $entry) printf ("<td><b>%01.3f</b></td>",$entry["gamma"]);printf("</tr>\n");
    printf("<tr><td>black           </td>\n"); foreach ($gammaStructure["entries"] as $entry) printf ("<td><b>%d</b></td>",$entry["black"]);printf("</tr>\n");
    printf("<tr><td>valid           </td>\n"); foreach ($gammaStructure["entries"] as $entry) printf ("<td><b>0x%x</b></td>",$entry["valid"]);printf("</tr>\n");
    printf("<tr><td>locked          </td>\n"); foreach ($gammaStructure["entries"] as $entry) printf ("<td><b>0x%8x</b></td>",$entry["locked"]);printf("</tr>\n");
    printf("<tr><td>this_non_scaled </td>\n"); foreach ($gammaStructure["entries"] as $entry) printf ("<td><b>%d</b></td>",$entry["this_non_scaled"]);printf("</tr>\n");
    printf("<tr><td>newer_non_scaled</td>\n"); foreach ($gammaStructure["entries"] as $entry) printf ("<td><b>%d</b></td>",$entry["newer_non_scaled"]);printf("</tr>\n");
    printf("<tr><td>older_non_scaled</td>\n"); foreach ($gammaStructure["entries"] as $entry) printf ("<td><b>%d</b></td>",$entry["older_non_scaled"]);printf("</tr>\n");
    printf("<tr><td>newer_all       </td>\n"); foreach ($gammaStructure["entries"] as $entry) printf ("<td><b>%d</b></td>",$entry["newer_all"]);printf("</tr>\n");
    printf("<tr><td>older_all       </td>\n"); foreach ($gammaStructure["entries"] as $entry) printf ("<td><b>%d</b></td>",$entry["older_all"]);printf("</tr>\n");
    printf("<tr><td>oldest_scaled   </td>\n"); foreach ($gammaStructure["entries"] as $entry) printf ("<td><b>%d</b></td>",$entry["oldest_scaled"]);printf("</tr>\n");
    printf("<tr><td>newest_scaled   </td>\n"); foreach ($gammaStructure["entries"] as $entry) printf ("<td><b>%d</b></td>",$entry["newest_scaled"]);printf("</tr>\n");
    printf("<tr><td>newer_scaled    </td>\n"); foreach ($gammaStructure["entries"] as $entry) printf ("<td><b>%d</b></td>",$entry["newer_scaled"]);printf("</tr>\n");
    printf("<tr><td>older_scaled    </td>\n"); foreach ($gammaStructure["entries"] as $entry) printf ("<td><b>%d</b></td>",$entry["older_scaled"]);printf("</tr>\n");
    printf("</table>\n");
  }

function getGammaStructure() {
        $gammas_file=fopen("/dev/gamma_cache","r");
        fseek($gammas_file,0,SEEK_END);
        $numberOfEntries=ftell($gammas_file);
        fclose($gammas_file);
        $gammaStructure=array();
        $g_raw=elphel_gamma_get_raw(0);
        $g_raw_ul=unpack('V*',$g_raw);
        $gammaStructure["oldest_non_scaled"]=$g_raw_ul[5];
        $gammaStructure["newest_non_scaled"]=$g_raw_ul[6];
        $gammaStructure["oldest_all"]=       $g_raw_ul[7];
        $gammaStructure["newest_all"]=       $g_raw_ul[8];
        $gammaStructure["non_scaled_length"]=$g_raw_ul[9];
        $gammaStructure["num_locked"]=       $g_raw_ul[10];
        $gammaStructure["locked_col"]=  array ($g_raw_ul[11],$g_raw_ul[12],$g_raw_ul[13],$g_raw_ul[14]);
        $gammaStructure["entries"]=  array ();
        for ($i=1; $i<$numberOfEntries; $i++) {
          $g_raw=elphel_gamma_get_raw($i);
          $g_raw_ul=unpack('V*',$g_raw);
          if ($g_raw_ul[ 4]>=0) { /// >=0 if ever used. This field seems to do nothing in the code.
            $hash32= $g_raw_ul[1];
            $gammaStructure["entries"][$i]=  array (
              "index" =>                 $i,
              "hash32"=>                 $hash32,
              "scale" =>                 ($hash32 & 0xffff)/1024.0,
              "gamma" =>                 (($hash32 >> 16) & 0xff)/100.0,
              "black" =>                 (($hash32 >> 24) & 0xff),
              "valid" =>                 $g_raw_ul[ 2], /// 0 - table invalid, 1 - table valid +2 for table locked (until sent to FPGA)
              "locked" =>                $g_raw_ul[ 3], /// bit frame+ (color<<3) locked for color/frame
              "this_non_scaled" =>       $g_raw_ul[ 4], /// 0 for non-scaled, others - (for scaled) - pointer to the corresponding non-scaled 
/// This is non-scaled (gamma data is full 16-bit)
              "newer_non_scaled" =>      $g_raw_ul[ 5], /// table type (non-scaled prototype) used later than this one
              "older_non_scaled" =>      $g_raw_ul[ 6], /// table type (non-scaled prototype) used before this one

              "newer_all" =>             $g_raw_ul[ 7], /// newer in a single  chain of all scaled tables, regardless of the prototype
              "older_all" =>             $g_raw_ul[ 8], /// older in a single  chain of all scaled tables, regardless of the prototype
///Next two pairs are the same (union)
              "oldest_scaled" =>         $g_raw_ul[ 9], /// oldest derivative of this prototype (scaled)
              "newest_scaled" =>         $g_raw_ul[10], /// newest derivative of this prototype (scaled)
              "newer_scaled" =>          $g_raw_ul[ 9], /// table type (non-scaled prototype) used later than this one
              "older_scaled" =>          $g_raw_ul[10] /// table type (non-scaled prototype) used before this one
              );
            }
          }
          return $gammaStructure;
        }

function printRawGamma($page=0) {
        $g_raw=elphel_gamma_get_raw($page);
//var_dump()
        $g_raw_ul=unpack('V*',$g_raw);
        echo "<pre>\n";
        printf ("Gamma cache page %d, length=%d\n",$page, strlen($g_raw));
        $a=1; /// unpack started with index 1
        $hash32= $g_raw_ul[$a++];
        $scale= ($hash32 & 0xffff)/1024.0;
        $gamma= (($hash32 >> 16) & 0xff)/100.0;
        $black= (($hash32 >> 24) & 0xff);
        printf ("hash32= %08x (scale=%f gamma=%f black=%d)\n",$hash32,$scale,$gamma,$black);
        $valid= $g_raw_ul[$a++];
        printf ("valid=%d, locked=%d\n",$valid & 1, $valid & 2);

        $locked= $g_raw_ul[$a++];
        printf ("locked= 0x%x (for frame=%d/color=%d)\n",$locked, $locked & 7, ($locked>>3) & 3);

        $this_non_scaled=$g_raw_ul[$a++]; /// 0 for non-scaled
        printf ("this_non_scaled=%d\n",$this_non_scaled);
        if ($page==0) {
          printf ("oldest_non_scaled=%d\n",$g_raw_ul[$a++]);
          printf ("newest_non_scaled=%d\n",$g_raw_ul[$a++]);
        } else {
          printf ("newer_non_scaled=%d\n",$g_raw_ul[$a++]);
          printf ("older_non_scaled=%d\n",$g_raw_ul[$a++]);
        }

        if ($page==0) {
          printf ("oldest_all=%d\n",$g_raw_ul[$a++]);
          printf ("newest_all=%d\n",$g_raw_ul[$a++]);
        } else {
          printf ("newer_all=%d\n",$g_raw_ul[$a++]);
          printf ("older_all=%d\n",$g_raw_ul[$a++]);
        }

        if ($page==0) {
          printf ("non_scaled_length=%d\n",$g_raw_ul[$a++]); /// current number of different hash values
          printf ("num_locked=%d\n",$g_raw_ul[$a++]);        /// number of nodes locked (until table sent to FPGA)
        } else if ($this_non_scaled==0){
          printf ("oldest_scaled=%d\n",$g_raw_ul[$a++]);
          printf ("newest_scaled=%d\n",$g_raw_ul[$a++]);
        } else {
          printf ("newer_scaled=%d\n",$g_raw_ul[$a++]);
          printf ("older_scaled=%d\n",$g_raw_ul[$a++]);
        }
///data tables
        if ($page==0) {
          printf ("\nTable of locked indexes\n");
          for ($color=0;$color<4; $color++) {
//            for ($frame=0;$frame<8; $frame++) {
              printf (" %4d",$g_raw_ul[$a++]);
//            }
//            printf ("\n");
          }
          printf ("\n");
/// no need to dump the rest - it is unused in the page 0
          printf ("\n\nUnused area on page 0:");
//          for ($i=0; $i<417; $i++) {
          for ($i=0; $i<445; $i++) {
            if (($i & 0x0f)==0) printf ("\n0x%03x:",$i);
            $d=$g_raw_ul[$a++];
            printf (" %08x",$d);
          }

        } else {
          printf ("\nGamma table (direct):");
          for ($i=0; $i<129; $i++) {
            if (($i & 0x07)==0) printf ("\n0x%03x:",$i*2);
            $d=$g_raw_ul[$a++];
            printf (" %04x %04x",$d & 0xffff, ($d>>16) & 0xffff );
          }
          printf ("\n\nGamma table (reverse):");
          for ($i=0; $i<64; $i++) {
            if (($i & 0x03)==0) printf ("\n0x%03x:",$i*4);
            $d=$g_raw_ul[$a++];
            printf (" %02x %02x %02x %02x",$d & 0xff, ($d>>8) & 0xff, ($d>>16) & 0xff, ($d>>24) & 0xff);
          }

          printf ("\n\nFPGA gamma data:");
          for ($i=0; $i<256; $i++) {
            if (($i & 0x0f)==0) printf ("\n0x%03x:",$i);
            $d=$g_raw_ul[$a++];
            printf (" %05x",$d);
          }
        }
        echo "</pre>\n";
}

function printHistogram($frame) {
     if (!$frame) $frame=elphel_get_frame()-1;
     $colors=array(0=>"R",1=>"G",2=>"GB",3=>"B");
     $h_arr=elphel_histogram_get(0xfff,$frame);
     $a=0;
     $offset2sum=1024+255; /// last in cumulative histogram for the same color
     echo "<pre>\n";
     for ($color=0;$color<4;$color++) {
       printf("\nhistogram for color #%d %s, Total number of pixels=%d (0x%x):",$color,$colors[$color],$h_arr[$a+$offset2sum],$h_arr[$a+$offset2sum]);
       for ($i=0; $i<256; $i++) {
         if (($i & 0x0f)==0) printf ("\n0x%03x:",$i);
         printf (" %05x",$h_arr[$a++]);
       }
       printf ("\n");
     }
     for ($color=0;$color<4;$color++) {
       printf("\ncumulative histogram for color #%d %s:",$color,$colors[$color]);
       for ($i=0; $i<256; $i++) {
         if (($i & 0x0f)==0) printf ("\n0x%03x:",$i);
         printf (" %08x",$h_arr[$a++]);
       }
       printf ("\n");
     }
     for ($color=0;$color<4;$color++) {
       printf("\npercentile for color #%d %s:",$color,$colors[$color]);
       for ($i=0; $i<256; $i++) {
         if (($i & 0x01f)==0) printf ("\n0x%03x:",$i);
         printf (" %02x",$h_arr[$a++]);
       }
       printf ("\n");
     }
     echo "</pre>\n";
}

function printRawHistogram($needed,$frame) {
        if (!$frame) $frame=elphel_get_frame()-1;
///FIXME:
/// Use ELPHEL_CONST_HISTOGRAM_TABLE_OFFSET - byte offset of the first histogram table
//        $percentile_start=8232;
//        $percentile_start=8216;
        $percentile_start=8192+ ELPHEL_CONST_HISTOGRAM_TABLE_OFFSET;
        $colors=array(0=>"R",1=>"G",2=>"GB",3=>"B");
        $h_raw=elphel_histogram_get_raw($needed,$frame);
//var_dump()
        $h_raw_ul=unpack('V*',substr($h_raw,0,$percentile_start));
        echo "<pre>\n";
        $a=1; /// unpack started with index 1
        $hframe=   $h_raw_ul[$a++];
        $gainr=    $h_raw_ul[$a++];
        $gaing=    $h_raw_ul[$a++];
        $gaingb=   $h_raw_ul[$a++];
        $gainb=    $h_raw_ul[$a++];
        $expos=    $h_raw_ul[$a++];
        $vexpos=   $h_raw_ul[$a++];
        $focus=    $h_raw_ul[$a++];
        $valid=    $h_raw_ul[$a++];
        $hash32_r= $h_raw_ul[$a++];
        $hash32_g= $h_raw_ul[$a++];
        $hash32_gb=$h_raw_ul[$a++];
        $hash32_b= $h_raw_ul[$a++];


/// When not parsing all the data above - just skip ELPHEL_CONST_HISTOGRAM_TABLE_OFFSET bytes
        printf ("Histogram for frame= %d (0x%x), valid mask=0x%x, requested=0x%x, data length=%d (0x%x)\n",$hframe,$hframe, $valid,$needed,strlen($h_raw),strlen($h_raw));
        printf ("Exposure = %d (0x%x)usec, in scan lines (vexpos) =%d (0x%x)\n",$expos,$expos,$vexpos,$vexpos);
        printf ("Gains: R:0x%x G:0x%x GB:0x%x B:0x%x)\n",$gainr,$gaing,$gaingb,$gainb);
        printf ("Focus quality=%d (0x%x)\n",$focus,$focus);
        printf ("hash32: R:0x%x G:0x%x GB:0x%x B:0x%x)\n",$hash32_r,$hash32_g,$hash32_gb,$hash32_b);
        for ($color=0;$color<4;$color++) {
          $sum=0;
          for ($i=0; $i<256; $i++)   $sum+=$h_raw_ul[$a+$i];
          printf("\nhistogram for color #%d %s sum=%d (0x%x):",$color,$colors[$color],$sum,$sum);
          for ($i=0; $i<256; $i++) {
            if (($i & 0x0f)==0) printf ("\n0x%03x:",$i);
            $d=$h_raw_ul[$a++];
            printf (" %05x",$d);
          }
          printf ("\n");
        }
        for ($color=0;$color<4;$color++) {
          printf("\ncumulative histogram for color #%d %s:",$color,$colors[$color]);
          for ($i=0; $i<256; $i++) {
            if (($i & 0x0f)==0) printf ("\n0x%03x:",$i);
            $d=$h_raw_ul[$a++];
            printf (" %08x",$d);
          }
          printf ("\n");
        }
        for ($color=0;$color<4;$color++) {
          printf("\npercentile for color #%d %s:",$color,$colors[$color]);
          for ($i=0; $i<256; $i++) {
            if (($i & 0x01f)==0) printf ("\n0x%03x:",$i);
            printf (" %02x",ord($h_raw[$percentile_start+(256*$color)+$i]));
          }
          printf ("\n");
        }
        echo "</pre>\n";
}


function printRawFrame($frame) {
        $fp_raw=elphel_framepars_get_raw($frame);
        $fp_raw_ul=unpack('V*',$fp_raw);
        echo "<pre>\n";
        printf ("\nFrame= %d(%08x)\n",$frame,$frame);
        $a=1; /// unpack started with index 1
        echo ".pars:";
        for ($i=0; $i<927; $i++) {
          if (($i & 0x0f)==0) printf ("\n0x%03x:",$i);
          printf (" %08x:",$fp_raw_ul[$a++]);
        }
        printf ("\n.functions= %08x:",$fp_raw_ul[$a++]);
        echo "\n.modsince:";
        for ($i=0; $i<31; $i++) {
          if (($i & 0x0f)==0) printf ("\n0x%03x:",$i);
          printf (" %08x:",$fp_raw_ul[$a++]);
        }
        printf ("\n.modsince32= %08x:",$fp_raw_ul[$a++]);
        echo "\n.mod:";
        for ($i=0; $i<31; $i++) {
          if (($i & 0x0f)==0) printf ("\n0x%03x:",$i);
          printf (" %08x:",$fp_raw_ul[$a++]);
        }
        printf ("\n.mod32= %08x:",$fp_raw_ul[$a++]);
        echo "\n.needproc:";
        for ($i=0; $i<31; $i++) {
          if (($i & 0x0f)==0) printf ("\n0x%03x:",$i);
          printf (" %08x:",$fp_raw_ul[$a++]);
        }
        printf ("\n.needproc32= %08x:",$fp_raw_ul[$a++]);
//        var_dump($fp_raw_ul);
        echo "</pre>\n";
}


function myval ($s) {
  $current_frame=elphel_get_frame();
  $s=trim($s,"\" ");
  if (strtoupper(substr($s,0,2))=="0X")   return intval(hexdec($s));
  else  switch ($s) {
           case "this":  return elphel_get_frame();
              break;
           case "next":
           case "next1":  return elphel_get_frame()+1;
              break;
           case "next2": return elphel_get_frame()+2;
              break;
           case "next3": return elphel_get_frame()+3;
              break;
           case "next4": return elphel_get_frame()+4;
              break;
           case "next5": return elphel_get_frame()+5;
              break;
           case "next6": return elphel_get_frame()+6;
              break;
              break;
           case "prev":
           case "prev1":  return elphel_get_frame()-1;
              break;
           case "prev2": return elphel_get_frame()-2;
              break;
           case "prev3": return elphel_get_frame()-3;
              break;
           case "prev4": return elphel_get_frame()-4;
              break;
           case "prev5": return elphel_get_frame()-5;
              break;
           case "prev6": return elphel_get_frame()-6;
              break;
           default:
             return intval($s);
  }
}
?>
