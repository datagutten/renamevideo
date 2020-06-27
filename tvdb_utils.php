<?php


use datagutten\tvdb;

class tvdb_utils extends tvdb\tvdb_cache
{
    public $mappings = array();
    public $bad_searches = [];

    public function __construct($config)
    {
        parent::__construct($config);
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
     * @param $search_strings
     * @param null $language
     * @return array Series info
     * @throws tvdb\exceptions\api_error Error from TVDB api
     * @throws tvdb\exceptions\noResultException No search hits
     */
    public function series_search_helper($search_strings, $language = null)
    {
        foreach($search_strings as $search)
        {
            if(array_search($search,$this->bad_searches)!==false) //Do not retry searches that have failed previously
                continue;
            $mapped_id = $this->lookup_mapping($search); //Check if there is a mapping between series name and tvdb id
            if(!empty($mapped_id))
                return $this->getseries($mapped_id, $language);

            else //Not found by lookup, search for name on TVDB
            {
                try {
                    return $this->series_search($search, $language);
                }
                catch (tvdb\exceptions\noResultException $e)
                {
                    $this->bad_searches[] = $search;
                    continue;
                }
            }
        }
        //If we are here the search has not matched
        throw new tvdb\exceptions\noResultException(sprintf('No hits for searches: %s', implode("\n", $search_strings)));
    }
}