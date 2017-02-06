<?php

/**
 * Description:
 *   Tests port 0 (2323) imgsrv pointers
 *
 * Usage:
 * * from 10393 ssh session:
 *   pc~$ ssh root@192.168.0.9
 *   ...
 *   10393~# php /www/pages/pointers/test.php
 *   ...
 *   CTRL-C to stop
 *
*/

file_get_contents("http://localhost:2323/towp/save");

while(true){
  file_get_contents("http://localhost:2323/torp/wait/img/next/save");
  usleep(110000);
}

?> 
