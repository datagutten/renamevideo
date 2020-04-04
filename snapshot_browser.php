<?php
use \datagutten\renamevideo;
require 'vendor/autoload.php';
$folder = $_GET['folder'];

$files = scandir($folder);
$config = require 'config.php';
$recordings = [];
$info = new renamevideo\recording_info();
$utils = new renamevideo\utils();

foreach ($files as $file)
{
	if($file[0]=='.')
		continue;
	$file = $folder.'/'.$file;
	$pathinfo = pathinfo($file);

	if(!is_file($file) || $pathinfo['extension']!=='ts')
		continue;

	try {
		$snapshots = $info->snapshots($file);
		$recordings[] = ['file' => $file, 'snapshots' => $snapshots];
	}
	catch (FileNotFoundException $e)
	{
		$recordings[] = ['file' => $file, 'snapshots' => null];
	}
}

echo $utils->render('snapshot_browser.twig', ['files'=>$recordings, 'title'=>'Snapshot browser']);