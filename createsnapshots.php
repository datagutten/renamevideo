<?Php
//https://trac.ffmpeg.org/wiki/Create%20a%20thumbnail%20image%20every%20X%20seconds%20of%20the%20video
//http://www.nrg-media.de/2010/11/creating-screenshots-with-ffmpeg-is-slow/

require 'config_renamevideo.php';
require 'tools/video.php';
$video=new video;
require 'xmltvtools/tvguide.class.php';
$tvguide=new tvguide;

$path=$config['videopath'].$argv[1].'/';
if(is_dir($path))
	$dir=array_diff(scandir($config['videopath'].$argv[1]),array('.','..','Thumbs.db'));
elseif(is_file($argv[1]))
{
	$dir=array(basename($argv[1]));
	$path=dirname($argv[1]).'/';
}
else
	die("Invalid: $path\n");
foreach($dir as $file)
{
	$pathinfo=pathinfo($file);
	if(!isset($pathinfo['extension']) || $pathinfo['extension']!='ts' || !$info=$tvguide->parsefilename($file))
		continue;

	echo "Creating snapshots for $file\n";

	$steps=$video->snapshotsteps($path.$file,4,true,true,true);
	$snapshots=$video->snapshots($path.$file,$steps,$path.'/snapshots/');
	//die();
	if(file_exists($dir_title="../snapshottitle/pictures/$file/crop/"))
	{
		echo "Copy title image\n";
		foreach(array_diff(scandir($dir_title),array('.','..','Thumbs.db')) as $key=>$picture)
		{
			copy($dir_title.'/'.$picture,$path.'/snapshots/'.$file.'/900'.$key.'.png');
		}
	}
}
