<?php
header('Content-type: image/png');
echo file_get_contents($_GET['file']);
?>