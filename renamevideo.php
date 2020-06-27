<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Rename video</title>
<link href="renamevideo.css" rel="stylesheet" type="text/css" />
    <link href="static/snapshots.css" rel="stylesheet" type="text/css" />
</head>
<body>
<script type="text/javascript" src="renamevideo.js"></script>
<?Php
use datagutten\renamevideo;
use datagutten\tvdb;
use datagutten\dreambox\recording_info;
use datagutten\video_tools\video;
use datagutten\video_tools\exceptions as video_exceptions;
use datagutten\xmltv\tools\exceptions;
use datagutten\xmltv\tools\exceptions as xmltv_exceptions;
use datagutten\xmltv\tools\parse\parser;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

ini_set('display_errors',1);
//error_reporting('E_ALL');
require 'vendor/autoload.php';

if(isset($argv[1]))
	$_GET['folder']=$argv[1];

$config = require 'config.php';
require 'config_renamevideo.php';
if(empty($_GET['folder']))
{
	foreach(array_diff(scandir($config['video_path']),array('.','..')) as $folder)
	{
		if(!is_dir($config['video_path'].'/'.$folder))
			continue;
		echo "<a href=\"?folder=".htmlentities(urlencode($folder))."\">$folder</a><br />\n";
	}
	die();
}
if(isset($_GET['tvdb_lang']))
	$tvdb_lang=$_GET['tvdb_lang'];
else
	$tvdb_lang=false;

$dir_video=$config['video_path'].'/'.$_GET['folder'];
$dir_delete=$dir_video.'/delete';
if(!isset($config['snapshot_path']))
	$config['snapshot_path']=$dir_video.'/snapshots';

$guide = new parser();
//$guide->debug=true;

try {
	$tvdb = new tvdb_utils($config['tvdb']);
}
catch (tvdb\exceptions\tvdbException $e)
{
    echo $e->getMessage();
    $tvdb = null;
}

try {
    $dreambox = new recording_info();
    $recording_info = new renamevideo\recording_info($config['xmltv_path'], $config['xmltv_sub_folders']);
}
catch (Exception $e)
{
    die($e->getMessage());
}

$dom=new DOMDocumentCustom;
$dom->formatOutput = true;
$video=new video;
$filesystem = new Filesystem();
$utils = new renamevideo\utils();

if(isset($_POST['button']))
{
	for ($i=0; $i<count($_POST['epname']); $i++)
	{
		$newname=$_POST['epname'][$i];
		if ($newname!='')
		{
			$oldname=$_POST['basename'][$i];
			$newname=str_replace(array(': ','?','/'),array(' -',' - ','',''),$newname);
			$oldfilename=$dir_video.'/'.$oldname;
			if(isset($config['no_name_folders']) && array_search($folder,$config['no_name_folders'])!==false)
				$newfilename=$dir_video.'/'.$newname; //Do not add folder name to files in folders specified in config
			else
				$newfilename=$dir_video.'/'.$folder.' '.$newname; //Add the folder name to the file
			if(strpos($oldname,'HD'))
				$newfilename.=' HD'; //If recorded from an HD channel, add HD to the file name

			if(file_exists($newfilename.'.ts')) //Handle existing files
			{
				$newfilename.="_dupe_".time();
				echo "Dupe: $newname<br />\n";
			}

			if ($newname!='del' && !file_exists($newfilename.'.xml')) //Do not write info file for files to be deleted
			{
			    try {
                    $xmlprogram = $dreambox->recording_info($oldname . '.ts'); //Write xmltv data to file
                    $xmlprogram->asXML($newfilename . '.xml');
                }
                catch (exceptions\ProgramNotFoundException|exceptions\InvalidFileNameException|exceptions\ChannelNotFoundException $e)
                {
                    echo $e->getMessage()."<br />\n";
                }
			}
			if(!file_exists($dir_delete)) //Create folder for removed files
				$filesystem->mkdir($dir_delete);
			
			if(file_exists($dir_snapshots=$config['snapshot_path'].'/'.$_POST['basename'][$i].'.ts')) //Check if snapshot folder exists
			{
				if($newname=='del')
				{
				    try {
                        $filesystem->remove($dir_snapshots);
                    }
                    catch (IOException|UnexpectedValueException $e)
                    {
                        printf("Failed to delete snapshot dir %s: %s", $dir_snapshots, $e->getMessage());
                    }
                }
				else {
                    try {
                        $filesystem->rename($dir_snapshots, dirname($dir_snapshots) . '/' . $newname);
                    }
                    catch (IOException $e)
                    {
                        $filesystem->mkdir(dirname($dir_snapshots) . '/' . $newname);
                        $filesystem->mirror($dir_snapshots, dirname($dir_snapshots) . '/' . $newname);
                        $filesystem->remove($dir_snapshots);
                    }
                }
			}
			else
				echo "No snapshots found: $dir_snapshots<br>\n";
				
			foreach ($config['extensions'] as $extension)
			{
				//Add extension to the file name
				$oldfile=$oldfilename.$extension;
				$newfile=$newfilename.$extension;

				if(file_exists($oldfile))
				{
					if($newname=='del')
					{
						echo 'Delete: '.$oldname.$extension.'<br>';
						//var_dump($oldfile);
						rename($oldfile,$dir_delete."/$oldname.del$extension");
					}
					elseif(!file_exists($newfile) && file_exists($oldfile)) //Sjekk at filen finnes og at det nye navnet er ledig
					{
						echo 'Renamed '.$oldname.$extension.'>'.$newfile.'<br>';
						rename($oldfile,$newfile);
					}

				}

			}
		}
		
	}
}

