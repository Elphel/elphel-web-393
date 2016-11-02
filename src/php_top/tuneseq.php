<?php
/*!*******************************************************************************
*! FILE NAME  : tuneseq.php
*! DESCRIPTION: Run-time adjustmet of the camera core sequencer
*! Copyright (C) 2008-2016 Elphel, Inc
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
*!  $Log: tuneseq.php,v $
*!  Revision 1.2  2010/05/13 03:35:28  elphel
*!  updates to reflect current drivers
*!
*!  Revision 1.1.1.1  2008/11/27 20:04:03  elphel
*!
*!
*!  Revision 1.7  2008/11/17 23:42:04  elphel
*!  changed myval() to accept numbers in ""
*!
*!  Revision 1.6  2008/11/02 00:33:45  elphel
*!  fixed bug introduced while renaming constants
*!
*!  Revision 1.5  2008/10/31 18:26:32  elphel
*!  Adding support for constants like SENSOR_REGS32 (defined constant plus 32 to simplify referencing sensor registers from PHP
*!
*!  Revision 1.4  2008/10/15 22:28:56  elphel
*!  snapshot 8.0.alpha2
*!
*!  Revision 1.3  2008/10/13 04:48:48  elphel
*!  added some help to the page
*!
*!  Revision 1.2  2008/10/12 22:17:56  elphel
*!  snapshot
*!
*/

function myval ($s) {
  $s=trim($s,"\" ");
  if (strtoupper(substr($s,0,2))=="0X")   return intval(hexdec($s));
  else return intval($s);
}

function read_latencies() {
   global $elp_const;
   $elphel_onchange=           'ELPHEL_ONCHANGE_';
   $elphel_onchange_len=strlen('ELPHEL_ONCHANGE_');
   $l_iarr=array(
          'CALLNASAP'=>elphel_get_P_value($GLOBALS['sensor_port'],ELPHEL_CALLNASAP),
          'CALLNEXT1'=>elphel_get_P_value($GLOBALS['sensor_port'],ELPHEL_CALLNEXT+1),
          'CALLNEXT2'=>elphel_get_P_value($GLOBALS['sensor_port'],ELPHEL_CALLNEXT+2),
          'CALLNEXT3'=>elphel_get_P_value($GLOBALS['sensor_port'],ELPHEL_CALLNEXT+3),
          'CALLNEXT4'=>elphel_get_P_value($GLOBALS['sensor_port'],ELPHEL_CALLNEXT+4));
/*
   echo "<pre>\n";
   echo "As GETed:\n";
   var_dump($l_iarr);
   echo "</pre>\n";
*/

   $larr=array();
   for ($i=0;$i<32;$i++) {
     $lat=0;
     if ($l_iarr['CALLNEXT1'] & (1<<$i)) $lat++;
     if ($l_iarr['CALLNEXT2'] & (1<<$i)) $lat++;
     if ($l_iarr['CALLNEXT3'] & (1<<$i)) $lat++;
     if ($l_iarr['CALLNEXT4'] & (1<<$i)) $lat++;
     $larr[$i]=array('name'=>'','seqen'=>($l_iarr['CALLNASAP'] & (1<<$i))?true:false,'latency'=>$lat);
   }
   foreach ($elp_const as $key=>$value)  if (substr($key,0,$elphel_onchange_len)==$elphel_onchange) {
       $larr[$value]['name']=substr($key,$elphel_onchange_len);
   }
   return $larr;
}

