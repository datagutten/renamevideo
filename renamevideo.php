<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Rename video</title>
<link href="renamevideo.css" rel="stylesheet" type="text/css" />
</head>
<body>
<script type="text/javascript" src="renamevideo.js"></script>
<?Php

use datagutten\dreambox\recording_info;
use datagutten\xmltv\tools\exceptions\ChannelNotFoundException;
use datagutten\xmltv\tools\exceptions\ProgramNotFoundException;
use datagutten\xmltv\tools\parse\parser;

ini_set('display_errors',1);
//error_reporting('E_ALL');
require 'vendor/autoload.php';

if(isset($argv[1]))
	$_GET['folder']=$argv[1];

require 'config_renamevideo.php';
if(empty($_GET['folder']))
{
	foreach(array_diff(scandir($config['videopath']),array('.','..')) as $folder)
	{
		if(!is_dir($config['videopath'].'/'.$folder))
			continue;
		echo "<a href=\"?folder=".htmlentities(urlencode($folder))."\">$folder</a><br />\n";
	}
	die();
}
if(isset($_GET['tvdb_lang']))
	$tvdb_lang=$_GET['tvdb_lang'];
else
	$tvdb_lang=false;

$dir_video=$config['videopath'].$_GET['folder'];
$dir_delete=$dir_video.'/delete';
if(!isset($config['snapshotpath']))
	$config['snapshotpath']=$dir_video.'/snapshots';

$guide = new parser();
//$guide->debug=true;

$tvdb=new tvdb();

if(file_exists('tvdb_mappings.php'))
	require 'tvdb_mappings.php';

try {
    $dreambox = new recording_info();
}
catch (Exception $e)
{
    die($e->getMessage());
}

$dom=new DOMDocumentCustom;
$dom->formatOutput = true;
$video=new video;

