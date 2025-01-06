<?php
trait General {
    // *Arr API Helper for building queries
    private function buildArrAPIQuery($Params = []) {
        $QueryParams = http_build_query($Params);
        if ($QueryParams) {
            $Query = '?'.$QueryParams;
            return $Query;
        }
    }

    // Normalise titles with spaces and special characters
    private function normalizeTitle($title) {
        // Remove special characters and convert to lowercase
        return strtolower(preg_replace('/[^a-zA-Z0-9\s]/', '', $title));
    }

    private function isDateOlderThanXDays($date, $days) {
        $currentDate = new DateTime();
        $interval = $currentDate->diff($date);
        return $interval->days > $days;
    }

    private function loadConfig() {
        $this->pluginConfig = $this->config->get('Plugins', 'Media Manager');
        $this->pluginConfig['tautulliMonths'] = $this->pluginConfig['tautulliMonths'] ?? 12;
        $this->pluginConfig['episodesToKeep'] = $this->pluginConfig['episodesToKeep'] ?? 3;
        $this->pluginConfig['promptForFolderDeletion'] = $this->pluginConfig['promptForFolderDeletion'] ?? true;
        $this->pluginConfig['sonarrApiVersion'] = $this->pluginConfig['sonarrApiVersion'] ?? 'v3';
        $this->pluginConfig['sonarrReportOnly'] = $this->pluginConfig['sonarrReportOnly'] ?? true;
        $this->pluginConfig['sonarrThrottlingSeasonThreshold'] = $this->pluginConfig['sonarrThrottlingSeasonThreshold'] ?? '4';
        $this->pluginConfig['sonarrThrottlingEpisodeThreshold'] = $this->pluginConfig['sonarrThrottlingEpisodeThreshold'] ?? '40';
        $this->pluginConfig['sonarrThrottlingEpisodeScanQty'] = $this->pluginConfig['sonarrThrottlingEpisodeScanQty'] ?? '10';
        $this->pluginConfig['sonarrCleanupEpisodesToKeep'] = $this->pluginConfig['sonarrCleanupEpisodesToKeep'] ?? '10';
        $this->pluginConfig['sonarrCleanupMaxAge'] = $this->pluginConfig['sonarrCleanupMaxAge'] ?? '180';
        $this->pluginConfig['radarrApiVersion'] = $this->pluginConfig['radarrApiVersion'] ?? 'v3';
        $this->pluginConfig['radarrReportOnly'] = $this->pluginConfig['radarrReportOnly'] ?? true;
        $this->pluginConfig['radarrCleanupMaxAge'] = $this->pluginConfig['radarrCleanupMaxAge'] ?? '1095';
    }

    // Generic Get API Results Function, to be shared across any API Wrappers
    private function getAPIResults($Method, $Url, $Data, $Headers = []) {
        if (in_array($Method,["GET","get"])) {
            $Result = $this->api->query->$Method($Url,$Headers,null,true);
        } else {
            $Result = $this->api->query->$Method($Url,$Data,$Headers,null,true);
        }
        if (isset($Result->status_code)) {
            if ($Result->status_code >= 400 && $Result->status_code < 600) {
                switch($Result->status_code) {
                    case 401:
                        $this->api->setAPIResponse('Error','API Key incorrect or expired');
                        $this->logging->writeLog("MediaManager","Error. API Key incorrect or expired.","error");
                        return;
                    case 404:
                        $this->api->setAPIResponse('Error','HTTP 404 Not Found');
                        return;
                    default:
                        $this->api->setAPIResponse('Error','HTTP '.$Result->status_code);
                        return;
                }
            }
        }

        $Response = $this->api->query->decodeResponse($Result,false);
        if (isset($Response)) {
            return $Response;
        } else {
            $this->api->setAPIResponse('Warning','No results returned from the API');
        }
    }
}