function apply_latencies() {
   $l_iarr=array(
          'CALLNASAP'=>0,
          'CALLNEXT1'=>0,
          'CALLNEXT2'=>0,
          'CALLNEXT3'=>0,
          'CALLNEXT4'=>0);
   for ($i=0;$i<32;$i++) {
     if ($_POST["seqen_".$i]) $l_iarr['CALLNASAP'] |= (1 << $i);
     switch ($_POST["latency_".$i]) { /// no "break" between cases here!
       case "4":
        $l_iarr['CALLNEXT4'] |= (1 << $i);
       case "3":
        $l_iarr['CALLNEXT3'] |= (1 << $i);
       case "2":
        $l_iarr['CALLNEXT2'] |= (1 << $i);
       case "1":
        $l_iarr['CALLNEXT1'] |= (1 << $i);
     }
   }
/*   elphel_set_P_value(ELPHEL_CALLNASAP,   $l_iarr['CALLNASAP'], 0, ELPHEL_CONST_FRAMEPAIR_FRAME_ZERO); ///FRAMEPAIR_FRAME_ZERO
   elphel_set_P_value(ELPHEL_CALLNEXT+1,  $l_iarr['CALLNEXT1'], 0, ELPHEL_CONST_FRAMEPAIR_FRAME_ZERO); ///FRAMEPAIR_FRAME_ZERO
   elphel_set_P_value(ELPHEL_CALLNEXT+2,  $l_iarr['CALLNEXT2'], 0, ELPHEL_CONST_FRAMEPAIR_FRAME_ZERO); ///FRAMEPAIR_FRAME_ZERO
   elphel_set_P_value(ELPHEL_CALLNEXT+3,  $l_iarr['CALLNEXT3'], 0, ELPHEL_CONST_FRAMEPAIR_FRAME_ZERO); ///FRAMEPAIR_FRAME_ZERO
   elphel_set_P_value(ELPHEL_CALLNEXT+4,  $l_iarr['CALLNEXT4'], 0, ELPHEL_CONST_FRAMEPAIR_FRAME_ZERO); ///FRAMEPAIR_FRAME_ZERO
*/
   elphel_set_P_value($GLOBALS['sensor_port'], ELPHEL_CALLNASAP,   $l_iarr['CALLNASAP']);
   elphel_set_P_value($GLOBALS['sensor_port'], ELPHEL_CALLNEXT+1,  $l_iarr['CALLNEXT1']);
   elphel_set_P_value($GLOBALS['sensor_port'], ELPHEL_CALLNEXT+2,  $l_iarr['CALLNEXT2']);
   elphel_set_P_value($GLOBALS['sensor_port'], ELPHEL_CALLNEXT+3,  $l_iarr['CALLNEXT3']);
   elphel_set_P_value($GLOBALS['sensor_port'], ELPHEL_CALLNEXT+4,  $l_iarr['CALLNEXT4']);
/*
   echo "<pre>\n";
   echo "As POSTed:\n";
   var_dump($l_iarr);
   echo "</pre>\n";
*/
}

function print_latencies() {
   $larr=read_latencies();
   printf ("<form action=\"$self\" method=\"post\">");
   printf ("<table border='1'><thead><td>bit#</td><td>Name</td><td>Enable<br/>sequencer</td><td>Latency</td></thead><tbody>\n");
   for ($i=0;$i<32;$i++) {
     printf ("<tr><td>%d</td><td>%s</td><td> <input type=\"checkbox\" name=\"seqen_%d\" value=\"1\" %s />  </td><td><input name=\"latency_%d\" type=\"text\" size=\"1\" value=\"%s\"/></td></tr>\n",
                                               $i,$larr[$i]['name']?$larr[$i]['name']:'&nbsp;',
                                               $i, $larr[$i]['seqen']?'checked':'',$i,$larr[$i]['latency']);
   }
   printf ("</tbody></table>\n");
   printf ("<input type=\"submit\" value=\"Apply\"/></form>\n");
}


