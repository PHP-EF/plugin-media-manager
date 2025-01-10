<?php
trait Radarr {
    // Radarr API Wrapper
    public function queryRadarrAPI($Method, $Uri, $Data = "", $Params = []) {
        if (!isset($this->pluginConfig['radarrUrl']) || empty($this->pluginConfig['radarrUrl'])) {
            $this->api->setAPIResponse('Error','Radarr URL Missing');
            $this->logging->writeLog("MediaManager","Radarr URL Missing","error");
            return false;
        }
        if (!isset($this->pluginConfig['radarrApiKey']) || empty($this->pluginConfig['radarrApiKey'])) {
            $this->api->setAPIResponse('Error','Radarr API Key Missing');
            $this->logging->writeLog('MediaManager','Radarr API Key Missing','error');
            return false;
        } else {
            try {
                $RadarrApiKey = decrypt($this->pluginConfig['radarrApiKey'],$this->config->get('Security','salt'));
            } catch (Exception $e) {
                $this->api->setAPIResponse('Error','Unable to decrypt Radarr API Key');
                $this->logging->writeLog('MediaManager','Unable to decrypt Radarr API Key','error');
                return false;
            }
        }
        $Params['apikey'] = $RadarrApiKey;
        if (!empty($Params)) {
            $BuiltQuery = $this->buildArrAPIQuery($Params);
            $Url = $this->pluginConfig['radarrUrl']."/api/".$this->pluginConfig['radarrApiVersion']."/".$Uri;
            $Url = $Url.$BuiltQuery;
            return $this->getAPIResults($Method,$Url,$Data);
        }
    }

    // Function to query list of movies from Radarr
    public function getRadarrMovies() {
        $Result = $this->queryRadarrAPI('GET','movie');
        if (is_array($Result)) {
            return $Result;
        } else {
            return false;
        }
    }

    public function getRadarrTags() {
        $Result = $this->queryRadarrAPI('GET','tag');
        if (is_array($Result)) {
            return $Result;
        } else {
            return false;
        }
    }

    // **
    // MATCH TAUTULLI -> RADARR
    // **

    private function queryAndMatchRadarrAndTautulli() {
        // Decode JSON data into PHP arrays
        $Radarr = $this->getRadarrMovies();
        $Tautulli = $this->getTautulliMovies();

        // Create an associative array for quick lookup from Tautulli data
        $TautulliMoviesList = [];
        foreach ($Tautulli as $TautulliMovie) {
            $TautulliNormalizedTitle = $this->normalizeTitle($TautulliMovie['title']);
            $TautulliMoviesList[$TautulliNormalizedTitle] = $TautulliMovie;
        }

        if ($Radarr && $Tautulli) {
            // Match Movies
            $Combined = [];
            foreach ($Radarr as $RadarrMovie) {
                $TautulliMovie = null;

                // Check if movie is downloaded, if not then skip the Tautulli check as it won't be on Plex
                if ($RadarrMovie['hasFile']) {
                    // Normalize title
                    $RadarrNormalizedTitle = $this->normalizeTitle($RadarrMovie['title']);

                    // Check primary title
                    if (isset($TautulliMoviesList[$RadarrNormalizedTitle])) {
                        $TautulliMovie = $TautulliMoviesList[$RadarrNormalizedTitle];
                    } else {
                        // Check alternative titles if primary title doesn't match
                        if (isset($RadarrMovie['alternateTitles'])) {
                            foreach ($RadarrMovie['alternateTitles'] as $altTitle) {
                                $altNormalizedTitle = $this->normalizeTitle($altTitle['title']);
                                if (isset($TautulliMoviesList[$altNormalizedTitle])) {
                                    $TautulliMovie = $TautulliMoviesList[$altNormalizedTitle];
                                    break; // Break out of the loop
                                }
                            }
                        }
                    }
                } else {
                    $RadarrMovie['MatchStatus'] = 'No Files';
                }

                if ($TautulliMovie) {
                    $RadarrMovie['Tautulli'] = $TautulliMovie;
                    $RadarrMovie['MatchStatus'] = 'Matched';
                } else {
                    $RadarrMovie['Tautulli'] = [];
                    if (!isset($RadarrMovie['MatchStatus'])) {
                        $RadarrMovie['MatchStatus'] = 'Unmatched';
                    }
                }
                $Combined[] = $RadarrMovie;
            }
            return $Combined;
        } else {
            if (empty($GLOBALS['api']['message'])) {
                $Faults = [];
                if (!$Radarr) {
                    $Faults[] = "Radarr";
                }
                if (!$Tautulli) {
                    $Faults[] = "Tautulli";
                }
                $this->api->setAPIResponse('Error', implode(' & ',$Faults).' did not respond as expected.', null, []);
                $this->logging->writeLog("MediaManager",implode(' & ',$Faults).' did not respond as expected.',"error");
            }
            return false;
        }
    }
}