#!/usr/bin/php
<?php
    define('PIPE_CMD',           '/tmp/pipe_cmd');
    define('PIPE_RESPONSE',      '/tmp/pipe_response');
    define('PIPE_MODE',          0600);

    set_include_path ( get_include_path () . PATH_SEPARATOR . '/www/pages/include' );
    include 'show_source_include.php';
    include "elphel_functions_include.php"; // includes curl functions
    $cmd = 'STATUS';
    $kw = 'CMD';
    if (count($argv) > 2){
        $kw = $argv[1];
        $cmd = $argv[2];
    } else if (count($argv) > 1){
        $cmd = $argv[1];
    }
    
    $mode=0600;
    if(!file_exists(PIPE_CMD)) {
      // create the pipe
        umask(0);
        posix_mkfifo(PIPE_CMD,$mode);
    }
    if (file_exists(PIPE_RESPONSE)){
        unlink(PIPE_RESPONSE); //delete old pipe
    }
    $f = fopen(PIPE_CMD,"w");
//    sleep(1);
    $cmds = $kw.' = '.$cmd."\n";
    
    fwrite($f,$cmds);
//    sleep (1);
    echo "(w) sent commands:\n".$cmds."\n";
    fclose ($f);
//    sleep(1);
    echo "(w) closed\n";
    if ($cmd == 'STATUS') {
        while (!file_exists(PIPE_RESPONSE)); // just wait
        echo "(w) got PIPE_RESPONSE\n";
        $fl = file(PIPE_RESPONSE);
        var_dump($fl);
        unlink(PIPE_RESPONSE); //delete pipe
    }
?>
