<?php

/**
 * @copyright Copyright (C) 2020 Elphel, Inc.
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * @author Oleg Dzhimiev <oleg@elphel.com>
 * @brief Test sensors at all 10393 ports
 */

include "include/elphel_functions_include.php";

$PORT_BASE = 2323;

$SENSOR_NONE    = 'none';
$SENSOR_MT9P006 = "mt9p006";

function apply_def_settings($port,$pars){

    global $PORT_BASE;

    $parsForMD5=array(
        'QUALITY'        => 96,
        'WOI_WIDTH'      => 2592,
        'WOI_HEIGHT'     => 1936,
        'SENSOR_RUN'     => 2,
        'COMPRESSOR_RUN' => 2,
        'GAINR'          => 0x20000,
        'GAING'          => 0x20000,
        'GAINB'          => 0x20000,
        'GAINGB'         => 0x20000,
        'COLOR'          => 0,
        'AUTOEXP_ON'     => 0,
        'WB_EN'          => 0,
        'GAIN_MIN'       => 0x10000,
        'GAIN_MAX'       => 0x10000,
        'TESTSENSOR'     => 0x10008,
        'EXPOS'          => 10000
    );

    for($i=0;$i<2;$i++){
        elphel_set_P_arr($port,$parsForMD5);
        $thisFrame = elphel_get_frame($port);
        elphel_wait_frame_abs($port,$thisFrame+3);
    }
}

function run_test_mt9p006($port){
    global $PORT_BASE;

    $ref_md5sum = "e1f8f6c37d1d7ddd233a821338f812e5";
    $p = $PORT_BASE + $port;
    $test_status = "ok";
    $ahead = 3;

    $parsForMD5=array(
        'QUALITY'        => 96,
        'WOI_WIDTH'      => 2592,
        'WOI_HEIGHT'     => 1936,
        'SENSOR_RUN'     => 2,
        'COMPRESSOR_RUN' => 2,
        'GAINR'          => 0x20000,
        'GAING'          => 0x20000,
        'GAINB'          => 0x20000,
        'GAINGB'         => 0x20000,
        'COLOR'          => 0,
        'AUTOEXP_ON'     => 0,
        'WB_EN'          => 0,
        'GAIN_MIN'       => 0x10000,
        'GAIN_MAX'       => 0x10000,
        'TESTSENSOR'     => 0x10008,
        'EXPOS'          => 10000
    );

    // save pars to restore later
    $parsSaved = elphel_get_P_arr($port,$parsForMD5);

    // Spectr's cheating - double initialization
    for($i=0;$i<2;$i++){
        elphel_set_P_arr($port,$parsForMD5);
        $thisFrame = elphel_get_frame($port);
        elphel_wait_frame_abs($port,$thisFrame+$ahead);
    }

    for($i=0;$i<3;$i++){
        $md5sum = md5(file_get_contents("http://127.0.0.1:$p/noexif/next/wait/img"));
        if ($md5sum!=$ref_md5sum){
            print("md5sum($i) does not match reference ($ref_md5sum): $md5sum\n");
            $test_status = "fail";
        }else{
            print("md5sum($i) $md5sum ok\n");
        }
    }

    // restore saved pars
    elphel_set_P_arr($port,$parsSaved);

    print("RESULT: $test_status\n");

}

if (isset($_SERVER['SERVER_ADDR'])){
    print("<pre>");
}

$sensors = get_sensors();

foreach($sensors as $i => $sensor){
    print("Testing port $i:\n");
    switch($sensor){
        case $SENSOR_MT9P006:
            run_test_mt9p006($i);
            break;
        case $SENSOR_NONE:
            print("WARNING: No sensor attached to port $i. Test skipped.\n");
            break;
        default:
            print("WARNING: Unsupported sensor '$sensor'. Test skipped.\n");
    }
}

print("Done\n");

?>
