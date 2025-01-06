<?php
trait Tautulli {
    // Tautuilli API Wrapper
    public function queryTautulliAPI($Method, $Cmd, $Data = "") {
        if (!isset($this->pluginConfig['tautulliUrl'])) {
            $this->api->setAPIResponse('Error','Tautulli URL Missing');
            return false;
        }

        if (!isset($this->pluginConfig['tautulliApiKey']) || empty($this->pluginConfig['tautulliApiKey'])) {
            $this->api->setAPIResponse('Error','Tautulli API Key Missing');
            return false;
        } else {
            try {
                $TautulliAPIKey = decrypt($this->pluginConfig['tautulliApiKey'],$this->config->get('Security','salt'));
            } catch (Exception $e) {
                $this->logging->writeLog('MediaManager','Unable to decrypt Tautulli API Key','error');
                return false;
            }
        }

        $Url = $this->pluginConfig['tautulliUrl']."/api/v2?cmd=".$Cmd;
        $Url = $Url.'&apikey='.$TautulliAPIKey;
        $Results = $this->getAPIResults($Method,$Url,$Data);
        if (isset($Results['response'])) {
            if (isset($Results['response']['data'])) {
                return $Results['response']['data'];
            } else {
                $this->api->setAPIResponse($Results['response']['result'],$Results['response']['message']);
                return false;
            }
        } else {
            $this->api->setAPIResponse('Error','Tautulli did not return any data');
            return false;
        }
    }

    // Tautulli API Helper for building queries
    private function buildTautulliAPIQuery($Cmd,$Params = []) {
        $QueryParams = http_build_query($Params);
        if ($QueryParams) {
            $Query = $Cmd.'&'.$QueryParams;
            return $Query;
        } else {
            return $Cmd;
        }
    }

    // Get a list of Tautulli Libraries
    public function getTautulliLibraries() {
        $Result = $this->queryTautulliAPI('GET',$this->buildTautulliAPIQuery('get_libraries'));
        return $Result;
    }

    // Get a list of Media from a particular library
    public function getTautulliMediaFromLibrary($Params) {
        $Result = $this->queryTautulliAPI('GET',$this->buildTautulliAPIQuery('get_library_media_info',$Params));
        return $Result;
    }

    // Get a list of unwatched items from the specified library
    public function getTautulliUnwatched($SectionID) {
        $Params = array(
            "section_id" => $SectionID,
            "length" => 10000 // Anything higher would probably need some form of paging
        );
        // Get a list of Media
        $Media = $this->getTautulliMediaFromLibrary($SectionID);
        // Filter TV shows that have never been watched
        $unwatched_shows = array_filter($Media['data'], function($show) {
            return empty($show['last_played']);
        });
        return $unwatched_shows;
    }

    // Get a list of TV Shows from Tautulli
    public function getTautulliTVShows() {
        $Libraries = $this->getTautulliLibraries();
        if ($Libraries) {
            $TVLibraries = array_filter($Libraries, function($Library) {
                return $Library['section_type'] == 'show';
            });
            $Results = array();
            foreach ($TVLibraries as $TVLibrary) {
                $Params = array(
                    'section_id' => $TVLibrary['section_id'],
                    'length' => 10000
                );
                $Result = $this->getTautulliMediaFromLibrary($Params);
                
                if (is_array($Result)) {
                    foreach ($Result['data'] as &$item) {
                        $item['library_name'] = $TVLibrary['section_name']; // Add library name to each item
                    }
                    $Results = array_merge($Results, $Result['data']);
                }
            }
            return $Results;
        }  
    }

    // Get a list of Movies from Tautulli
    public function getTautulliMovies() {
        $Libraries = $this->getTautulliLibraries();
        if ($Libraries) {
            $MovieLibraries = array_filter($Libraries, function($Library) {
                return $Library['section_type'] == 'movie';
            });
            $Results = array();
            foreach ($MovieLibraries as $MovieLibrary) {
                $Params = array(
                    'section_id' => $MovieLibrary['section_id'],
                    'length' => 10000
                );
                $Result = $this->getTautulliMediaFromLibrary($Params);

                if (is_array($Result)) {
                    foreach ($Result['data'] as &$item) {
                        $item['library_name'] = $MovieLibrary['section_name']; // Add library name to each item
                    }
                    $Results = array_merge($Results, $Result['data']);
                }
            }
            return $Results;
        }  
    }

    public function initiateTautulliSSO($data) {
        $Url = $this->pluginConfig['tautulliUrl']."/auth/signin";
        $Results = $this->api->query->post($Url,$data,array('Content-Type' => 'x-www-form-urlencoded'));
        if (isset($Results)) {
            return $Results;
        } else {
            $this->api->setAPIResponse('Error','Tautulli SSO did not return any data');
            return false;
        }
    }
}