<?php
header('Content-type: image/png');
$fp=fopen($_GET['file'],'r');
fpassthru($fp);
fclose($fp);
?>