<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * text analysis for solo plugin
 *
 * @package    mod_solo
 * @copyright  2015 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 namespace mod_solo;

defined('MOODLE_INTERNAL') || die();


/**
 * Functions used for producing a textanalysis
 *
 * @package    mod_solo
 * @copyright  2015 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class textanalysis {

    const CLOUDPOODLL = 'https://cloud.poodll.com';
    //const CLOUDPOODLL = 'https://vbox.poodll.com/cphost';



    /** @var string $token The cloudpoodll token. */
    protected $token;

    /** @var string $region The aws region. */
    protected $region;

    /** @var string $passage The aws region. */
    protected $passage;

    /** @var string $language The target language. */
    protected $language;

    /** @var string $userlanguage The users L1 language. */
    protected $userlanguage;

        /**
         * The class constructor.
         *
         */
    public function __construct($token, $region, $passage, $language, $userlanguage=false){
        $this->token = $token;
        $this->region = $region;
        $this->passage = $passage;
        $this->language = $language;
        $this->userlanguage = $userlanguage;
    }

    //fetch lang server url, services incl. 'transcribe' , 'lm', 'lt', 'spellcheck'
    public function fetch_lang_server_url($service='transcribe'){
        switch($this->region) {
            case 'useast1':
                $ret = 'https://useast.ls.poodll.com/';
                break;
            default:
                $ret = 'https://' . $this->region . '.ls.poodll.com/';
        }
        return $ret . $service;
    }


    public function fetch_sentence_stats($passage){

        //count sentences
        $items = preg_split('/[!?.]+(?![0-9])/', $passage);
        $items = array_filter($items);
        $sentencecount = count($items);

        //longest sentence length
        //average sentence length
        $longestsentence=1;
        $averagesentence=1;
        $totallengths = 0;
        foreach($items as $sentence){
            $length = self::mb_count_words($sentence,$this->language);
            if($length>$longestsentence){
                $longestsentence =$length;
            }
            $totallengths+=$length;
        }
        if($totallengths>0 && $sentencecount>0){
            $averagesentence=round($totallengths / $sentencecount);
        }

        //return values
        return ['sentencetotal'=>$sentencecount,'sentenceaverage'=>$averagesentence,'sentencelongest'=>$longestsentence];
    }

    public function is_english(){
        $ret = strpos($this->language,'en')===0;
        return $ret;
    }

    public function fetch_word_stats($passage) {

        //prepare data
        $is_english=$this->is_english();
        $items = \core_text::strtolower($passage);
        $items = $this->mb_count_words($items,1);
        $totalwords = count($items);
        $items = array_unique($items);

        //unique words
        $uniquewords = count($items);

        //long words
        $longwords = 0;
        foreach ($items as $item) {
            if($is_english) {
                if (self::count_syllables($item) > 2) {
                    $longwords++;
                }
            }else{
                if (\core_text::strlen($item) > 5) {
                    $longwords++;
                }
            }
        }

        //return results
        return ['wordstotal'=>$totalwords,'wordsunique'=>$uniquewords,'wordslong'=>$longwords];
    }

    /*
     * count words,
     * return number of words for format 0
     * return words array for format 1
     */
    public function mb_count_words($string,  $format=0)
    {

        //wordcount will be different for different languages
        switch($this->language){
            //arabic
            case constants::M_LANG_ARAE:
            case constants::M_LANG_ARSA:
                //remove double spaces and count spaces remaining to estimate words
                $string= preg_replace('!\s+!', ' ', $string);
                switch($format){

                    case 1:
                        $ret = explode(' ', $string);
                        break;
                    case 0:
                    default:
                        $ret = substr_count($string, ' ') + 1;
                }

                break;
            //others
            default:
                $words = diff::fetchWordArray($string);
                $wordcount = count($words);
                //$wordcount = str_word_count($string,$format);
                switch($format){

                    case 1:
                        $ret = $words;
                        break;
                    case 0:
                    default:
                       $ret = $wordcount;
                }

        }

        return $ret;
    }

    /**
     * count_syllables
     *
     * based on: https://github.com/e-rasvet/sassessment/blob/master/lib.php
     */
    public function count_syllables($word) {
        // https://github.com/vanderlee/phpSyllable (multilang)
        // https://github.com/DaveChild/Text-Statistics (English only)
        // https://pear.php.net/manual/en/package.text.text-statistics.intro.php
        // https://pear.php.net/package/Text_Statistics/docs/latest/__filesource/fsource_Text_Statistics__Text_Statistics-1.0.1TextWord.php.html
        $str = strtoupper($word);
        $oldlen = strlen($str);
        if ($oldlen < 2) {
            $count = 1;
        } else {
            $count = 0;

            // detect syllables for double-vowels
            $vowels = array('AA','AE','AI','AO','AU',
                    'EA','EE','EI','EO','EU',
                    'IA','IE','II','IO','IU',
                    'OA','OE','OI','OO','OU',
                    'UA','UE','UI','UO','UU');
            $str = str_replace($vowels, '', $str);
            $newlen = strlen($str);
            $count += (($oldlen - $newlen) / 2);

            // detect syllables for single-vowels
            $vowels = array('A','E','I','O','U');
            $str = str_replace($vowels, '', $str);
            $oldlen = $newlen;
            $newlen = strlen($str);
            $count += ($oldlen - $newlen);

            // adjust count for special last char
            switch (substr($str, -1)) {
                case 'E': $count--; break;
                case 'Y': $count++; break;
            };
        }
        return $count;
    }


    public static function fetch_targetwords($attempt){
        $targetwords = explode(PHP_EOL,$attempt->topictargetwords);
        $mywords = explode(PHP_EOL,$attempt->mywords);
        //Do new lines and commas
        $allwords = array_merge($targetwords, $mywords);
        //remove any duplicates or blanks
        $alltargetwords = array_filter(array_unique($allwords));

        return $alltargetwords;
    }



    public function process_all_stats($targetwords=[]){


            $stats = $this->calculate_stats($this->passage,$targetwords);
            if ($stats) {
                $stats->ideacount = $this->process_idea_count($this->passage);
                $stats->cefrlevel = $this->process_cefr_level($this->passage);
                $stats->relevance = $this->process_relevance($this->passage);
                $stats = array_merge($stats,$this->fetch_sentence_stats($this->passage));
                $stats = array_merge($stats,$this->fetch_word_stats($this->passage));
                $stats = array_merge($stats,$this->calc_grammarspell_stats($this->passage,$stats->wordtotal));
            }
            return $stats;
    }

    public function process_grammar_correction($passage){

        $ret=['gcorrections'=>false,'gcerrors'=>false,'gcmatches'=>false,'gcerrorcount'=>false];
        //If this is English then lets see if we can get a grammar correction
       // if(!empty($attempt->selftranscript) && self::is_english($moduleinstance)){
        if(!empty($passage)){
                $grammarcorrection = self::fetch_grammar_correction($passage);
                if ($grammarcorrection) {
                    $ret['gcorrections']=$grammarcorrection;

                    //fetch and set GC Diffs
                    list($gcerrors,$gcmatches,$gcinsertioncount) = $this->fetch_grammar_correction_diff($passage, $grammarcorrection);
                    if(self::is_json($gcerrors)&& self::is_json($gcmatches)) {
                        $ret['gcerrors'] = json_decode($gcerrors);
                        $ret['gcmatches'] = json_decode($gcmatches);
                        $ret['gcerrorcount']=count(get_object_vars($ret['gcerrors'])) +$gcinsertioncount;
                    }
                }

        }
        return $ret;
    }

    public function process_relevance($passage,$targetembedding=false){
        global $DB;

        $relevance=false;//default is blank
        if(!empty($passage)&&$targetembedding!==false){
            $relevance = $this->fetch_relevance($passage,$targetembedding);
        }
        if ($relevance!==false) {
            return $relevance;
        }else{
            return 0;
        }
    }

    public function process_cefr_level($passage){

        $cefrlevel=false;//default is blank
        if(!empty($passage)){
            $cefrlevel = $this->fetch_cefr_level($passage);
        }
        if ($cefrlevel!==false) {
            return $cefrlevel;
        }else{
            return "";
        }
    }

    public function process_idea_count($passage){

        $ideacount=false;
        if(!empty($passage)){
            $ideacount = $this->fetch_idea_count($passage);
        }
        if ($ideacount!==false) {
            return $ideacount;
        }else{
            return 0;
        }

    }

    public static function fetch_grammar_correction_diff($selftranscript,$correction){


        //turn the passage and transcript into an array of words
        $alternatives = diff::fetchAlternativesArray('');
        $wildcards = diff::fetchWildcardsArray($alternatives);
        $passagebits = diff::fetchWordArray($selftranscript);
        $transcriptbits = diff::fetchWordArray($correction);


        //fetch sequences of transcript/passage matched words
        // then prepare an array of "differences"
        $passagecount = count($passagebits);
        $transcriptcount = count($transcriptbits);
        //rough estimate of insertions
        $insertioncount = $transcriptcount - $passagecount;
        if($insertioncount<0){$insertioncount=0;}

        $language = constants::M_LANG_ENUS;
        $sequences = diff::fetchSequences($passagebits,$transcriptbits,$alternatives,$language);

        //fetch diffs
        $diffs = diff::fetchDiffs($sequences, $passagecount,$transcriptcount);
        $diffs = diff::applyWildcards($diffs,$passagebits,$wildcards);


        //from the array of differences build error data, match data, markers, scores and metrics
        $errors = new \stdClass();
        $matches = new \stdClass();
        $currentword=0;
        $lastunmodified=0;
        //loop through diffs
        foreach($diffs as $diff){
            $currentword++;
            switch($diff[0]){
                case Diff::UNMATCHED:
                    //we collect error info so we can count and display them on passage
                    $error = new \stdClass();
                    $error->word=$passagebits[$currentword-1];
                    $error->wordnumber=$currentword;
                    $errors->{$currentword}=$error;
                    break;

                case Diff::MATCHED:
                    //we collect match info so we can play audio from selected word
                    $match = new \stdClass();
                    $match->word=$passagebits[$currentword-1];
                    $match->pposition=$currentword;
                    $match->tposition = $diff[1];
                    $match->audiostart=0;//not meaningful when processing corrections
                    $match->audioend=0;//not meaningful when processing corrections
                    $match->altmatch=$diff[2];//not meaningful when processing corrections
                    $matches->{$currentword}=$match;
                    $lastunmodified = $currentword;
                    break;

                default:
                    //do nothing
                    //should never get here

            }
        }
        $sessionendword = $lastunmodified;

        //discard errors that happen after session end word.
        $errorcount = 0;
        $finalerrors = new \stdClass();
        foreach($errors as $key=>$error) {
            if ($key < $sessionendword) {
                $finalerrors->{$key} = $error;
                $errorcount++;
            }
        }
        //finalise and serialise session errors
        $sessionerrors = json_encode($finalerrors);
        $sessionmatches = json_encode($matches);

        return [$sessionerrors,$sessionmatches,$insertioncount];

    }


    //we leave it up to the grading logic how/if it adds the ai grades to gradebook
    public function calc_grammarspell_stats($passage,$wordcount){
        //init stats with defaults
        $stats= new \stdClass();
        $stats->autospell="";
        $stats->autogrammar="";
        $stats->autospellscore=100;
        $stats->autogrammarscore=100;
        $stats->autospellerrors = 0;
        $stats->autogrammarerrors=0;


        //if we have no words for whatever reason the calc will not work
        if(!$wordcount || $wordcount<1) {
            //update spelling and grammar stats in DB
            return $stats;
        }

        //get lanserver lang string
        switch($this->language){
            case constants::M_LANG_ARSA:
            case constants::M_LANG_ARAE:
                $targetlanguage = 'ar';
                break;
            default:
                $targetlanguage = $this->language;
        }

        //fetch grammar stats
        $lt_url = utils::fetch_lang_server_url($this->region,'lt');
        $postdata =array('text'=> $passage,'language'=>$targetlanguage);
        $autogrammar = utils::curl_fetch($lt_url,$postdata,'post');
        //default grammar score
        $autogrammarscore =100;

        //fetch spell stats
        $spellcheck_url = utils::fetch_lang_server_url($this->region,'spellcheck');
        $spelltranscript = diff::cleanText($passage);
        $postdata =array('passage'=>$spelltranscript,'lang'=>$targetlanguage);
        $autospell = utils::curl_fetch($spellcheck_url,$postdata,'post');
        //default spell score
        $autospellscore =100;



        //calc grammar score
        if(self::is_json($autogrammar)) {
            //work out grammar
            $grammarobj = json_decode($autogrammar);
            $incorrect = count($grammarobj->matches);
            $stats->autogrammarerrors= $incorrect;
            $raw = $wordcount - ($incorrect * 3);
            if ($raw < 1) {
                $autogrammarscore = 0;
            } else {
                $autogrammarscore = round($raw / $wordcount, 2) * 100;
            }

            $stats->autogrammar = $autogrammar;
            $stats->autogrammarscore = $autogrammarscore;
        }

        //calculate spell score
        if(self::is_json($autospell)) {

            //work out spelling
            $spellobj = json_decode($autospell);
            $correct = 0;
            if($spellobj->status) {
                $spellarray = $spellobj->data->results;
                foreach ($spellarray as $val) {
                    if ($val) {
                        $correct++;
                    }else{
                        $stats->autospellerrors++;
                    }
                }

                if ($correct > 0) {
                    $autospellscore = round($correct / $wordcount, 2) * 100;
                } else {
                    $autospellscore = 0;
                }
            }
        }

        //update spelling and grammar stats in data object and return
        $stats->autospell=$autospell;
        $stats->autogrammar=$autogrammar;
        $stats->autospellscore=$autospellscore;
        $stats->autogrammarscore=$autogrammarscore;
        return get_object_vars($stats);
    }



    //calculate stats of transcript (no db code)
    public function calculate_stats($passage,$targetwords){
        $stats= new \stdClass();
        $stats->turns=0;
        $stats->words=0;
        $stats->avturn=0;
        $stats->longestturn=0;
        $stats->targetwords=0;
        $stats->totaltargetwords=0;
        $stats->aiaccuracy=-1;

        if(!$passage || empty($passage)){
            return $stats;
        }

        $items = preg_split('/[!?.]+(?![0-9])/', $passage);
        $transcriptarray = array_filter($items);
        $totalturnlengths=0;
        $jsontranscript = '';

        foreach($transcriptarray as $sentence){
            //wordcount will be different for different languages
            $wordcount = self::mb_count_words($sentence,$this->language);

            if($wordcount===0){continue;}
            $jsontranscript .= $sentence . ' ' ;
            $stats->turns++;
            $stats->words+= $wordcount;
            $totalturnlengths += $wordcount;
            if($stats->longestturn < $wordcount){$stats->longestturn = $wordcount;}
        }
        if(!$stats->turns){
            return false;
        }
        $stats->avturn= round($totalturnlengths  / $stats->turns);
        $stats->totaltargetwords = count($targetwords);


        $searchpassage = strtolower($jsontranscript);
        foreach($targetwords as $theword){
            $searchword = self::cleanText($theword);
            if(empty($searchword) || empty($searchpassage)){
                $usecount=0;
            }else {
                $usecount = substr_count($searchpassage, $searchword);
            }
            if($usecount){$stats->targetwords++;}
        }
        return get_object_vars($stats);
    }

    /*
   * Clean word of things that might prevent a match
    * i) lowercase it
    * ii) remove html characters
    * iii) replace any line ends with spaces (so we can "split" later)
    * iv) remove punctuation
   *
   */
    public static function cleanText($thetext){
        //lowercaseify
        $thetext=strtolower($thetext);

        //remove any html
        $thetext = strip_tags($thetext);

        //replace all line ends with empty strings
        $thetext = preg_replace('#\R+#', '', $thetext);

        //remove punctuation
        //see https://stackoverflow.com/questions/5233734/how-to-strip-punctuation-in-php
        // $thetext = preg_replace("#[[:punct:]]#", "", $thetext);
        //https://stackoverflow.com/questions/5689918/php-strip-punctuation
        $thetext = preg_replace("/[[:punct:]]+/", "", $thetext);

        //remove bad chars
        $b_open="“";
        $b_close="”";
        $b_sopen='‘';
        $b_sclose='’';
        $bads= array($b_open,$b_close,$b_sopen,$b_sclose);
        foreach($bads as $bad){
            $thetext=str_replace($bad,'',$thetext);
        }

        //remove double spaces
        //split on spaces into words
        $textbits = explode(' ',$thetext);
        //remove any empty elements
        $textbits = array_filter($textbits, function($value) { return $value !== ''; });
        $thetext = implode(' ',$textbits);
        return $thetext;
    }

    //we use curl to fetch transcripts from AWS and Tokens from cloudpoodll
    //this is our helper
    //we use curl to fetch transcripts from AWS and Tokens from cloudpoodll
    //this is our helper
    public static function curl_fetch($url,$postdata=false, $method='get')
    {
        global $CFG;

        require_once($CFG->libdir.'/filelib.php');
        $curl = new \curl();

        if($method=='post') {
            $result = $curl->post($url, $postdata);
        }else{
            $result = $curl->get($url, $postdata);
        }
        return $result;
    }


    public static function fetch_spellingerrors($stats, $transcript) {
        $spellingerrors=array();
        $usetranscript = diff::cleanText($transcript);
        //sanity check
        if(empty($usetranscript) ||!self::is_json($stats->autospell)){
            return $spellingerrors;
        }

        //return errors
        $spellobj = json_decode($stats->autospell);
        if($spellobj->status) {
            $spellarray = $spellobj->data->results;
            $wordarray = explode(' ', $usetranscript);
            for($index=0;$index<count($spellarray); $index++) {
                if (!$spellarray[$index]) {
                    $spellingerrors[]=$wordarray[$index];
                }
            }
        }
        return $spellingerrors;

    }
    public static function fetch_grammarerrors($stats, $transcript) {
        $usetranscript = diff::cleanText($transcript);
        //sanity check
        if(empty($usetranscript) ||!self::is_json($stats->autogrammar)){
            return [];
        }

        //return errors
        $grammarobj = json_decode($stats->autogrammar);
        return $grammarobj->matches;

    }

    //fetch the grammar correction suggestions
    public function fetch_grammar_correction($passage) {
        global $USER;

        //The REST API we are calling
        $functionname = 'local_cpapi_call_ai';

        $params = array();
        $params['wstoken'] = $this->token;
        $params['wsfunction'] = $functionname;
        $params['moodlewsrestformat'] = 'json';
        $params['action'] = 'request_grammar_correction';
        $params['appid'] = 'mod_solo';
        $params['prompt'] = $passage;//urlencode($passage);
        $params['language'] = $this->language;
        $params['subject'] = 'none';
        $params['region'] = $this->region;
        $params['owner'] = hash('md5',$USER->username);

        //log.debug(params);

        $serverurl = self::CLOUDPOODLL . '/webservice/rest/server.php';
        $response = self::curl_fetch($serverurl, $params);
        if (!self::is_json($response)) {
            return false;
        }
        $payloadobject = json_decode($response);

        //returnCode > 0  indicates an error
        if (!isset($payloadobject->returnCode) || $payloadobject->returnCode > 0) {
            return false;
            //if all good, then lets do the embed
        } else if ($payloadobject->returnCode === 0) {
            $correction = $payloadobject->returnMessage;
            //clean up the correction a little
            if(\core_text::strlen($correction) > 0){
                $correction = \core_text::trim_utf8_bom($correction);
                $charone = substr($correction,0,1);
                if(preg_match('/^[.,:!?;-]/',$charone)){
                    $correction = substr($correction,1);
                }
            }

            return $correction;
        } else {
            return false;
        }
    }

    //fetch the relevance
    public  function fetch_relevance($passage,$model_or_modelembedding=false) {
        global $USER;

        //default to 100% relevant if no TTS model or if it's not English
        if(!$this->is_english() || $model_or_modelembedding===false){
            return 1;
        }

        //The REST API we are calling
        $functionname = 'local_cpapi_call_ai';

        $params = array();
        $params['wstoken'] = $this->token;
        $params['wsfunction'] = $functionname;
        $params['moodlewsrestformat'] = 'json';
        $params['action'] = 'get_semantic_sim';
        $params['appid'] = 'mod_solo';
        $params['prompt'] = $passage;//urlencode($passage);
        $params['subject'] = $model_or_modelembedding;
        $params['language'] = $this->language;
        $params['region'] = $this->region;
        $params['owner'] = hash('md5',$USER->username);

        //log.debug(params);

        $serverurl = self::CLOUDPOODLL . '/webservice/rest/server.php';
        $response = self::curl_fetch($serverurl, $params,'post');
        if (!self::is_json($response)) {
            return false;
        }
        $payloadobject = json_decode($response);

        //returnCode > 0  indicates an error
        if (!isset($payloadobject->returnCode) || $payloadobject->returnCode > 0) {
            return false;
            //if all good, then return the value
        } else if ($payloadobject->returnCode === 0) {
            $relevance = $payloadobject->returnMessage;
            if(is_numeric($relevance)){
                $relevance=(int)round($relevance * 100,0);
            }else{
                $relevance = false;
            }
            return $relevance;
        } else {
            return false;
        }
    }

    //fetch the CEFR Level
    public function fetch_cefr_level($passage) {
        global $USER;

        //The REST API we are calling
        $functionname = 'local_cpapi_call_ai';

        $params = array();
        $params['wstoken'] = $this->token;
        $params['wsfunction'] = $functionname;
        $params['moodlewsrestformat'] = 'json';
        $params['action'] = 'predict_cefr';
        $params['appid'] = 'mod_solo';
        $params['prompt'] = $passage;//urlencode($passage);
        $params['language'] = $this->language;
        $params['subject'] = 'none';
        $params['region'] = $this->region;
        $params['owner'] = hash('md5',$USER->username);

        //log.debug(params);

        $serverurl = self::CLOUDPOODLL . '/webservice/rest/server.php';
        $response = self::curl_fetch($serverurl, $params);
        if (!self::is_json($response)) {
            return false;
        }
        $payloadobject = json_decode($response);

        //returnCode > 0  indicates an error
        if (!isset($payloadobject->returnCode) || $payloadobject->returnCode > 0) {
            return false;
            //if all good, then return the value
        } else if ($payloadobject->returnCode === 0) {
            $cefr = $payloadobject->returnMessage;
            //make pretty sure its a CEFR level
            if(\core_text::strlen($cefr) !== 2){
                $cefr=false;
            }

            return $cefr;
        } else {
            return false;
        }
    }

    //fetch embedding
    public function fetch_embedding($thetextpassage) {
        global $USER;

        //The REST API we are calling
        $functionname = 'local_cpapi_call_ai';

        $params = array();
        $params['wstoken'] = $this->token;
        $params['wsfunction'] = $functionname;
        $params['moodlewsrestformat'] = 'json';
        $params['action'] = 'get_embedding';
        $params['appid'] = 'mod_solo';
        $params['prompt'] = $thetextpassage;//urlencode($passage);
        $params['language'] = $this->language;
        $params['subject'] = 'none';
        $params['region'] = $this->region;
        $params['owner'] = hash('md5',$USER->username);

        //log.debug(params);

        $serverurl = self::CLOUDPOODLL . '/webservice/rest/server.php';
        $response = self::curl_fetch($serverurl, $params);
        if (!self::is_json($response)) {
            return false;
        }
        $payloadobject = json_decode($response);

        //returnCode > 0  indicates an error
        if (!isset($payloadobject->returnCode) || $payloadobject->returnCode > 0) {
            return false;
            //if all good, then process  it
        } else if ($payloadobject->returnCode === 0) {
            $return_data = $payloadobject->returnMessage;
            //clean up the correction a little
            if(!self::is_json($return_data)){
                $embedding=false;
            }else{
                $data_object = json_decode($return_data);
                if(is_array($data_object)&&$data_object[0]->object=='embedding') {
                    $embedding = json_encode($data_object[0]->embedding);
                }else{
                    $embedding=false;
                }
            }
            return $embedding;
        } else {
            return false;
        }
    }

    //fetch the Idea Count
    public function fetch_idea_count($passage) {
        global $USER;

        //The REST API we are calling
        $functionname = 'local_cpapi_call_ai';

        $params = array();
        $params['wstoken'] = $this->token;
        $params['wsfunction'] = $functionname;
        $params['moodlewsrestformat'] = 'json';
        $params['action'] = 'count_unique_ideas';
        $params['appid'] = 'mod_solo';
        $params['prompt'] = $passage;//urlencode($passage);
        $params['language'] = $this->language;
        $params['subject'] = 'none';
        $params['region'] = $this->region;
        $params['owner'] = hash('md5',$USER->username);

        //log.debug(params);

        $serverurl = self::CLOUDPOODLL . '/webservice/rest/server.php';
        $response = self::curl_fetch($serverurl, $params);
        if (!self::is_json($response)) {
            return false;
        }
        $payloadobject = json_decode($response);

        //returnCode > 0  indicates an error
        if (!isset($payloadobject->returnCode) || $payloadobject->returnCode > 0) {
            return false;
            //if all good, then lets do the embed
        } else if ($payloadobject->returnCode === 0) {
            $ideacount = $payloadobject->returnMessage;
            //clean up the correction a little
            if(!is_number($ideacount)){
                $ideacount=false;
            }

            return $ideacount;
        } else {
            return false;
        }
    }

    public function process_modeltts_stats($passage){
        $ret = ['embedding'=>false,'ideacount'=>false];
        if(empty($passage) || !$this->is_english()) {
            return $ret;
        }

        $embedding = self::fetch_embedding($passage);
        $ideacount = self::fetch_idea_count($passage);
        if($embedding){
            $ret['embedding'] = $embedding;
        }
        if($ideacount){
            $ret['ideacount'] = $ideacount;
        }
        return $ret;
    }

    //see if this is truly json or some error
    public static function is_json($string) {
        if (!$string) {
            return false;
        }
        if (empty($string)) {
            return false;
        }
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }


}
