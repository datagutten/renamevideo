<?Php
//https://trac.ffmpeg.org/wiki/Create%20a%20thumbnail%20image%20every%20X%20seconds%20of%20the%20video
//http://www.nrg-media.de/2010/11/creating-screenshots-with-ffmpeg-is-slow/

require 'vendor/autoload.php';
$config = require 'config.php';

if(is_file($argv[1])) //Argument is an absolute path to a file
{
	$pathinfo=pathinfo($argv[1]);
	$files=array($pathinfo['basename']);
	$dir_video=$pathinfo['dirname'];
}
elseif(is_dir($argv[1]))
{
	$dir_video=$argv[1];
}
else //Argument is a sub folder to video path
{
	$dir_video=$config['video_path'].'/'.$argv[1];
	if(!file_exists($dir_video))
		die("$dir_video does not exist\n");
}

if(!isset($files))
	$files=array_diff(scandir($dir_video),array('.','..','Thumbs.db'));

$dir_snapshots=$dir_video.'/snapshots';
foreach($files as $file)
{
	$pathinfo=pathinfo($file);
    if(file_exists($dir_snapshots.'/'.$file))
        continue;

	if(!isset($pathinfo['extension']) || $pathinfo['extension']!='ts')
		continue;

	echo "Creating snapshots for $file\n";
    try {
        $steps=video::snapshotsteps($dir_video.'/'.$file, 4, true, true);
    }
    catch (DependencyFailedException $e)
    {
        echo $e->getMessage();
        break;
    }
    try {
        $snapshots = video::snapshots($dir_video . '/' . $file, $steps, $dir_snapshots);
    }
    catch (FileNotFoundException|Exception $e)
    {
        echo $e->getMessage()."\n";
        continue;
    }
	if(file_exists($dir_title="../snapshottitle/pictures/$file/crop/"))
	{
		echo "Copy title image\n";
		foreach(array_diff(scandir($dir_title),array('.','..','Thumbs.db')) as $key=>$picture)
		{
			copy($dir_title.'/'.$picture,$path.'/snapshots/'.$file.'/900'.$key.'.png');
		}
	}
}
