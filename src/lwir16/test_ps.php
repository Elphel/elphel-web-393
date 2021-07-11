#!/usr/bin/php
<?php
    print_r($argv);
    set_include_path ( get_include_path () . PATH_SEPARATOR . '/www/pages/include' );
    include 'show_source_include.php';
    include "elphel_functions_include.php"; // includes curl functions
    $name = 'test_ps.php'; // int.php';
    $interpreter='php';
    echo "\n--------- interpreter=$interpreter, name = $name ----------\n";
    $arr = getPIDByName($name, $interpreter,  $active_only=false);
//    var_dump($arr);
    print_r($arr);
    $name = 'test_ps.php'; // 'php-cgi';
    $interpreter='';
    echo "\n--------- interpreter=$interpreter, name = $name ----------\n";
    $arr = getPIDByName($name, $interpreter, $active_only=false);
    //    var_dump($arr);
    print_r($arr);
    
    $interpreter='python';
    $name = 'tempmon.py';
    echo "\n--------- interpreter=$interpreter, name = $name ----------\n";
    $arr = getPIDByName($name, $interpreter,  $active_only=false);
    //    var_dump($arr);
    print_r($arr);
    
?>