function read_triggers() {
   global $elp_const;
   $elphel_onchange=           'ELPHEL_ONCHANGE_';
   $elphel_onchange_len=strlen('ELPHEL_ONCHANGE_');
   $elphel_lseek=              'ELPHEL_LSEEK_';
   $elphel_lseek_len=   strlen('ELPHEL_LSEEK_');
   $elphel_const=              'ELPHEL_CONST_'; ///Other constants
   $elphel_const_len=   strlen('ELPHEL_CONST_');
//   $elphel_=                   'ELPHEL_';
   $elphel_len=         strlen('ELPHEL_');

   $fp_raw=elphel_framepars_get_raw($GLOBALS['sensor_port'], -1);
   $fp_raw_ul=unpack('V*',$fp_raw); /// first 927
   $p_params=array_fill  ( 0, 927, array('name'=>"",'value'=>0));
   for ($i=0;$i<927;$i++) $p_params[$i]['value']=$fp_raw_ul[$i+1];
/// find names 
   foreach ($elp_const as $key=>$value) {
     if ((substr($key,0,$elphel_onchange_len)!=$elphel_onchange) &&
          (substr($key,0,$elphel_lseek_len)!=$elphel_lseek) &&
          (substr($key,0,$elphel_const_len)!=$elphel_const)) {
       if (($value>=0) && ($value<count($p_params))) {
         if (!$p_params[$value]['name'] ) $p_params[$value]['name']=substr($key,$elphel_len);
       }
     }
   }
   for ($i=0; $i <ELPHEL_SENSOR_NUMREGS; $i++ ) !$p_params[ELPHEL_SENSOR_REGS+$i]['name']="SENSOR_REGS".$i;
   return $p_params;
}


function print_triggers_head($names) { /// print column names - repeat in long table
   printf ("<td>Num</td><td>Hex</td><td>Name</td><td>Value</td>\n");
   for ($i=0;$i<32;$i++) {
     printf("<td>");
     if ( count($names[$i])) foreach ($names[$i] as $char) printf("%s<br/>",$char);
     else                                                      printf("&nbsp;");
     printf("</td>");
   }

}


function print_triggers($filter=0,$minnum=0, $maxnum=926, $rephead=50) { /// filter +1 - skip noname, +2 - skip no value
   global $elp_const;
   $elphel_onchange=           'ELPHEL_ONCHANGE_';
   $elphel_onchange_len=strlen('ELPHEL_ONCHANGE_');
//   $bit_names=array(0);
//   for ($i=0;$i<32;$i++) $bit_names[$i]="";
   $bit_names=array_fill  ( 0, 32, "");
   foreach ($elp_const as $key=>$value)  if (substr($key,0,$elphel_onchange_len)==$elphel_onchange) {
       $bit_names[$value]=substr($key,$elphel_onchange_len);
   }
   for ($i=0;$i<32;$i++) $bit_names[$i]=str_split($bit_names[$i]);
//   $larr=read_triggers();

   $pars=read_triggers();
/*
   echo "<pre>\n";
   var_dump($pars[0]);
   var_dump($pars[1]);
   var_dump($pars[2]);
   var_dump($pars[3]);
   echo "</pre>\n";
*/

   printf ("<form action=\"$self\" method=\"post\">");
   printf ("<table border='1'><thead>\n");
   print_triggers_head($bit_names);
   printf ("<tbody>\n");
   $tohead=$rephead;
   if ($maxnum>926) $maxnum=926;
   for ($parnum=$minnum;$parnum<=$maxnum;$parnum++) {
     if ((!($filter & 1) || ($pars[$parnum]['name'])) && (!($filter & 2) || ($pars[$parnum]['value']))) {
       printf ("<tr>\n");
       printf ("<td><input type=\"hidden\" name=\"par_%d\" value=\"%d\"/>%d</td><td>0x%x</td><td>%s</td><td>%08x</td>",$parnum,$parnum,$parnum,$parnum,($pars[$parnum]['name'])?($pars[$parnum]['name']):"&nbsp;",$pars[$parnum]['value']);
       for ($b=0;$b<32;$b++) {
         printf ("<td><input type=\"checkbox\" name=\"trig_%d_%d\" value=\"1\" %s /></td>",$parnum,$b,($pars[$parnum]['value'] & (1 << $b))?'checked':'');
       }
       printf ("</tr>\n");
       if (($tohead--)<=0) { /// repeat table header
         printf ("<tr>\n");
         print_triggers_head($bit_names);
         printf ("</tr>\n");
         $tohead=$rephead;
       }
     }
   }
   printf ("</tbody></table>\n");

   printf ("<input type=\"submit\" value=\"Apply\"/></form>\n");
}
//   <input type="hidden" name="is_post" value="it_is">

