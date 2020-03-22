<?php


namespace datagutten\renamevideo;


use datagutten\dreambox\recording_info as dreambox_info;
use datagutten\xmltv\tools\exceptions;
use datagutten\xmltv\tools\parse\parser;
use FileNotFoundException;
use SimpleXMLElement;

class recording_info
{
    /**
     * @var dreambox_info 
     */
    public $dreambox;
    /**
     * @var parser 
     */
    public $xmltv_parser;

	/**
	 * @var array Information sources
	 */
    public $info_sources = array('xml', 'eit');
    
    public $info_fields=array('title','seasonepisode','description', 'start', 'start_timestamp', 'end', 'end_timestamp', 'category', 'sub-title');
    
    public function __construct()
    {
        $this->dreambox = new dreambox_info();
        $this->xmltv_parser = new parser();
    }

	/**
	 * @param string $file Recording file
	 * @return array Standardized array with program info matching other class methods
	 * @throws exceptions\ChannelNotFoundException Channel not found
	 * @throws exceptions\ProgramNotFoundException Program not found
	 * @throws exceptions\InvalidFileNameException File name could not be parsed
	 */
    function file_info_xml($file)
	{
		$program=$this->dreambox->recording_info($file);
		return $this->xml_info($program);
	}

    /**
	 * Get information about a program as a standardized array
     * @param SimpleXMLElement $program Program information
     * @return array Standardized array with program info matching other class methods
	 * list($programinfo['xml'], $start_timestamp, $generator) = $recording_info->file_info_xml($file);
	 */
    function xml_info($program)
    {
        $program_info = [];

        $generator = (string)$program->xpath('/tv/@generator-info-name')[0];
        $program_info['start_timestamp'] = strtotime($program->attributes()->{'start'});
        $program_info['start'] = date('H:i', $program_info['start_timestamp']);
        if(isset($program->title)) //Get the title
            $program_info['title']=(string)$program->title;
        if(isset($program->attributes()->{'stop'}))
        {
            $program_info['end_timestamp'] = strtotime($program->attributes()->{'stop'});
            $program_info['end'] = date('H:i', $program_info['end_timestamp']);
        }

        if(isset($program->category)) //Get the category
        {
            if(!is_array($program->category))
                $program_info['category']=$program->category;
            else
                $program_info['category'] = implode(', ', $program->{'category'});
        }
        if(isset($program->{'sub-title'})) //Get the sub-title
            $program_info['sub-title']=(string)$program->{'sub-title'};
        if(isset($program->desc)) //Get the description
            $program_info['description']=(string)$program->desc;
        if(isset($program->{'episode-num'}) && $this->xmltv_parser->season_episode($program)) //Get the episode-num string and convert it to season and episode
            $program_info['seasonepisode']=$this->xmltv_parser->season_episode($program,false);

        return [$program_info, $program_info['start_timestamp'], $generator];
    }

    /**
	 * Get information from EIT file
     * @param string $file
     * @throws FileNotFoundException EIT file not found
     * @return array Standardized array with program info matching other class methods
     */
    function eit_info($file)
    {
        $extension = pathinfo($file, PATHINFO_EXTENSION);
        if($extension!=='eit')
        {
            $eit_file = str_replace($extension, 'eit', $file);
            if(file_exists($eit_file))
                $file = $eit_file;
            else
                throw new FileNotFoundException('EIT file not found');
        }
        $program_info = dreambox_info::parse_eit($file, 'array');
        $program_info['seasonepisode'] = $program_info['season_episode'];
        if(empty($program_info['description']) && !empty($program_info['short_description']))
            $program_info['description'] = $program_info['short_description'];
        return $program_info;
    }

	/**
	 * Combine information from multiple sources to get complete program information
	 * @param array $program_info
	 * @return array Combined info
	 */
    function combine_info($program_info)
    {
        $programinfo_final = [];
        foreach($this->info_fields as $field) //Decide which information source to use
        {
            foreach($this->info_sources as $source)
            {
                if(!empty($program_info[$source][$field]))
                {
                    $programinfo_final[$field]=$program_info[$source][$field];
                    continue 2;
                }
            }
        }

        if(!empty($program_info['eit']['season_episode']) && $programinfo_final['seasonepisode']['season']==0) //Check if eit has more correct season than other sources
            $programinfo_final['seasonepisode']['season']=$program_info['eit']['season_episode']['season'];
        return $programinfo_final;
    }

	/**
	 * @param $file
	 * @return array
	 * @throws exceptions\InvalidFileNameException
	 */
    function get_info($file)
    {
        try {
            $program_info['eit'] = $this->eit_info($file);
        }
        catch (FileNotFoundException $e)
        {
            $program_info['eit'] = [];
        }
        try {
            $program_info['xml'] = $this->file_info_xml($file)[0];
        }
        catch (exceptions\ChannelNotFoundException | exceptions\ProgramNotFoundException $e)
        {
            $program_info['xml'] = [];
        }
        //print_r($program_info);
        $program_info = $this->combine_info($program_info);
        $recording = dreambox_info::parse_file_name($file);
        $program_info['recording_start'] = strtotime($recording['datetime']);

        return $program_info;
    }
}