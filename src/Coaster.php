<?php

if(!$_SESSION) {
    @ini_set('session.save_handler', 'files');
    @session_save_path("/tmp");
    @session_cache_expire(3600);
    @session_start();
}

class TM_Client_Exception extends Exception { }

if (!function_exists('curl_init')) {
    throw new TM_Client_Exception('TM needs the CURL PHP extension.');
}

if (!function_exists('json_decode')) {
    throw new TM_Client_Exception('TM needs the JSON PHP extension.');
}

class Coaster {

    private $dec;
    private $public;
    private $secret;
    private $service;
    private $token;

    public $debug;
    public $debug_log;
    public $exit_on_error;
    public $response_type = "json";

    const CLIENT_VERSION = "1.0.1";
    const API_VERSION    = "v4";

    public static $CURL_OPTIONS = array(
                                        CURLOPT_RETURNTRANSFER => true,
                                        CURLOPT_FOLLOWLOCATION => true
                                        );


    public function __construct($dec, $public, $secret, $options=false) {
        $this->dec      = $dec;
        $this->public   = $public;
        $this->secret   = $secret;

        if(is_array($options)) {
            foreach($options as $k=>$v)
                $this->$k = $v;
        }

        $version = $options['version'] ? $options['version'] : self::API_VERSION;

        $this->base_url = 'https://'.$dec.'.thismoment.com/'.$version."/api";
    }


    public function set_tm_token_session() {
        $res = $this->call("/login/get_token", $options);
        $this->set_token($res['tm-token']);
        return $this->get_token();
    }


    public function gallery($options) {
        return $this->call("/gallery/get", $options);
    }


    public function content($options) {
        return $this->call("/content/get", $options);
    }


    public function set_token($token) {
        $this->token = $_SESSION['tm-token'] = $token;
    }


    public function get_token() {
        $this->token = $_SESSION['tm-token'] ? $_SESSION['tm-token'] : $this->token;
        return $this->token;
    }


    public function generate_signature($params, $secret) {
        foreach(array("object", "action", "response", "version", "signature", "site_key", "tm-token","pretty","media_file") as $undo) {
            unset($params[$undo]);
        }

        // work with sorted data
        if(is_array($params))
            ksort($params);

        // generate the base string
        $base_string = '';
        if(is_array($params)) {
            $base_string = http_build_query($params, false, '&');
        }
        $base_string = $secret . $base_string;

        return md5($base_string);
    }


    public function get_debug_log() {
        return $this->debug_log;
    }


    public function call($service, $data) {
        $signature = $this->generate_signature($data, $this->secret);
        $url       = $this->base_url.$service.".".$this->response_type;
        $data['signature'] = $signature;
        $data['site_key']  = $this->public;
        if($this->get_token()) {
            $data['tm-token'] = $this->token;
        }

        $fields_string = '';
        if(is_array($data)) {
            // if media is attached, dont build the query
            if($data['media_file'])
                $fields_string = $data;
            else
                $fields_string = http_build_query($data, false, '&');
        }

        $ch = curl_init();

        $options = self::$CURL_OPTIONS;
        if($this->token)
            $options[CURLOPT_COOKIE] = session_name() . "=" . $this->token;

        $options[CURLOPT_URL]        = $url;
        $options[CURLOPT_POST]       = count($fields);
        $options[CURLOPT_HTTPHEADER] = array('Content-Length: ' . strlen($fields_string));
        $options[CURLOPT_POSTFIELDS] = $fields_string;
        $options[CURLOPT_VERBOSE]    = $this->debug;
        $options[CURLOPT_BINARYTRANSFER] = true;

        curl_setopt_array($ch,$options);
            
        $result = curl_exec($ch);
        

        if($this->debug) {
            print $url . "\n\n";
            $this->debug_log['curl_info'] = curl_getinfo($ch);
            $this->debug_log['data'] = $data;
            $this->debug_log['results'] = $result;
        }

        //close connection
        curl_close($ch);

        if($this->debug) {
            print_r($this->debug_log);
            print $result;
        }

        switch($this->response_type) {
            case "json":
                $_r = json_decode($result, true);
                if($_r['status'] != "OK") {
                    if($this->exit_on_error) {
                        throw new TM_Client_Exception("Error received for " . $service . ": " . $_r['status'] . "\n" . "Message received: " . $_r['errors']);
                    }
                    $return_results = $_r;
                } else {
                    //$return_results = $_r['results'];
                    $return_results =  array_merge($_r['results'], $_r['meta']);
                }
                break;
        }

        return $return_results;
    }
}

?>
