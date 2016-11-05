<?php
//$data=file_get_contents('/mnt/video/input/20140109 0525 - TV Norge HD - Grensevakten.eit');
//$data=file_get_contents('/mnt/video/Phineas og Ferb/20130619 0315 - Disney Channel - Phineas og Ferb.eit');
class eitparser
{
	public function parse($file)
	{
		$data=file_get_contents($file);
		if(strlen($data)>1000)
			return false;
		$data=str_replace('(R)','',$data);
		$titleref=strpos($data,chr(0x4D));
		
		$title_start=$this->non_printable_search($data,$titleref,$titleref+7)+1;
		
		if($pos=strpos($data,'('))
			$title_end=$pos-1;
		else
			$title_end=$this->non_printable_search($data,$title_start,strlen($data))-2;
		
		$lang=substr($data,$title_start-4,3);
		$control=range(chr(0x00),chr(0x1F));
		if(preg_match('^\((.+)\)^',$data,$seasonepisode))
		{
			$info['raw_seasonepisodestring']=$seasonepisode[1];
			if(is_numeric($seasonepisode[1]))
			{
				$info['seasonepisode']['season']=0;
				$info['seasonepisode']['episode']=$seasonepisode[1];
			}
			elseif(preg_match('^\(([0-9]+):*[0-9]*/s([0-9]+)\)^',$data,$seasonepisode))
			{
				$info['seasonepisode']['season']=$seasonepisode[2];
				$info['seasonepisode']['episode']=$seasonepisode[1];
			}
			
			
		}
		//echo "Start: $title_start End: $title_end\n";
		if(($pos=strpos($data,')'))!==false)
			$description_start=$pos+2;
		else
			$description_start=strpos($data,chr(0x05),$title_end+2)+1;
		$info['title']=utf8_encode(substr($data,$title_start,$title_end-$title_start));	
		$description=substr($data,$description_start);
		$description=str_replace($control,'',$description);
		$info['description']=utf8_encode($description);
		
		return $info;
	}
	private function is_printable($char)
	{
		if(ord($char)<0x20 || (ord($char)>0x7E && ord($char)<0xA0))
			return false;
		else
			return true;
	}
	private function non_printable_search($string,$from,$to,$debug=false)
	{
		for($pos=$from; $pos<$to; $pos++)
		{
			
			if(!$this->is_printable($char=substr($string,$pos,1)))
			{
				if($debug)
					echo "Not printable: $pos: ".dechex(ord($char))." ($char)\n";
				return $pos;
			}
			elseif($debug)
				echo "Printable: $pos: ".dechex(ord($char))." ($char)\n";
		}
	}
}