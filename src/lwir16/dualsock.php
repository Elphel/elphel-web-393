#!/usr/bin/php
<?php
/*!*******************************************************************************
 *! FILE NAME  : dualsock.php
 *! DESCRIPTION: Skeleton websockets server to connect to 2 smartphones - 
 *!              1 for stereo goggles, anothe as a hand-held RC
 *! Copyright (C) 2021 Elphel, Inc
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
 *!
 */

//    print_r($argv);
    set_include_path ( get_include_path () . PATH_SEPARATOR . '/www/pages/include' );
    include 'show_source_include.php';
    include 'elphel_functions_include.php'; // includes curl functions
    define('SOCK_GOGGLES_FILE',        '/tmp/wstunnel-nobin.socket');  // defined in /etc/lighttpd.conf ws://<ip>:80/ws-nobin/"
    define('SOCK_REMOTE_CONTROL_FILE', '/tmp/wstunnel-nobin1.socket'); // defined in /etc/lighttpd.conf ws://<ip>:80/ws-nobin1/"
    define('SOCK_GOGGLES',        'SOCK_GOGGLES');
    define('SOCK_REMOTE_CONTROL', 'SOCK_REMOTE_CONTROL');
    define('LOGFILE', '/tmp/dualsock.log');
    set_time_limit(0); // no limit - run forever
    logmsg('Staring dualsock.php');
    
    if(file_exists(SOCK_GOGGLES_FILE))         unlink(SOCK_GOGGLES_FILE); //delete socket if it was already there
    if(file_exists(SOCK_REMOTE_CONTROL_FILE))  unlink(SOCK_REMOTE_CONTROL_FILE); //delete socket if it was already there
    init();
    $GLOBALS[SOCK_GOGGLES] =        socket_create(AF_UNIX, SOCK_STREAM, 0);
    $GLOBALS[SOCK_REMOTE_CONTROL] = socket_create(AF_UNIX, SOCK_STREAM, 0);
    socket_bind($GLOBALS[SOCK_GOGGLES],        SOCK_GOGGLES_FILE);          // bind new socket with filesystem path
    socket_bind($GLOBALS[SOCK_REMOTE_CONTROL], SOCK_REMOTE_CONTROL_FILE);
    socket_set_block($GLOBALS[SOCK_GOGGLES]);         // will wait for data available /ready to accept
    socket_set_block($GLOBALS[SOCK_REMOTE_CONTROL]);  // will wait for data available /ready to accept
    socket_listen($GLOBALS[SOCK_GOGGLES]);            // will listen to the socket for incoming data
    socket_listen($GLOBALS[SOCK_REMOTE_CONTROL]);     // will listen to the socket for incoming data
    
//    Warning: socket_write(): unable to write to socket [32]: Broken pipe in /www/pages/lwir16/dualsock.php on line 103
    
    // Main loop
    while (1) {
        $read = array(
            $GLOBALS[SOCK_GOGGLES],
            $GLOBALS[SOCK_REMOTE_CONTROL]
        );
        $write = NULL;
        $except = NULL;
        $select_timeout_sec =  5; // will be used for housekeeping actions, needed if there are no incoming commands
        $select_timeout_usec = 0; // when need faster than number of seconds 
        $num_changed_sockets = socket_select(
            $read, // will modify array to have only ready connections
            $write,
            $except,
            $select_timeout_sec,
            $select_timeout_usec
        ); // wait  
    
        if ($num_changed_sockets === false) {
            /* Error handling */
            logmsg ("Error: socket_select returned false!");
            sleep (1);
            continue;
        } else if ($num_changed_sockets > 0) {
            /* At least at one of the sockets something interesting happened */
            $active_connections = array();
            if (in_array($GLOBALS[SOCK_GOGGLES], $read)) {
                $active_connections[SOCK_GOGGLES] = socket_accept($GLOBALS[SOCK_GOGGLES]);
            }
                
            if (in_array($GLOBALS[SOCK_REMOTE_CONTROL], $read)) {
                $active_connections[SOCK_REMOTE_CONTROL] = socket_accept($GLOBALS[SOCK_REMOTE_CONTROL]);
            }
            // now $active_connections contain one or two temporary sockets, to read command through and respond, if empty - close
            foreach ($active_connections as $name=>$conn) {
                logmsg("Processing connection $name");
                processConnection($conn, $name);
            }

        } else { // do housekeeping functions
            logmsg ("No incoming commands - do housekeeping routines");
        }
    
    } // end of while (1) -main loop 
    
    
    function processConnection($conn, $name){
        // Just read lines and respond with the name to each until read empty string
        while (1) {
            $sr = socket_read($conn, 1000);
            logmsg($name." -> ".$sr." (".strlen($sr)." bytes)");
            if (!$sr){
                break; // happens when timeout and websocket is closed (or page refreshed)
            }
            if (strlen($sr) < 2){
                break;
            }
            $sw = socket_write($conn, $name.", got ".$sr." from you");
        }
        socket_close($conn);
        logmsg($name." closed");
    }
    
    function logmsg($msg){
        file_put_contents(LOGFILE, time().': '.$msg."\n", FILE_APPEND);
    }
    
    function init(){
        // Add all required initialization before establishing connection to the clients 
    }
?>
