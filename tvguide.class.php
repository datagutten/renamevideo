<?Php
require 'class_filepath.php';
class tvguide extends filepath
{
	public $xmltvpath;
	public $xmltv_cache;
	public $xmltv_subfolder_main;
	public $xmltv_subfolder_alt;
	public $error;
	public $debug=false;
	public function __construct()
	{
		ini_set('display_errors',1);
		error_reporting(E_ALL);
		require 'config.php';
		if(!isset($xmltv_subfolder_main))
			die("Main subfolder not specified ($xmltv_subfolder_main)");
		$this->xmltv_subfolder_main=$xmltv_subfolder_main;
		if(isset($xmltv_subfolder_alt))
			$this->xmltv_subfolder_alt=$xmltv_subfolder_alt; //Subfolder for alternative source
		if(substr($xmltvpath,-1,1)!='/')
			$xmltvpath.='/';
		parent::__construct($xmltvpath);
		$this->xmltvpath=$xmltvpath;
		if(isset($xmltv_cache))
		{
			if(substr($xmltv_cache,-1,1)!='/')
				$xmltv_cache.='/';
			$this->xmltv_cache=$xmltv_cache;
		}
	}
	 public function selectchannel($channelstring)
	 {
		$channels=array("animalplanet.discovery.no","hd.animalplanet.discovery.no","science.discovery.no","discovery.no","hd.discovery.no","xd.disneychannel.no","disneychannel.no","nrk1.nrk.no","nrk2.nrk.no","nrk3.nrk.no","nrk3.nrk.no","nrksuper.nrk.no","tv2.no","tvnorge.no");
		$channellist['Discovery']='discovery.no';
		$channellist['Discovery (N)']='discovery.no';
		$channellist['Discovery HD']='hd.discovery.no';
		$channellist['Discov Science']='science.discovery.no';
		$channellist['Animal Planet']='animalplanet.discovery.no';
		$channellist['Animal Planet HD']='hd.animalplanet.discovery.no';
	
		$channellist['NRK1 HD']='nrk1.nrk.no';
		$channellist['NRK2 HD']='nrk2.nrk.no';
		$channellist['NRK2']='nrk2.nrk.no';
		$channellist['NRK Super _ NRK3']='nrk3super.nrk.no';
		$channellist['NRK Super _ NRK3 HD']='nrk3super.nrk.no';
		$channellist['Disney Channel']='disneychannel.no';
		$channellist['Disney XD']='xd.disneychannel.no';
		$channellist['TV Norge']='tvnorge.no';
		$channellist['TV Norge HD']='tvnorge.no';
		$channellist['TV 2 HD']='tv2.no';
		$channellist['TV 2 (N)']='tv2.no';
		$channellist['Nat Geo Channel']='natgeo.no'; 
		$channellist['Nat Geo HD']='natgeo.no'; 
		$channellist['FOX']='fox.no';
		$channellist['MAX HD']='max.no';
		$channellist['Cartoon Network']='cartoonnetwork.no';
		$channellist['Boomerang']='boomerangtv.no';
		if(isset($channellist[$channelstring]))
			$return=$channellist[$channelstring];
		else
		{
			$this->error.="Finner ingen kanal for $channelstring<br />\n";
			$return=false;
		}
		return $return;
	 }
	 public function parsefilename($input)
	 {
		if(!preg_match('^([0-9 ]+) - (.*) - (.*)\.ts^U',$input,$result))
		{
			//print_r($result);
			return false;
		}
		else
			return array('datetime'=>$result[1],'channel'=>$result[2]);

	 }
	 public function filecheck($file)
	 {
		
		//var_dump(strpos($file,'http://')===false);
		if(strpos($file,'http://')===false)
			return file_exists($file);
		else
		{ //http://stackoverflow.com/questions/10444059/file-exists-returns-false-even-if-file-exist-remote-url
			$file_headers = get_headers($file);
			if($file_headers[0] == 'HTTP/1.1 404 Not Found')
			{
				return false;	
			}
			elseif($file_headers[0] == "HTTP/1.1 200 OK")
			
				return true;
			else
			{
				print_r($file_headers);
				return false;
				
			}
		}
	 }
	public function getchannel($channelstring,$timestamp)
	{
		$channelid=$this->selectchannel($channelstring);
		if($channelid===false)
			return false;	
		else
			return $this->loadxmlfile($channelid,$timestamp);
	}
	public function loadxmlfile($channelid,$timestamp,$returntype='object',$forcesubfolder=false) //Get the xml file for the specified channel and time
	{
		//Return can be object, array or string
		$ymd=date('Y-m-d',$timestamp);
		$basename="$channelid/{$channelid}_$ymd.xml";

		$files['main']=$this->fullpath($channelid,$this->xmltv_subfolder_main,$timestamp,'xml');
		if(isset($this->xmltv_subfolder_alt))
			$files['alt']=$this->fullpath($channelid,$this->xmltv_subfolder_alt,$timestamp,'xml');
		$files['cache']=str_replace($this->xmltvpath,$this->xmltv_cache,$files['main']);

		if($forcesubfolder!==false)
		{
			$checkfiles=array($this->fullpath($channelid,$forcesubfolder,$timestamp,'xml'));
		}
		else
			$checkfiles=array($files['cache'],$files['main'],$files['alt']);
		
		foreach($checkfiles as $path)
		{
			if($this->filecheck($path))
			{
				$xmlstring=file_get_contents($path); //Load the xml file
				if($channelid=='tvnorge.no') //TV Norge need some string manipulation
					$xmlstring=str_replace('&lt;br/&gt;&lt;br/&gt;','',$xmlstring);
				$xml=simplexml_load_string(str_replace('&','&amp;',$xmlstring)); //Parse the file and create a simplexml object
				if(!isset($xml->programme))
				{
					$temperror="Invalid XML file for $channelid $ymd ($path)<br>\n";
					continue; //Invalid file, try next
				}
				else
				{
					if($this->debug)
						$this->error.="Successfully loaded $path<br />\n";
					break; //Valid file found, no need to continue
				}
			}
			else
			{
				$temperror="No XML file found for $channelid $ymd<br />\n";
				continue; //No file found, try next
			}
		}
		if(!isset($xml) || $xml===false) //If we are here without xml data, no valid file was found
		{
			$this->error.=$temperror;
			return false;
		}

		if(strpos($this->xmltvpath,'http://')!==false && isset($files['cache'])) //If the file was fetched from a remote location, it should be cached
		{
			if(!file_exists($cachedir=dirname($files['cache'])))
				mkdir($cachedir,0777,true);
			file_put_contents($files['cache'],$xmlstring);
		}

		if($returntype=='string')
			return $xmlstring; //Return the raw file
		elseif($returntype=='object')
			return $xml; //Return the simplexml object
		elseif($returntype=='array')
			return json_decode(json_encode($xml),true); //Convert the xml object to array
	}

