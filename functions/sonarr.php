<?php
trait Sonarr {
    // Sonarr API Wrapper
    public function querySonarrAPI($Method, $Uri, $Data = "", $Params = []) {
        if (!isset($this->pluginConfig['sonarrUrl']) || empty($this->pluginConfig['sonarrUrl'])) {
            $this->api->setAPIResponse('Error','Sonarr URL Missing');
            $this->logging->writeLog("MediaManager","Sonarr URL Missing","error");
            return false;
        }
        if (!isset($this->pluginConfig['sonarrApiKey']) || empty($this->pluginConfig['sonarrApiKey'])) {
            $this->api->setAPIResponse('Error','Sonarr API Key Missing');
            $this->logging->writeLog('MediaManager','Sonarr API Key Missing','error');
            return false;
        } else {
            try {
                $SonarrAPIKey = decrypt($this->pluginConfig['sonarrApiKey'],$this->config->get('Security','salt'));
            } catch (Exception $e) {
                $this->api->setAPIResponse('Error','Unable to decrypt Sonarr API Key');
                $this->logging->writeLog('MediaManager','Unable to decrypt Sonarr API Key','error');
                return false;
            }
        }
        $Params['apikey'] = $SonarrAPIKey;
        if (!empty($Params)) {
            $BuiltQuery = $this->buildArrAPIQuery($Params);
            $Url = $this->pluginConfig['sonarrUrl']."/api/".$this->pluginConfig['sonarrApiVersion']."/".$Uri;
            $Url = $Url.$BuiltQuery;

            return $this->getAPIResults($Method,$Url,$Data);
        }
    }

    // Function to query list of TV Shows from Sonarr
    public function getSonarrTVShows($Params = []) {
        $Result = $this->querySonarrAPI('GET','series',null,$Params);
        return $Result;
    }

    // Function to query list of TV Shows from Sonarr by Series ID
    public function getSonarrTVShowById($id) {
        $Result = $this->querySonarrAPI('GET','series/'.$id);
        return $Result;
    }

    // Function to update a TV Show in Sonarr. Requires sonarr object to be submitted as a parameter.
    public function updateSonarrTVShow($data) {
        if ($data['id']) {
            $Result = $this->querySonarrAPI('PUT','series/'.$data['id'],$data);
            if ($Result) {
                return $Result;
            } else {
                $this->logging->writeLog("MediaManager","Failed to update Sonarr Series.","error",$data);
                return false;
            }
        } else {
            $this->logging->writeLog("MediaManager","Failed to update Sonarr Series. id was missing from series data","error",$data);
            return false;
        }
    }

    // Function to query list of Episodes from Sonarr, filtering by a specific Series ID
    public function getSonarrEpisodesBySeriesId($id) {
        $Params = array(
            "seriesId" => $id
        );
        $Result = $this->querySonarrAPI('GET','episode',null,$Params);
        return $Result;
    }

    public function getSonarrTags() {
        $Result = $this->querySonarrAPI('GET','tag');
        return $Result;
    }

    public function runSonarrCommand($cmd) {
        try {
            $Result = $this->querySonarrAPI('POST','command',$cmd);
            if ($Result) {
                return $Result;
            } else {
                $this->logging->writeLog('Sonarr','Unable to run Sonarr command','error',$Result);
                return false;
            }
        } catch (Requests_Exception $e) {
            $this->logging->writeLog('Sonarr','Unable to run Sonarr command','error',$e);
            return false;
        }
    }

    // **
    // MATCH TAUTULLI -> SONARR
    // **