function apply_triggers() {
  $pars=array();
  foreach ($_POST as $key=>$value) if (substr($key,0,4)=="par_") $pars[$value]=0;

  foreach ($_POST as $key=>$value) if (substr($key,0,5)=="trig_") {
       list ($s,$i,$j)= split('_',$key);
       $pars[$i] |= (1<<$j);
  }
  foreach ($pars as $key=>$value) {
    elphel_set_P_value($GLOBALS['sensor_port'],
    		intval($key),
    		intval($value),
    		0,
    		ELPHEL_CONST_FRAMEPAIR_FRAME_FUNC); ///#define FRAMEPAIR_FRAME_FUNC   (FRAMEPAIR_FRAME_ZERO | FRAMEPAIR_FORCE_NEW) // write to func2call instead of the frame parameters
  }
/*
   echo "<pre>\n";
   var_dump($pars);
   echo "</pre>\n";
*/
}

///TODO: colorize columns, checkboxes
//main
   $elp_const=get_defined_constants(true);
   $elp_const=$elp_const["elphel"];
   $sensor_port = 0;
   if (array_key_exists ( 'sensor_port', $_GET )) {
   	$sensor_port = (myval($_GET ['sensor_port']));
   }
   switch ($_GET["mode"]) {
     case "triggers":
     case "functions":
     $filter=0;
     $minnum=0; //927
     $maxnum=512; //927
     $rephead=50;
     if ($_GET["filter"]) {
        $filter=$_GET["filter"];
     }
     if ($_GET["min"]) {
        $minnum=$_GET["min"];
     }
     if ($_GET["max"]) {
        $maxnum=$_GET["max"];
     }
     if ($_GET["rephead"]) {
        $rephead=$_GET["rephead"];
     }
    

       if ($_SERVER["REQUEST_METHOD"]=="POST") {
         apply_triggers();
       }
       print_triggers($filter, $minnum, $maxnum,$rephead);
     exit (0);
     case "latency":
     case "latencies":
       if ($_SERVER["REQUEST_METHOD"]=="POST") {
         apply_latencies();
       }
       print_latencies();
     exit (0);
     default:

      echo <<<USAGE
   <p>This page allows to tune camera sequencer at run time, those adjustments to be applied to C headers of the drivers:
   <a href="https://github.com/Elphel/linux-elphel/blob/master/src/drivers/elphel/latency.h">latency.h</a> and
   <a href="https://github.com/Elphel/linux-elphel/blob/master/src/drivers/elphel/param_depend.h">param_depend.h</a></p>
   <p>First mode is used to specify latency (in frames) for different actions, additionally it specifies which actions can use hardware sequencer (some, like writing gamma tables to FPGA
      can only be performed in "ASAP" mode, bypassing the sequencer)</p>
   <h4> <a href="$self?sensor_port=$sensor_port&mode=latency">mode=latency</a></h4>
   <p>The second one can be used to specify which functions are triggered by chnage in which parameters (either from user program or sometimes by actions of other functions).</p>
   <h4> <a href="$self?sensor_port=$sensor_port&mode=triggers">mode=triggers</a></h4>
   <p>This mode can generate large tables, so it is possible to limit number of parameters shown</p>
   <ul>
    <li><b>filter=N</b> (N=0..3), where 1 means "ignore unnamed parameters", 2 - "ignore parameters with a value 0", 3 - ignore both and 0 - show all<b></li>
    <li><b>min=N</b> - start with parameter number <b>N</b></li>
    <li><b>max=N</b> - end with parameter number <b>N</b></li>
    <li><b>rephead=N</b> - repeat table header each <b>N</b> lines.</li>
  </ul>
  <p>Example: <a href="$self?sensor_port=$sensor_port&mode=triggers&filter=1&min=40&max=60">?mode=triggers&filter=1&min=40&max=60</a></p>
USAGE;
       exit (0);
   } 

?>