	public function xmltoarray($xml)
	{
		return @json_decode(@json_encode($xml),true); //Gjør om til array
	}
	public function recordinginfo($filename) //Find information about a recorded file
	{
		//list($datetime,$channelstring) = 
		$info=$this->parsefilename($filename);
		$timestamp=strtotime($info['datetime']);

		$channelstring=$info['channel'];
		if(!$channelid=$this->selectchannel($channelstring))
			return false;

		/*$offsettimestamp=$this->offset($timestamp,$channelid);  //Some channels got early programs in yesterdays file

		if($offsettimestamp!=$timestamp && $this->debug)
		{	
			echo "Original: ".date('c',$timestamp)."\n";
			echo "Offset:   ".date('c',$offsettimestamp)."\n";
		}*/
		if(!$xml=$this->loadxmlfile($channelid,$timestamp))
			return false;
		else
		{			
			$generator=(string)$xml->attributes();
			if($generator=='quadepg' && !$xml=$this->loadxmlfile($channelid,$this->offset($timestamp,$channelid),'object','xmltv_quad'))
				return false;

			//var_dump((array)$xml->attributes()["@attributes"]); //
			$xmlprogram=$this->findprogram($timestamp,$xml,array(0,5*60,10*60,15*60,60)); //Se etter programmer som starter om kort tid
			if($xmlprogram===false)
		   		$xmlprogram=$this->findprogram($timestamp,$xml,'now'); //Se hvilket program som gikk på opptakstidspunktet

			return $xmlprogram;
			
		}
	}
	public function findprogram($time,$xml,$offsets=array(300)) //Find a program from a tv-listing
	{
		if(!is_object($xml))
		{
			$this->error="Ugyldig xml<br />\n";	
			return false;
		}
		//Opptak starter 5 min før programmet
		//Sjekk om start-5 er samme som opptak
		if($offsets==='now')
			$now=$time;
		else
			$now=time();

		//$now=strtotime('2013-12-26 10:59');
		foreach($xml->programme as $program)
		{
			$start=strtotime($program->attributes()->start);

			if(!isset($prevprogram) && $time<$start)
			{
				$this->error.="Ingen sending på angitt tidspunkt<br />\n";
				return false;
			}
			//var_dump($offsets);
			if(is_array($offsets))
			{
				foreach($offsets as $offset)
				{
					//echo $start-$offset;
					//echo "==$time\n";
					if($start-$offset==$time)
						return $program;
				}
			}
			elseif($offsets==='now')
			{
				$diff=$now-$start;
				if($this->debug)
				{
					echo "$now-$start=$diff\n";
					echo date('H:i',$now).'-'.date('H:i',$start)."\n";
				}

				if($diff<0)
				{
					if(isset($prevprogram))
						return $prevprogram;
					else
					{
						$this->error.="Matcher første program<br />\n";
						return false;
					}
				}


			}
			
			$prevprogram=$program;
		}
		$this->error.="No program found<br />\n";
		return false;
	}
	public function seasonepisode($program,$string=true)
	{
		foreach($program->{'episode-num'} as $num)
		if(preg_match('^([0-9]+) \. ([0-9]+)/([0-9]+)^',$num,$matches) || preg_match('^([0-9]+) \. ([0-9]+)^',$num,$matches) || preg_match('^([0-9]+)\.([0-9]+)/([0-9]+)^',$num,$matches))
		{
			if($string)
			{
				$season=str_pad($matches[1]+1,2,'0',STR_PAD_LEFT);
				$episode=str_pad($matches[2]+1,2,'0',STR_PAD_LEFT);
				return "S{$season}E$episode";
			}
			else
				return array('season'=>$matches[1]+1,'episode'=>$matches[2]+1);
		}
		elseif(preg_match('^\. ([0-9]+)/([0-9]+) \.^',$num,$matches)) //One shot series
		{
			if($string)
				return "EP".str_pad($matches[1]+1,2,'0',STR_PAD_LEFT);
			else
				return array('season'=>0,'episode'=>$matches[1]+1);
		}
		else
			return false;

	}
	public function eitparser($eitfile,$mode='title')
	{
		if(!file_exists($eitfile))
			return false;
		$eitfile=file_get_contents($eitfile);
		if(($pos=strpos($eitfile,''))===false)
			$pos=strpos($eitfile,'');
		$info['title']=utf8_encode(trim(substr($eitfile,$pos)));
		//die($title);

			if(preg_match('^\(([0-9]+)/s([0-9]+)\)^',$eitfile,$seasonepisode))
			{
				$info['seasonepisode']['season']=$seasonepisode[2];
				$info['seasonepisode']['episode']=$seasonepisode[1];
			}
		if($mode=='array')
			return $info;
		else
			return $info['title'];
	}
	
	public function offset($timestamp,$channel) 
	{
		if(date('Hi',$timestamp)<300 && strpos($channel,'nrk')!==false) //Programmer sendt på NRK mellom midnatt og 03:00 finnes i gårsdagens fil
		{
			/*echo 'Forskyv: '.$input."<br>\n";
			echo '_'.date('Y-m-d',$timestamp).".xml<br>\n";*/
			return $timestamp-86400;
		}
		elseif((date('Hi',$timestamp)<800 && $channel=='natgeo.no')/* ||
			   (date('Hi',$timestamp)<600 && $channel=='tvnorge.no')*/		
		) //Programmer sendt på Nat Geo mellom midnatt og 08:00 finnes i gårsdagens fil
		{
			/*echo 'Forskyv: '.$input."<br>\n";
			echo '_'.date('Y-m-d',$timestamp).".xml<br>\n";*/
			return $timestamp-86400;
		}
		else
			return $timestamp;
	}
}
