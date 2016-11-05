<?Php
require 'xmltvtools/tvguide.class.php';
$tvguide=new tvguide;
require 'config_renamevideo.php';
$dir=glob(substr($config['videopath'],0,-1).'/'.$argv[1].'/*.ts*');
require 'tools/filnavn.php';

foreach($dir as $file)
{
	$file_info=$tvguide->parsefilename($file);
	$recording_start=strtotime($file_info['datetime']);
	if(($xmlprogram=$tvguide->recordinginfo($file))!==false) //Get info from XML
	{
		$attributes=$xmlprogram->attributes();

		$program_start=strtotime($attributes['start']);
		$offset=$program_start-$recording_start;
		echo $offset."\n";
		//var_dump($offset!=0 && ($offset<60*5 || $offset>60*10));
		if($offset!=0 && ($offset<60*5 || $offset>60*10))
			continue;
		else
		{
			//print_r($xmlprogram);
			//print_r($file_info);
			$title=(string)$xmlprogram->title;
			$pathinfo=pathinfo($file);
			//print_r($pathinfo);

			$outdir=$pathinfo['dirname'].'/'.filnavn($title);
			if(!file_exists($outdir))
				mkdir($outdir);
			foreach($config['extensions'] as $extension)
			{
				if(file_exists($file=$pathinfo['dirname'].'/'.$pathinfo['filename'].$extension))
				{
					echo "mv '$file' '$outdir'\n";
					if(!file_exists($outfile=$outdir.'/'.basename($file)))
						//echo "rename($file,$outfile)\n";
						rename($file,$outfile);
					else
						echo "File exists $outfile\n";
				}
				else
					echo $file."\n";
			}
			//break;
		}
	}
	/*else
		echo "No xml info\n";*/

}
