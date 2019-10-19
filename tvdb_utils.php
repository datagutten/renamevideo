<?php


class tvdb_utils extends tvdb
{
    public $mappings = array();
    public $series_cache = array();
    public function __construct()
    {
        parent::__construct();
        if(file_exists(__DIR__.'/tvdb_mappings.php'))
            $this->mappings = require __DIR__.'/tvdb_mappings.php';
    }

    public static function generate_search_strings($title)
    {
        return array(preg_replace('/(.+):.+/','$1',$title),$title); //Search for complete title and title cut at :
    }

    /**
     * @param $search
     * @return false|int
     */
    public function lookup_mapping($search)
    {
        return array_search($search,$this->mappings);
    }

    /**
     * Get series and episodes for a series ID
     * @param int $series_id Series ID
     * @param string $language Language
     * @return array
     * @throws Requests_Exception
     */
    public function get_series_and_episodes($series_id, $language = null)
    {
        if(isset($this->series_cache[$series_id][$language]))
            return $this->series_cache[$series_id][$language];
        else
        {
            $series = parent::get_series_and_episodes($series_id, $language);
            $this->series_cache[$series_id][$language] = $series;
            return $series;
        }
    }

    public function series_search_helper($search_strings, $language = null)
    {
        foreach($search_strings as $search)
        {
            $this->error='';
            if(isset($tvdb_nomatch) && array_search($search,$tvdb_nomatch)!==false) //Do not retry searches that have failed previously
                continue;
            $mapped_id = $this->lookup_mapping($search); //Check if there is a mapping between series name and tvdb id
            if(!empty($mapped_id))
            {
                $tvdb_series=$this->get_series_and_episodes($mapped_id); //Fetch series
                break;
            }
            else //Not found by lookup, search for name on TVDB
            {
                //echo "Search for series\n";
                try {
                    $tvdb_series_search = $this->series_search($search);
                    if(!empty($tvdb_series_search))
                    {
                        if(empty($language))
                            $lang=$this->last_search_language;
                        else
                            $lang=$language;
                        $tvdb_series=$this->get_series_and_episodes($tvdb_series_search['id'],$lang);

                        $this->mappings[$tvdb_series['Series']['id']]=$search; //Add id to mappings to avoid search next time
                        break;
                    }
                }
                catch (Requests_Exception $e)
                {
                    $tvdb_series_search = null;
                }
            }
            //If we are here the search has not matched
            $tvdb_nomatch[]=$search;
        }
        if(!empty($tvdb_series))
            return $tvdb_series;
        else
            return null;
    }
}