#!/usr/bin/php
<?php
    set_include_path ( get_include_path () . PATH_SEPARATOR . '/www/pages/include' );
    include 'show_source_include.php';
    include "elphel_functions_include.php"; // includes curl functions

    $pipe_cmd="/tmp/pipe_cmd";
    $pipe_response="/tmp/pipe_response";
    $mode=0600;
    declare(ticks = 1);

    pcntl_signal(SIGTERM, "signal_handler");
    pcntl_signal(SIGINT, "signal_handler");

    function signal_handler($signal) {
        switch($signal) {
            case SIGTERM:
                print "Caught SIGTERM\n";
                exit;
            case SIGKILL:
                print "Caught SIGKILL\n";
                exit;
            case SIGINT:
                print "Caught SIGINT\n";
                exit;
        }
    }
    if(file_exists($pipe_cmd)){
        unlink($pipe_cmd); //delete pipe if it was already there - waiting prevents signal handling!
    }
    while(1) {
        if(file_exists($pipe_cmd)) {
            //block and read from the pipe
            $f = fopen($pipe_cmd,"r");
            echo "(r) opened cmd\n";
            $l = fgets($f);
            fclose ($f);
            echo "(r) closed cmd\n";
            unlink($pipe_cmd); //delete pipe
            echo "(r) deleted cmd\n";
            if(!file_exists($pipe_response)) {
                // create the pipe
                umask(0);
                posix_mkfifo($pipe_response,$mode);
            }
            $fr = fopen($pipe_response,"w");
            fwrite($fr,"response1: ".$l);
            fwrite($fr,"response2: ".$l);
            fwrite($fr,"response3: ".$l);
            echo "(r) sent responses\n";
            sleep(1);
            fclose ($fr);
            echo "(r) closed response pipe\n";
        }
     //
   }
?>