if(isset($_POST['button']))
{
	for ($i=0; $i<count($_POST['name']); $i++)
	{
		$newname=$_POST['name'][$i];
		if ($newname!='')
		{
			$oldname=$_POST['basename'][$i];
			$newname=str_replace(array(':','?','/'),array(' - ','',''),$newname);
			
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
				$xmlprogram=$dreambox->recording_info($oldname.'.ts'); //Write xmltv data to file
				if(is_object($xmlprogram))
					$xmlprogram->asXML($newfilename.'.xml');
			}
			if(!file_exists($dir_delete)) //Create folder for removed files
				mkdir($dir_delete);
			
			if(file_exists($dir_snapshots=$config['snapshotpath'].'/'.$_POST['basename'][$i].'.ts')) //Check if snapshot folder exists
			{
				if($newname=='del')
				{
					if(!file_exists($config['snapshotpath'].'/delete')) //Create folder for removed snapshots
						mkdir($config['snapshotpath'].'/delete');
					rename($dir_snapshots,$config['snapshotpath'].'/delete/'.$_POST['basename'][$i]);
				}
				else
					rename($dir_snapshots,dirname($dir_snapshots).'/'.$newname);
			}
			else
				echo "No snapshots found: $dir_snapshots<br>\n";
				
			foreach ($extensions as $extension)
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

	$table->appendChild($tr=$dom->createElement('tr')); //Row for recording
	
	$td_file=$dom->createElement_simple('td',$tr,array('class'=>'filename'),$file);
	$td_description=$dom->createElement_simple('td',$tr,array('class'=>'description'));
	$displaytext='';

    //Get info from XML
	try
	{
        $xmlprogram=$dreambox->recording_info($file);
		$starttimestamp=strtotime($xmlprogram->attributes()->start);

		if(isset($starttimestamp))
			$dom->createElement_simple('span',$td_description,array('class'=>'starttime'),date('H:i',$starttimestamp)); //Add a span with the start time to the td
		if(isset($xmlprogram->title)) //Get the title
		{
			$dom->createElement_simple('span',$td_description,array('class'=>'title'),(string)$xmlprogram->title);
			$programinfo['xml']['title']=(string)$xmlprogram->title;
		}
		if(isset($xmlprogram->category)) //Get the category
		{
			if(!is_array($xmlprogram->category))
				$category=' - '.$xmlprogram->category;
			else
			{
				$category=' - '.$xmlprogram->category[1];
				$category.=print_r($xmlprogram->category,true);
			}
			
			$span_category=$dom->createElement('span',$category);
			$span_category->setAttribute('class','category');
			$td_description->appendChild($span_category);
		}
		if(isset($xmlprogram->{'sub-title'})) //Get the sub-title
		{
			$dom->createElement_simple('p',$td_description,array('class'=>'title'),(string)$xmlprogram->{'sub-title'});
			$programinfo['xml']['sub-title']=(string)$xmlprogram->{'sub-title'};
		}
		if(isset($xmlprogram->desc)) //Get the description
		{
			$td_description->appendChild($dom->createElement('br'));
			$span_description=$dom->createElement('span',(string)$xmlprogram->desc);
			$td_description->appendChild($span_description);
			
			$programinfo['xml']['description']=(string)$xmlprogram->desc;
		}
		if(isset($xmlprogram->{'episode-num'}) && $episodestring=$guide->season_episode($xmlprogram)) //Get the episode-num string and convert it to season and episode
		{
			$programinfo['xml']['seasonepisode']=$guide->season_episode($xmlprogram,false);
			$td_description->appendChild($dom->createElement('br'));
			$span_description=$dom->createElement_simple('span',$td_description,array('class'=>'seasonepisode','id'=>'seasonepisode'.$key),$episodestring);
		}
		$recording_start=strtotime($info['datetime']);
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
        //$programinfo['eit']=$eitparser->parse($eitfile);
        $programinfo['eit'] = recording_info::parse_eit($dir_video . '/' . $pathinfo['filename'] . '.eit', 'array');
        $programinfo['eit']['seasonepisode'] = $programinfo['eit']['season_episode'];
        if(empty($programinfo['eit']['description']) && !empty($programinfo['eit']['short_description']))
            $programinfo['eit']['description'] = $programinfo['eit']['short_description'];
        var_dump($programinfo['eit']);
    }
    catch (FileNotFoundException $e)
    {
        echo $e->getMessage()."\n";
    }
	$info_sources=array('xml','eit');
	$info_fields=array('title','seasonepisode','description');
	foreach($info_fields as $field) //Decide which information source to use
	{
		foreach($info_sources as $source)
		{
			if(isset($programinfo[$source][$field]))
			{
				$programinfo_final[$field]=$programinfo[$source][$field];
				continue 2;
			}
		}
	}

	if(isset($programinfo['eit']['title']))
		$p_eit=$dom->createElement_simple('p',$td_description,array('class'=>'eit'),sprintf('EIT: %s',$programinfo['eit']['title']));
	if(isset($programinfo['eit']['raw_season_episode_string']))
		$dom->createElement_simple('span',$p_eit,array('class'=>'eit'),$programinfo['eit']['raw_season_episode_string']);
	if(!empty($programinfo['eit']['season_episode']) && $programinfo_final['seasonepisode']['season']==0) //Check if eit has more correct season than other sources
		$programinfo_final['seasonepisode']['season']=$programinfo['eit']['season_episode']['season'];

	if(is_object($tvdb) && isset($programinfo_final['title']) && isset($programinfo_final['seasonepisode'])) //If series title and episode num is known, find information on TVDB
	{
		$p_tvdb=$dom->createElement_simple('p',$td_description,array('class'=>'tvdb'),'TVDB: ');

		$tvdb_searchstrings=array(preg_replace('/(.+):.+/','$1',$programinfo_final['title']),$programinfo_final['title']); //Search for complete title and title cut at :

		foreach($tvdb_searchstrings as $search)
		{
			$tvdb->error='';
			if(isset($tvdb_nomatch) && array_search($search,$tvdb_nomatch)!==false) //Do not retry searches that have failed previously
				continue;
			if(isset($tvdb_mappings) && ($tvdb_id=array_search($search,$tvdb_mappings))!==false) //Check if there is a mapping between series name and tvdb id
			{
				if(isset($tvdb_series_cache[$tvdb_id]))
				{
					//echo "Fetch mapped series from cache\n";
					$tvdb_series=$tvdb_series_cache[$tvdb_id];
				}
				else //Series mapped but not in cache
				{
					//echo "Fetch mapped series\n";
					$tvdb_series=$tvdb->get_series_and_episodes($tvdb_id); //Fetch series
					$tvdb_series_cache[$tvdb_id]=$tvdb_series; //Add to cache
				}
				break;
			}
			else //Not found by lookup, search for name on TVDB
			{
				//echo "Search for series\n";
				try {
                    $tvdb_series_search = $tvdb->series_search($search);
                }
                catch (Exception $e)
                {
                    $tvdb_series_search = null;
                }

				if(is_array($tvdb_series_search))
				{
					if($tvdb_lang===false)
						$lang=$tvdb->last_search_language;
					else
						$lang=$tvdb_lang;
					$tvdb_series=$tvdb->get_series_and_episodes($tvdb_series_search['id'],$lang);
					$tvdb_series_cache[$tvdb_series['Series']['id']]=$tvdb_series;
					$tvdb_mappings[$tvdb_series['Series']['id']]=$search; //Add id to mappings to avoid search next time
					break;
				}
			}
			//If we are here the search has not matched
			$tvdb_nomatch[]=$search;
		}

        $key = sprintf('S%02dE%02d', $programinfo_final['seasonepisode']['season'], $programinfo_final['seasonepisode']['episode']);
		if(isset($tvdb_series) && is_array($tvdb_series) && isset($tvdb_series['Episode'][$key])) //Series is found, find episode
		{
            $tvdbinfo = $tvdb_series['Episode'][$key];
            $dom->createElement_simple('a',$p_tvdb,array('href'=>$tvdb->series_link($tvdbinfo)),$tvdb_series['Series']['seriesName']);

			$p_tvdb->appendChild($dom->createElement('br'));

			if(($episodename=$tvdb->episodename($tvdbinfo))!==false)
				$dom->createElement_simple('span',$p_tvdb,array('id'=>'tvdb_episode'.$count,'class'=>'tvdb_episode'),$episodename);
		}
		if(!empty($tvdb->error)) //Show TVDB errors
		{
			$p_tvdb_error=$dom->createElement_simple('span',$p_tvdb,array('class'=>'error'),'TVDB error: '.$tvdb->error);
			unset($tvdb->error);
		}
	}

	if(file_exists($eitfile=$dir_video.$pathinfo['filename'].'.eit') && (!isset($xmlprogram) || !is_object($xmlprogram))) //If the program is missing xml data, try to get info from eit
		$dom->createElement_simple('span',$td_description,array('class'=>'eit'),"---".$guide->eitparser($eitfile)."---");

	$td_name=$dom->createElement('td');
	$tr->appendChild($td_name);
	$input_name=$dom->createElement_simple('input',$td_name,array('name'=>'name[]','type'=>'text','size'=>'6','id'=>'input'.$count));
	$input_basename=$dom->createElement_simple('input',$td_name,array('name'=>'basename[]','type'=>'hidden','value'=>$pathinfo['filename']));
	
	//Show snapshots
	if(file_exists($dir_snapshots=$config['snapshotpath'].'/'.$file))
	{
		$td_snapshots=$dom->createElement_simple('td',$tr,array('class'=>'snapshots'));
		foreach(array_diff(scandir($dir_snapshots),array('.','..','Thumbs.db')) as $snapshot)
		{
			$snapshotfile=$dir_snapshots.'/'.$snapshot;
			if(is_file($snapshotfile))
			{
				$a_snapshot=$dom->createElement_simple('a',$td_snapshots,array('href'=>'bilde.php?file='.$snapshotfile));
				$dom->createElement_simple('img',$a_snapshot,array('src'=>'bilde.php?file='.$snapshotfile,'height'=>'150px'));
			}
		}
	}
	else
		$td_name->setAttribute('colspan',2);

	unset($displaytext,$xmlprogram,$programinfo,$programinfo_final);
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
