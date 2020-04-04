<?php
if(isset($_GET['file'])) {
    $size = getimagesize($_GET['file']);
    set_error_handler('handler');
    header("Content-type: {$size['mime']}");

    $fp = fopen($_GET['file'], 'r');
    if($fp!==false)
    {
        fpassthru($fp);
        fclose($fp);
    }
}

function image_error($string)
{
    $im=imagecreatetruecolor(1000,20);
    imagefill($im, 0,0, imagecolorallocate($im, 255,255,255));
    imagestring($im, 4, 0,0, $string, imagecolorallocate($im, 255,0,0));
    header("Content-type: image/png");
    imagepng($im);
}

function handler(/** @noinspection PhpUnusedParameterInspection */ $errno, $errstr)
{
    image_error($errstr);
}