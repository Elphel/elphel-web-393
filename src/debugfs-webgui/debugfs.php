<?php

if (isset($_GET['cmd']))
    $cmd = $_GET['cmd'];
else
    $cmd = "do_nothing";

function control_records_sort($a,$b){

    $ad1 = strpos($a,":");
    $ad2 = strpos($a,"[");
    
    $afile = substr($a,0,$ad1);
    $aline = (int)substr($a,$ad1+1,($ad2-1)-$ad1-1);
    
    $bd1 = strpos($b,":");
    $bd2 = strpos($b,"[");
    
    $bfile = substr($b,0,$bd1);
    $bline = (int)substr($b,$bd1+1,($bd2-1)-$bd1-1);
    
    if ($afile==$bfile){
        if ($aline==$bline){
            return 0;
        }
        return($aline<$bline)?-1:1;
    }
    
    return ($afile<$bfile)?-1:1;
    
}
    
function get_control($f){
    $res = Array();
    $results = trim(file_get_contents($f));
    //print("<pre>");
    $ress = explode("\n",$results);
    //filename - find first ":"
    //lineno - between ":" and " "
    //then [module] inside brackets
    //function from "]" to " "

    usort($ress,"control_records_sort");
    
    $oldfile = "";
    
    foreach($ress as $line){
    
        if ($line[0]=="#") continue;
    
        $d0 = 0;
        $d1 = strpos($line,":");
        $d2 = strpos($line,"[");
        $d3 = strpos($line,"]");
        preg_match("/=[flmpt_]+/",$line,$matches,PREG_OFFSET_CAPTURE);
        $d4 = $matches[0][1];
        $d5 = strpos($line,"\"");
    
        $subarr = Array();
        $subarr['file']     = substr($line,0,$d1);
        $subarr['lineno']   = substr($line,$d1+1,($d2-1)-$d1-1);
        $subarr['module']   = substr($line,$d2+1,($d3-1)-$d2);
        $subarr['function'] = substr($line,$d3+1,($d4-1)-$d3-1);
        $subarr['flags']    = substr($line,$d4+1,1);
        $subarr['format']   = substr($line,$d5+1,-1);
    
        if ($subarr['file']!=$oldfile){
            //echo "processing ".$subarr['file']."\n";
            if ($oldfile!="") array_push($res,$sub);
            $oldfile = $subarr['file'];
            $sub = Array(
                "file" => $subarr['file'],
                "state" => 0,
                "configs" => Array(
                    Array(
                        "name" => "default",
                        "state" => 1,
                        "lines" => Array()
                    )
                )
            );
        }
        array_push($sub['configs'][0]['lines'],$subarr);
    }
    //last
    array_push($res,$sub);
        
    return $res;
}

function update_config($data){
    // debugfs.json
    file_put_contents("debugfs.json",$data);
}

function sync_to_config($file,$line,$flag){
    //$arr_debugfs = get_control("/sys/kernel/debug/dynamic_debug/control");
    $arr_config = json_decode(file_get_contents("debugfs.json"),true);
        
    foreach($arr_config as $k => $v){
        if ($v['file']==$file) $dc = $k;
    }
    
    echo "DC=$dc\n";
    
    $tmp_arr1 = $arr_config[$dc]['configs'];
    
    foreach($tmp_arr1 as $k => $v){
        if ($v['state']==1) $dcc = $k;
    }
    
    $tmp_arr2 = $arr_config[$dc]['configs'][$dcc]['lines'];
    
    foreach($tmp_arr2 as $k => $v){
        if ($v['lineno']==$line) $dccc = $k;
    }
    
    if ($flag=="+") $flag = "p";
    else            $flag = "_";
    
    $arr_config[$dc]['configs'][$dcc]['lines'][$dccc]['flags'] = $flag;
    
    print_r($arr_config);
    
    update_config(json_encode($arr_config));
}

if (($cmd=="do_nothing")||($cmd=="restore")){
    if (isset($_GET['file'])) $file = $_GET['file'];
    else                      $file = "/sys/kernel/debug/dynamic_debug/control";
    
    //echo json_encode(get_control($file));
    //echo "<pre>";
    
    if (!is_file("debugfs.json")) {
        $arr = get_control($file);
        //print_r($arr);
        update_config(json_encode($arr));
        echo json_encode($arr);
        //echo "debugfs.json was missing, refresh page\n";
    }else{
        $json_data = file_get_contents("debugfs.json");
        //print_r(json_decode($json_data));
        echo $json_data;
    }
}

if ($cmd=="echo") {
    $file = $_GET['file'];
    $line = $_GET['line'];
    $flag = $_GET['pflag'];
    //$config name
    
    if ($flag=="true"){
        $flag="+";
    }else{
        $flag="-";
    }
    
    exec("echo -n 'file $file line $line ${flag}p' > /sys/kernel/debug/dynamic_debug/control");
    
    sync_to_config($file,$line,$flag);
}

$debugfs_configs = "debugfs_configs";

if ($cmd=="save"){
    $file = $_GET['file'];
    if (!is_dir($debugfs_configs)) mkdir($debugfs_configs);
    file_put_contents("$debugfs_configs/$file", file_get_contents("/sys/kernel/debug/dynamic_debug/control"));
}

if ($cmd=="sync"){
    //list saved configs here
    $data = file_get_contents("php://input");
    update_config($data);
}


//single line: echo -n 'file gamma_tables.c +p' > /sys/kernel/debug/dynamic_debug/control

?>
