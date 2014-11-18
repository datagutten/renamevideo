<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Rename video</title>
</head>
<body>
<?Php
$starttime=time();
ini_set('display_errors',1);
error_reporting('E_ALL');

if(isset($argv[1]))
	$_GET['folder']=$argv[1];

require 'config_renamevideo.php';
$dir_video=$config['videopath'].$_GET['folder'];
$dir_delete=$dir_video.'/delete';

require 'xmltvtools/tvguide.class.php';
$guide=new tvguide;
//$guide->debug=true;

require 'tvdb/tvdb.php';
$tvdb=new tvdb($tvdb_key);

if(file_exists('tvdb_mappings.php'))
	require 'tvdb_mappings.php';

require 'eitparser.php';
$eitparser=new eitparser;

if (isset($_POST['button']))
{
	for ($i=0; $i<count($_POST['navn']); $i++)
	{
		$newname=$_POST['navn'][$i];
		if ($newname!='')
		{
			$oldname=$_POST['basename'][$i];
			$newname=str_replace(array(':','?','/'),array(' - ','',''),$newname);
			
			$oldfilename=$path.$oldname;
			if ($folder=='NRK' || strpos($folder,'input')!==false || strpos($folder,'diverse')!==false)
				$newfilename=$path.'/'.$newname; //Ikke bruk navnet på samlemapper i filnavn
			elseif (strpos($oldname,'HD'))
				$newfilename=$path.'/'.$folder.' '.$newname.' HD'; //Er det tatt opp fra en HD kanal, legg til HD i filnavn
			else
				$newfilename=$path.'/'.$folder.' '.$newname; //Lag filnavn med seriens navn
			
			if(file_exists($newfilename.'.ts')) //Handle existing files
			{
				$newfilename.="_dupe_".time();
				echo "Dupe: $newname<br />\n";
			}
				
			if ($newname!='del' && !file_exists($newfilename.'.xml')) //Ikke lag nfo for filer som skal slettes
			{
				$xmlprogram=$guide->recordinginfo($oldname.'.ts'); //Write xmltv data to file
				if(is_object($xmlprogram))
					$xmlprogram->asXML($newfilename.'.xml');
			}
			if(!file_exists($dir_delete)) //Create folder for removed files
				mkdir($dir_delete);
			if(file_exists($dir_snapshots=$dir_video.'/snapshots/'.$_POST['basename'][$i].'.ts')) //Check if snapshot folder exists
				rename($dir_snapshots,$dir_delete.'/'.$_POST['basename'][$i]);
			else
				echo "No snapshots found: $dir_snapshots<br>\n";
				
			foreach ($extensions as $extension)
			{
				$oldfile=$oldfilename.$extension;
				$newfile=$newfilename.$extension; //Lag filnavn med type
				//echo $oldname.$extension.'>'.$newfile.":<br>";		
				//var_dump($oldfile);
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

if (!file_exists($path))
	die("Folder not found: $path");

$dir=scandir($path);
$dir=array_diff($dir,array('.','..','Thumbs.db'));

sort($dir);
$i=0; 

?>
<form id="form1" name="form1" method="post" action="">
<table border=1>
<?Php
$count=0;
foreach ($dir as $key=>$file)
{
	$pathinfo=pathinfo($file);
	if(!isset($pathinfo['extension']) || $pathinfo['extension']!='ts' || !$info=$guide->parsefilename($file)) //Check if the file is a valid recording
		continue;
	if($count>=50)
		break;
	$count++;

	$displaytext='';

	if(($xmlprogram=$guide->recordinginfo($file))!==false) //Get info from XML
	{
		if(!is_object($xmlprogram))
		{
			var_dump($file);
			var_dump($xmlprogram);
			var_dump($guide->error);
		}
		$starttimestamp=strtotime($xmlprogram->attributes()->start);
	
		if(isset($starttimestamp))
			$displaytext.=date('H:i',$starttimestamp)."&nbsp;";
		if(isset($xmlprogram->title)) //Get the title
		{
			$displaytext.=' '.$xmlprogram->title;
			$programinfo['xml']['title']=(string)$xmlprogram->title;
		}
		if(isset($xmlprogram->category)) //Get the category
		{
			if(!is_array($xmlprogram->category))
				$displaytext.=' - '.$xmlprogram->category."<br />\n";
			else
				$displaytext.=' - '.$xmlprogram->category[1]."<br />\n";
		}
		if(isset($xmlprogram->desc)) //Get the description
		{
			$displaytext.=$xmlprogram->desc."<br />\n";
			$programinfo['xml']['description']=(string)$xmlprogram->desc;
		}
		if(isset($xmlprogram->{'episode-num'}) && $episodestring=$guide->seasonepisode($xmlprogram)) //Get the episode-num string and convert it to season and episode
		{
			$programinfo['xml']['seasonepisode']=$guide->seasonepisode($xmlprogram,false);
			$displaytext.=$episodestring."<br />\n";
		}
	}
	elseif(empty($guide->error))
	{
		$displaytext.="tvguide returned false with no error<br />\n";
	}
	if(file_exists($eitfile=$path.$pathinfo['filename'].'.eit'))
		$programinfo['eit']=$eitparser->parse($eitfile);
	$info_sources=array('xml','eit');
	$info_fields=array('title','seasonepisode','description');
	foreach($info_fields as $field)
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
		$displaytext.="EIT: {$programinfo['eit']['title']}<br />\n";
	if(isset($programinfo['eit']['raw_seasonepisodestring']))
		$displaytext.=' '.$programinfo['eit']['raw_seasonepisodestring'];

	if(isset($programinfo_final['title']) && isset($programinfo_final['seasonepisode'])) //If series title and episode num is known, find information on TVDB
	{
		$tvdb_searchstrings=array($programinfo_final['title'],preg_replace('/(.+):.+/','$1',$programinfo_final['title'])); //Search for complete title and title cut at :

		foreach($tvdb_searchstrings as $search)
		{
			$tvdb->error='';

			if(isset($tvdb_mappings) && ($tvdb_id=array_search($search,$tvdb_mappings))!==false)
			{
				$tvdb_series=$tvdb->findseries($tvdb_id);
				break;
			}
			else //Not found by lookup, search for name on TVDB
			{
				$tvdb_series=$tvdb->findseries($search,$tvdb->lang);
				if(is_array($tvdb_series))
					break;
				$tvdb->error='';
				$tvdb_series=$tvdb->findseries($search,'all'); //Try all languages
				if(is_array($tvdb_series))
					break;		
			}
		}

		if(is_array($tvdb_series)) //Series is found, find episode
		{
			if($programinfo_final['seasonepisode']['season']==0)
				$programinfo_final['seasonepisode']['season']=1;
			$tvdbinfo=$tvdb->finnepisode($tvdb_series,$programinfo_final['seasonepisode']['season'],$programinfo_final['seasonepisode']['episode']);
			
			if(isset($tvdbinfo['Episode']))
			{
				$tvdbinfo=$tvdbinfo['Episode'];
				$displaytext.="TVDB: {$tvdb_series['Series']['SeriesName']}<br />\nS".str_pad($tvdbinfo['Combined_season'],2,'0',STR_PAD_LEFT).'E'.str_pad($tvdbinfo['EpisodeNumber'],2,'0',STR_PAD_LEFT).' - '.$tvdbinfo['EpisodeName']."<br />\n";
			}
		}

		if(!empty($tvdb->error))
			$displaytext.='<br />'.$tvdb->error."<br />\n";
	}

	if (file_exists($eitfile=$path.$pathinfo['filename'].'.eit') && (!isset($xmlprogram) || !is_object($xmlprogram)))
		$displaytext="---".$guide->eitparser($eitfile)."---";

	if(!empty($guide->error)) //Vis feilmeldinger i tabellen
	{
		$displaytext.="<br />\nError: ".$guide->error;
		$guide->error='';	
	}
	//Lag fil med info
	$nfo=trim(str_replace("<br />","\r\n",$displaytext));
	if(isset($starttimestamp))
		$nfo.="\r\n".date('Y-m-d H:i',$starttimestamp);
	$nfo.="\r\n".$file;

	?>
		<tr>
			<td><?Php echo '<a href="file://'.$winpath.$file.'">'.str_replace('  ','&nbsp;&nbsp;',htmlentities($file)).'</a>'?></td>
			<td><?php echo $displaytext; ?></td>
			<td>
				<input name="navn[]" type="text" id="textfield" value="" size="6" />
				<input name="basename[]" type="hidden" id="hiddenField" value="<?Php echo $pathinfo['filename']; ?>" />
                <input name="nfo[]" type="hidden"  value="<?php echo isset($nfo) ? $nfo:''; ?>"/>
			</td>
         <td>
		 <?Php
		 //Show snapshots
		if(file_exists($dir_snapshots=$path.'/snapshots/'.$file))
		{
			foreach(array_diff(scandir($dir_snapshots),array('.','..','Thumbs.db')) as $snapshot)
			{
				$snapshotfile=$dir_snapshots.'/'.$snapshot;
				if(is_file($snapshotfile))
					echo '<img src="bilde.php?file='.$snapshotfile.'" width="15%" height="15%"/>'."\n";
			}
		}
		?>
        </td>
		</tr>
		<?Php

	unset($displaytext,$xmlprogram,$nfo,$programinfo,$programinfo_final);
}

?>
</table>
<input type="submit" name="button" id="button" value="Submit" />
</form>
</body>
</html>
