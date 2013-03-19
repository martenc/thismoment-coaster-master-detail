<?php

require_once "src/Coaster.php";
define('PUBLIC_API_KEY', 'xxxxxxxx'); // use your api keys here
define('PRIVATE_API_KEY', 'xxxxxxxx');


/**
 * Series
 * 
 */
class Series {

    private $coaster;
    private $signal_site = 'your_dec_name';
    private $environment = '1';
    private $showall = '1';
    private $series;

    public function __construct()
    {
        $this->coaster = new Coaster($this->signal_site, PUBLIC_API_KEY, PRIVATE_API_KEY);
        $this->coaster->exit_on_error = true;
    }

    /**
     * printSeries (one-to-many: Series to Matchups)
     * @return null
     */
    public function printSeries()
    {
        echo json_encode($this->series);
    }

    /**
     * getSeries (Master Gallery)
     * 
     * @param  int $series_id   The gallery ID for the series
     * @return null
     */
    public function getSeries($series_id=null)
    {
        if (!$series_id || !is_int($series_id)) {
            die('Invalid gallery id provided');
        }

        // get series data
        $this->series = $this->tmCall($series_id);

        if (!isset($this->series[0])) {
            die('Unexpected results.');
        }

        $matchupListField = $this->series[0]['MatchupGalleryIDs'];

        if (!empty($matchupListField)) {

            $matchupList = split(',', $matchupListField);

            $this->series['matchups'] = $this->getMatchups($matchupList);
        }
    }

    /**
     * getMatchups (Detail Galleries)
     *
     * @param  array $matchuplist  list of matchup id's
     * @return array $matchups     array of matchups data
     */
    private function getMatchups($matchupList=array())
    {
        $matchups = array();
        
        foreach ($matchupList as $matchup_id) {

            // use this to clear white-space of integer
            $matchup_id = (int)$matchup_id;

            // get matchup data
            $match = $this->tmCall($matchup_id);

            $characters = array();

            foreach ($match as $key => $val) {
                // if data is character
                // this may need to change at a later point
                if ($key===0 || $key===1) {
                    // save character
                    $characters[strtolower($val['title'])] = $val;
                }
            }

            // TODO: perhaps get fbid from session?
            $matchups['match'.$matchup_id] = array("user_vote"=>'0', "characters"=>$characters); 

        }

        // save matchups
        return $matchups;

    }

    /**
     * tmCall  Wrapper for This Moment Coaster API call
     * @param  int   $gallery_id
     * @return array $data        array returned from coaster api
     */
    private function tmCall($gallery_id)
    {
        try {
            $data =  $this->coaster->call('/gallery/get', array(
                'gallery_id' => $gallery_id,
                'environment' => $this->environment,
                'showall' => $this->showall
            ));
        }
        catch( TM_Client_Exception $e ) {
            die("Results: FAILED");
        }

        return $data;
    }
}

// Instantiate

$series = new Series();
$series->getSeries((int)$_REQUEST['gal']);
$series->printSeries();


?>