if (!file_exists($dir_video))
	die("Folder not found: $dir_video");

$dir=scandir($dir_video);
$dir=array_diff($dir,array('.','..','Thumbs.db'));

sort($dir);

$form=$dom->createElement_simple('form',false,array('method'=>'post'));
$table=$dom->createElement_simple('table',$form,array('border'=>'1'));

$count=0;
$programinfo = [];
foreach ($dir as $key=>$file)
{
	$pathinfo=pathinfo($file);
	try {
        $info = recording_info::parse_file_name($file);
    }
    catch (InvalidArgumentException $e)
    {
        //echo "Invalid file name: $file<br />\n";
        continue;
    }

	if(!isset($pathinfo['extension']) || $pathinfo['extension']!='ts' || empty($info)) //Check if the file is a valid recording
		continue;
	if($count>=50)
		break;
	$recording_start=strtotime($info['datetime']);

	$table->appendChild($tr=$dom->createElement('tr')); //Row for recording
	
	$td_file=$dom->createElement_simple('td',$tr,array('class'=>'filename'),$file);

	try {
    	$duration=$video->duration($dir_video.'/'.$file);

		$recording_end=$recording_start+$duration;
		$dom->createElement_simple('p',$td_file,false,sprintf('%s-%s',date('H:i',$recording_start),date('H:i',$recording_end)));
		$dom->createElement_simple('p',$td_file,false,video::seconds_to_time($duration));
	}
	catch (video_exceptions\VideoException|DependencyFailedException $e)
    {
        $dom->createElement_simple('p',$td_file,false,$e->getMessage());
    }

	$td_description=$dom->createElement_simple('td',$tr,array('class'=>'description'));
	$displaytext='';

    $programinfo = [];
    //Get info from XML
	try
	{
        list($programinfo['xml'], $starttimestamp, $generator) = $recording_info->file_info_xml($file);

		$offset=$starttimestamp-$recording_start;
		if($offset<0)
			$td_description->setAttribute('class','category error');
		elseif($offset<60*5 || $offset>60*10)
			$td_description->setAttribute('class','category warning');
	}
	catch (ProgramNotFoundException|ChannelNotFoundException $e) {
        $dom->createElement_simple('p', $td_description, array('class' => 'error'), 'Error from xmltv: ' . $e->getMessage());
    }


    //Parse eit file if found
	try {
        $programinfo['eit'] = $recording_info->eit_info($dir_video . '/' . $pathinfo['filename'] . '.eit');
    }
    catch (FileNotFoundException $e)
    {
        echo $e->getMessage()."\n";
    }
	$info_sources=array('xml','eit');
	$info_fields=array('title','seasonepisode','description', 'start', 'end', 'category', 'sub-title');
    $programinfo_final = $recording_info->combine_info($programinfo);

	if(!empty($programinfo['eit']['season_episode']) && $programinfo_final['seasonepisode']['season']==0) //Check if eit has more correct season than other sources
		$programinfo_final['seasonepisode']['season']=$programinfo['eit']['season_episode']['season'];

	if(is_object($tvdb) && isset($programinfo_final['title']) && isset($programinfo_final['seasonepisode'])) //If series title and episode num is known, find information on TVDB
	{
		$p_tvdb=$dom->createElement_simple('p',$td_description,array('class'=>'tvdb'),'TVDB: ');

		if(isset($_GET['tvdb_title']))
			$tvdb_searchstrings=array($_GET['tvdb_title']);
		else
            $tvdb_searchstrings=tvdb_utils::generate_search_strings($programinfo_final['title']);

		try {
			$tvdb_series = $tvdb->series_search_helper($tvdb_searchstrings, $tvdb_lang);
			$episode = $tvdb->episode_info($tvdb_series['id'], $programinfo_final['seasonepisode']['season'], $programinfo_final['seasonepisode']['episode']);
		}
		catch (tvdb\exceptions\api_error|tvdb\exceptions\noResultException $e)
        {
			$p_tvdb_error=$dom->createElement_simple('span',$p_tvdb,array('class'=>'error'), $e->getMessage());
        }

		if(!empty($episode)) //Series is found, find episode
		{
            if(!empty($tvdb_series['seriesName'])) {
                $url = tvdb\tvdb::episode_link($episode);
                $text = $tvdb_series['seriesName'].' '.tvdb\tvdb::episode_name($episode);
				$dom->createElement_simple('a', $p_tvdb, array('href' => $url), $text);
			}

			$p_tvdb->appendChild($dom->createElement('br'));

			if(!empty($episode['episodeName']) && empty($programinfo_final['seasonepisode']))
				$dom->createElement_simple('span',$p_tvdb,array('id'=>'tvdb_episode'.$count,'class'=>'tvdb_episode'),$episode['episodeName']);
		}
	}

    if(!empty($programinfo_final['start'])) {
        if (!empty($programinfo_final['end']))
            $dom->createElement_simple('span', $td_description, array('class' => 'final_start', 'id' => 'final_end' . $count), sprintf('%s-%s ', $programinfo_final['start'], $programinfo_final['end']));
        else
            $dom->createElement_simple('span', $td_description, array('class' => 'final_start', 'id' => 'final_start' . $count), $programinfo_final['start'] . ' ');
    }
	if(!empty($programinfo_final['title']))
	    $dom->createElement_simple('span', $td_description, array('class'=>'final_title', 'id'=>'final_title'.$count), $programinfo_final['title']);
	if(!empty($programinfo['xml']) && !empty($programinfo['eit']) && $programinfo['eit']['title']!=$programinfo['xml']['title'])
        $dom->createElement_simple('p', $td_description, array('class'=>'warning', 'id'=>'mismatch_title'.$count), sprintf('Title mismatch: XML: %s EIT: %s', $programinfo['xml']['title'], $programinfo['eit']['title']));
    if(!empty($programinfo_final['description']))
        $p_desc=$dom->createElement_simple('p',$td_description,array('class'=>'final_description', 'id'=>'final_description'.$count), $programinfo_final['description']);
    if(!empty($programinfo_final['seasonepisode']))
        $dom->createElement_simple('p', $td_description, array('class'=>'final_season_episode', 'id'=>'final_season_episode'.$count), sprintf('S%02dE%02d', $programinfo_final['seasonepisode']['season'], $programinfo_final['seasonepisode']['episode']));
    if(!empty($programinfo_final['sub-title']))
        $dom->createElement_simple('p', $td_description, array('class'=>'final_sub-title', 'id'=>'final_sub-title'.$count), $programinfo_final['sub-title']);
    if(isset($generator))
        $dom->createElement_simple('p', $td_description, null, $generator);

    if(isset($programinfo_final['end_timestamp']) && isset($programinfo_final['start_timestamp']))
        $program_duration = $programinfo_final['end_timestamp'] - $programinfo_final['start_timestamp'];
    else
        $program_duration = 0;
    if($duration>$program_duration+15) //Multiple episodes
    {
        try {
			$multi_info = $recording_info->multi_info($file, $duration);
			$td_description->textContent = '';
			foreach (explode("\n", $multi_info) as $line) {
				$td_description->appendChild(new DOMText($line));
				$dom->createElement_simple('br', $td_description);
			}
		}
		catch (xmltv_exceptions\XMLTVException $e)
        {
            $dom->createElement_simple('span', $td_description, ['class'=>'error'], $e->getMessage());
        }
    }

	$td_name=$dom->createElement('td');
	$tr->appendChild($td_name);
	$input_name=$dom->createElement_simple('input',$td_name,array('name'=>'epname[]','type'=>'text','size'=>'6','id'=>'input'.$count));
	$input_basename=$dom->createElement_simple('input',$td_name,array('name'=>'basename[]','type'=>'hidden','value'=>$pathinfo['filename']));
    //Show snapshots
	try {
        $snapshots = $recording_info->snapshots($dir_video.'/'.$file);
        if(!empty($snapshots)) {
            $snapshots_html = $utils->render('snapshots.twig', ['snapshots' => $snapshots]);
            $fragment = $dom->createDocumentFragment();
            $fragment->appendXML($snapshots_html);
            $td_snapshots = $dom->createElement_simple('td', $tr, array('class' => 'snapshots'));
            $td_snapshots->appendChild($fragment);
        }
	}
    catch (FileNotFoundException $e) {
        $td_name->setAttribute('colspan', 2);
    }
    catch (Exception $e)
    {
        $td_snapshots=$dom->createElement_simple('td',$tr,array('class'=>'snapshots'), $e->getMessage());
    }

	unset($displaytext,$xmlprogram,$programinfo,$programinfo_final, $generator);
	$count++;
}

$dom->createElement_simple('span',$form,array('style'=>'display: none;','id'=>'field_count'),(string)$count);
if($count>0)
{
	$dom->createElement_simple('span',$form,array('onclick'=>'fill_episodes()'),'Fill episode names');
	$dom->createElement_simple('input',$form,array('type'=>'submit','name'=>'button','value'=>'Submit'));
	echo $dom->saveXML($form);
}
else
	echo 'Nothing to be done';
?>
</body>
</html>
