<?php

if (isset($_GET['port']))
    $port = $_GET['port'];
else
    die();

if (isset($_GET['rel']))
    $rel = $_GET['rel'];
else
    die();

header('Content-type:image/jpeg');
echo file_get_contents("http://localhost:$port/$rel");
    
?> 