    private function queryAndMatchSonarrAndTautulli() {
        // Decode JSON data into PHP arrays
        $Sonarr = $this->getSonarrTVShows();
        $Tautulli = $this->getTautulliTVShows();

        // Create an associative array for quick lookup from Tautulli data
        $TautulliShowsList = [];
        foreach ($Tautulli as $TautulliShow) {
            $TautulliNormalizedTitle = $this->normalizeTitle($TautulliShow['title']);
            $TautulliShowsList[$TautulliNormalizedTitle] = $TautulliShow;
        }

        if ($Sonarr && $Tautulli) {
            // Match TV shows
            $Combined = [];
            foreach ($Sonarr as $SonarrShow) {
                $TautulliShow = null;

                // Check if show has any episodes, if not then skip the Tautulli check as it won't be on Plex
                if ($SonarrShow['statistics']['episodeFileCount'] > 0) {
                    // Normalize title
                    $SonarrNormalizedTitle = $this->normalizeTitle($SonarrShow['title']);

                    // Check primary title
                    if (isset($TautulliShowsList[$SonarrNormalizedTitle])) {
                        $TautulliShow = $TautulliShowsList[$SonarrNormalizedTitle];
                    } else {
                        // Check alternative titles if primary title doesn't match
                        if (isset($SonarrShow['alternateTitles'])) {
                            foreach ($SonarrShow['alternateTitles'] as $altTitle) {
                                $altNormalizedTitle = $this->normalizeTitle($altTitle['title']);
                                if (isset($TautulliShowsList[$altNormalizedTitle])) {
                                    $TautulliShow = $TautulliShowsList[$altNormalizedTitle];
                                    break; // Break out of the loop
                                }
                            }
                        }
                    }
                } else {
                    $SonarrShow['MatchStatus'] = 'No Episodes';
                }

                if ($TautulliShow) {
                    $SonarrShow['Tautulli'] = $TautulliShow;
                    $SonarrShow['MatchStatus'] = 'Matched';
                } else {
                    $SonarrShow['Tautulli'] = [];
                    if (!isset($SonarrShow['MatchStatus'])) {
                        $SonarrShow['MatchStatus'] = 'Unmatched';
                    }
                }
                $Combined[] = $SonarrShow;
            }
            return $Combined;
        } else {
            if (empty($GLOBALS['api']['message'])) {
                $Faults = [];
                if (!$Sonarr) {
                    $Faults[] = "Sonarr";
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

    // **
    // SONARR THROTTLING
    // **

    public function sonarrThrottlingTautulliWebhook($request) {
        ## Get Throttled Tag
        $ThrottledTag = $this->pluginConfig['sonarrThrottlingTag'] ?? null;

        ## Error if Throttled tag is not set
        if (empty($ThrottledTag)) {
            $this->api->setAPIResponse('Error', 'Throttling tag is missing, check logs.');
            $this->logging->writeLog("SonarrThrottling","Throttling tag is missing or not set","error");
            return false;
        }

        ## Check for valid data and API Key
        if ($request == null) {
            $this->api->setAPIResponse('Error', 'Tautulli Webhook Request Empty.');
            $this->logging->writeLog("SonarrThrottling","Tautulli Webhook Request Empty","error");
            return false;
        }

        ## Check for test notification
        if (isset($request['test_notification'])) {
            $this->api->setAPIResponseMessage('Tautulli Webhook Test Successful.');
            $this->logging->writeLog("SonarrThrottling","Tautulli Webhook Test Received","notice",$request);
            return true;
        }

        if ($request['media_type'] == "episode") {
            ## Check tvdbId exists
            if (empty($request['tvdbId'])) {
                $this->api->setAPIResponse('Error', 'Empty tvdbId.');
                $this->logging->writeLog("SonarrThrottling","Tautulli Webhook Error: Empty tvdbId","error",$request);
                return false;
            }

            ## Get Sonarr Series by tvdbId
            $Params = [
                'tvdbId' => $request['tvdbId']
            ];
            $SonarrSeriesLookup = $this->getSonarrTVShows($Params);

            ## Check if Sonarr Object Exists
            if (isset($SonarrSeriesLookup[0])) {
                $SonarrSeries = $SonarrSeriesLookup[0];
                ## Check if TV Show is tagged as Throttled within Sonarr
                if (!in_array($ThrottledTag,$SonarrSeries['tags'])) {
                    $this->api->setAPIResponseMessage('TV Show is not throttled');
                    $this->logging->writeLog("SonarrThrottling","Tautulli Webhook: TV Show is not throttled: ".$SonarrSeries['title'],"info",$request);
                    return false;
                }

                $Episodes = $this->getSonarrEpisodesBySeriesId($SonarrSeries['id']);

                // Find next available file to download
                foreach ($Episodes as $Episode) {
                    if ($Episode['hasFile'] == false && $Episode['seasonNumber'] != "0" && $Episode['monitored'] == true) {
                        // Set Episode ID to search for
                        $cmd = [
                            'episodeIds' => [
                                $Episode['id']
                            ],
                            'name' => 'episodeSearch'
                        ];
                        // Send Scan Request to Sonarr
                        if ($this->runSonarrCommand($cmd)) {
                            // Mark more episodes as available
                            $MoreEpisodesAvailable = true;

                            $Response = 'Search request sent for: '.$SonarrSeries['title'].' - S'.$Episode['seasonNumber'].'E'.$Episode['episodeNumber'].' - '.$Episode['title'];
                            $this->api->setAPIResponseMessage($Response);
                            $this->logging->writeLog("SonarrThrottling","Tautulli Webhook: Search Request Sent","info",[$Response]);
                            return true;
                        } else {
                            $Response = 'Failed to send search request for: '.$SonarrSeries['title'].' - S'.$Episode['seasonNumber'].'E'.$Episode['episodeNumber'].' - '.$Episode['title'];
                            $this->api->setAPIResponse('Error',$Response);
                            $this->logging->writeLog("SonarrThrottling","Tautulli Webhook: Failed to send Search Request","error",[$Response]);
                            return true;
                        }
                    }
                }
                // If no more episodes available (i.e show is full), mark as monitored and unthrottled.
                if (!$MoreEpisodesAvailable) {
                    if ($SonarrSeries) {
                        ## Find Throttled Tag and remove it
                        $ArrKey = array_search($ThrottledTag, $SonarrSeries['tags']);
                        if ($ArrKey) {
                            unset($SonarrSeries['tags'][$ArrKey]);
                        }
                        ## Mark TV Show as Monitored
                        $SonarrSeries['monitored'] = true;
                        ## Submit data back to Sonarr
                        $Update = $this->updateSonarrTVShow($SonarrSeries);
                        if ($Update) {
                            $Response = 'All aired episodes are available. Removed throttling from: '.$SonarrSeries['title'].' and marked as monitored.';
                            $this->api->setAPIResponseMessage($Response);
                            $this->logging->writeLog("SonarrThrottling","Tautulli Webhook: TV Show Full","info",[$Update]);
                            return true;
                        } else {
                            $Response = 'Failed to update TV Show: '.$SonarrSeries['title'];
                            $this->api->setAPIResponse('Error',$Response);
                            $this->logging->writeLog("SonarrThrottling","Tautulli Webhook: Failed to update TV Show","info",[$Response]);
                            return false;
                        }
                    } else {
                        $Response = 'Failed to find series in Sonarr: '.$SonarrSeries['title'];
                        $this->api->setAPIResponse('Error',$Response);
                        $this->logging->writeLog("SonarrThrottling","Tautulli Webhook: Failed to find series in Sonarr","error",[$Response]);
                        return false;
                    }
                }
            } else {
                $this->api->setAPIResponse('Error', 'Sonarr ID Missing From Database.');
                $this->logging->writeLog("SonarrThrottling","Sonarr ID Missing From Database: ".$SonarrSeries['title'],"error");
                return false;
            }
        } else {
            $this->api->setAPIResponseMessage('Not a TV Show');
            $this->logging->writeLog("SonarrThrottling","Not a TV Show","info");
            return true;
        }
    }

    public function sonarrThrottlingOverseerrWebhook($request) {
        ## Get Throttled Tag
        $ThrottledTag = $this->pluginConfig['sonarrThrottlingTag'] ?? null;

        ## Error if Throttled tag is not set
        if (empty($ThrottledTag)) {
            $this->api->setAPIResponse('Error', 'Throttling tag is missing, check logs.');
            $this->logging->writeLog("SonarrThrottling","Throttling tag is missing or not set","error");
            return false;
        }

        ## Check for valid data and API Key
        if ($request == null) {
            $this->api->setAPIResponse('Error', 'Overseerr Webhook Request Empty.');
            $this->logging->writeLog("SonarrThrottling","Overseerr Webhook Request Empty","error");
            return false;
        }

        ## Check for test notification
        if ($request['notification_type'] == "TEST_NOTIFICATION") {
            $this->api->setAPIResponseMessage('Overseerr Webhook Test Successful.');
            $this->logging->writeLog("SonarrThrottling","Overseerr Webhook Test Received","notice",$request);
            return true;
        }

        ## Check Request Type
        if ($request['media']['media_type'] == "tv") {

            ## Check tvdbId exists
            if (empty($request['media']['tvdbId'])) {
                $this->api->setAPIResponse('Error', 'Empty tvdbId.');
                $this->logging->writeLog("SonarrThrottling","Overseerr Webhook Error: Empty tvdbId","error",$request);
                return false;
            }

            // ** REWORK THIS
            ## Sleep to allow Sonarr to update. Might add a loop checking logic here in the future.
            // sleep(10);

            ## Lookup Sonarr Series by tvdbId
            $Params = [
                'tvdbId' => $request['media']['tvdbId']
            ];
            $SonarrLookupObj = $this->getSonarrTVShows($Params);

            ## Check if Sonarr Object Exists
            if (!isset($SonarrLookupObj[0])) {
                $this->api->setAPIResponse('Error', 'TV Show not in Sonarr database.');
                $this->logging->writeLog('SonarrThrottling','Overseerr Webhook Error: TV Show not in Sonarr database.','error',$Params);
                return false;
            } else {
                // Grab Sonarr Object
                $SonarrLookupItem = $SonarrLookupObj[0];

                ## Check if TV Show is already present and tagged as Throttled within Sonarr
                if (in_array($ThrottledTag,$SonarrLookupItem['tags'])) {
                    $this->api->setAPIResponseMessage('TV Show is already throttled');
                    $this->logging->writeLog("SonarrThrottling","Overseerr Webhook: TV Show is already throttled: ".$SonarrLookupItem['title'],"info",$request);
                    return true;
                }

                ## Check Season / Episode Counts & Apply Throttling Tag if neccessary
                $EpisodeCount = 0;
                foreach ($SonarrLookupItem['seasons'] as $season) {
                    $EpisodeCount += $season['statistics']['totalEpisodeCount'];
                }
                $SeasonCount = $SonarrLookupItem['statistics']['seasonCount'];
                if ($SeasonCount > $this->pluginConfig['sonarrThrottlingSeasonThreshold']) {
                    $SonarrLookupItem['tags'][] = $ThrottledTag;
                    $SonarrLookupItem['monitored'] = false;
                    $Search = "searchX";
                } else if ($EpisodeCount > $this->pluginConfig['sonarrThrottlingEpisodeThreshold']) {
                    $SonarrLookupItem['tags'][] = $ThrottledTag;
                    $SonarrLookupItem['monitored'] = false;
                    $Search = "searchX";
                } else {
                    $SonarrLookupItem['monitored'] = true;
                    $SonarrLookupItem['addOptions']['searchForMissingEpisodes'] = true;
                    $Search = "searchAll";
                };

                // Initiate Full Series Search
                if ($Search == "searchAll") {
                    $SonarrSearch = [
                        "name" =>  "SeriesSearch",
                        "seriesId" => $SonarrLookupItem['id']
                    ];
                    $Response = $SonarrLookupItem['title'].' has been updated as a normal TV Show. Sent search request for all episodes.';
                } else if ($Search == "searchX") {
                    ## Update Series to be Throttled
                    if (!$this->updateSonarrTVShow($SonarrLookupItem)) {
                        $Response = 'Failed to update TV Show: '.$SonarrLookupItem['title'];
                        $this->api->setAPIResponse('Error',$Response);
                        $this->logging->writeLog("SonarrThrottling","Overseerr Webhook: Failed to update TV Show","info",[$Response]);
                        return false;
                    } else {
                        // Initiate Part Series Search
                        $Episodes = $this->getSonarrEpisodesBySeriesId($SonarrLookupItem['id']); // Get list of episodes
                        $EpisodesToSearch = [];
                        foreach ($Episodes as $Key => $Episode) {
                            if ($Episode['seasonNumber'] != "0" && $Episode['hasFile'] != true && $Episode['monitored'] == true) {
                                $EpisodesToSearch[] = $Episode['id'];
                            }
                        }
                        $SonarrSearch = [
                            "name" =>  "EpisodeSearch",
                            "episodeIds" => array_slice($EpisodesToSearch,0,$this->pluginConfig['sonarrThrottlingEpisodeScanQty'])
                        ];
                        $Response = $SonarrLookupItem['title'].' has been updated as a Throttled TV Show. Sent search request for the first '.$this->pluginConfig['sonarrThrottlingEpisodeScanQty'].' episodes.';
                    }
                }

                if (isset($Search) && isset($SonarrSearch)) {
                    // Send Scan Command to Sonarr
                    if ($this->runSonarrCommand($SonarrSearch)) {
                        $this->api->setAPIResponseMessage($Response);
                        $this->logging->writeLog('SonarrThrottling',$Response,'info',$SonarrSearch);
                        return true;
                    } else {
                        $this->api->setAPIResponse('Error', 'Failed to initiate scan request to Sonarr');
                        $this->logging->writeLog('SonarrThrottling','Overseerr Webhook Error: Failed to initiate scan request to Sonarr.','error',$SonarrSearch);
                        return false;
                    }
                }
            }
        } else {
            $this->api->setAPIResponse('Error', 'Not a TV Show.');
            $this->logging->writeLog('SonarrThrottling','Overseerr Webhook: Not a TV Show Request.','debug',$request);
            return false;
        }
    }
}