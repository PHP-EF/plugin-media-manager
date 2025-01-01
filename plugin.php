<?php
// **
// USED TO DEFINE PLUGIN INFORMATION & CLASS
// **

// PLUGIN INFORMATION - This should match what is in plugin.json
$GLOBALS['plugins']['Plex TV Cleaner'] = [
    'name' => 'Plex TV Cleaner',
    'author' => 'jamiedonaldson-tinytechlabuk',
    'category' => 'Media Management',
    'link' => 'https://github.com/jamiedonaldson-tinytechlabuk/php-ef-plex-tv-cleaner',
    'version' => '1.0.4',
    'image' => 'logo.png',
    'settings' => true,
    'api' => '/api/plugin/plextvcleaner/settings',
];

class plextvcleaner extends ib {
    private $pluginConfig;

    public function __construct() {
        parent::__construct();
        $this->loadConfig();
    }

    private function loadConfig() {
        $this->pluginConfig = $this->config->get('Plugins', 'Plex TV Cleaner');
        $this->pluginConfig['tautulliMonths'] = $this->pluginConfig['tautulliMonths'] ?? 12;
        $this->pluginConfig['episodesToKeep'] = $this->pluginConfig['episodesToKeep'] ?? 3;
        $this->pluginConfig['reportOnly'] = $this->pluginConfig['reportOnly'] ?? true;
        $this->pluginConfig['promptForFolderDeletion'] = $this->pluginConfig['promptForFolderDeletion'] ?? true;
    }

    public function _pluginGetSettings() {
        $excludeFolders = [];
        if (!empty($this->pluginConfig['excludeFolders'])) {
            foreach ($this->pluginConfig['excludeFolders'] as $folder) {
                $excludeFolders[] = $folder;
            }
        }

        return array(
            'About' => array(
                $this->settingsOption('notice', '', ['title' => 'Information', 'body' => '
                <p>This plugin helps manage and clean up TV show folders in your Plex server environment. It integrates with Tautulli to track watched shows and applies custom cleanup rules to maintain a manageable library size.</p>
                <br/>']),
            ),
            'Plugin Settings' => array(
                $this->settingsOption('auth', 'ACL-PLEXTVCLEANER', ['label' => 'Plex TV Cleaner Plugin Access ACL'])
            ),
            'TV Show Settings' => array(
                $this->settingsOption('input', 'tvRootFolder', ['label' => 'Plex TV Root Folder', 'placeholder' => '\\\\SERVER\\Plex\\TV']),
                $this->settingsOption('input-multiple', 'tvExcludeFolders', ['label' => 'TV Shows to Exclude', 'values' => $excludeFolders, 'text' => 'Add'])
            ),
            'Tautulli Settings' => array(
                $this->settingsOption('url', 'tautulliUrl', ['label' => 'Tautulli API URL', 'placeholder' => 'http://server:port']),
                $this->settingsOption('input', 'tautulliApiKey', ['label' => 'Tautulli API Key', 'placeholder' => 'Your API Key']),
                $this->settingsOption('input', 'tautulliMonths', ['label' => 'Months to Look Back', 'placeholder' => '12'])
            ),
            'Sonarr Settings' => array(
                $this->settingsOption('url', 'sonarrUrl', ['label' => 'Sonarr API URL', 'placeholder' => 'http://server:port']),
                $this->settingsOption('input', 'sonarrApiKey', ['label' => 'Sonarr API Key', 'placeholder' => 'Your API Key']),
                $this->settingsOption('select', 'sonarrApiVersion', ['label' => 'Sonarr API Version', 'options' => array(array("name" => 'v1', "value" => 'v1'),array("name" => 'v2', "value" => 'v2'),array("name" => 'v3', "value" => 'v3'))]),
            ),
            'Cleanup Settings' => array(
                $this->settingsOption('input', 'episodesToKeep', ['label' => 'Number of Episodes to Keep', 'placeholder' => '3']),
                $this->settingsOption('select', 'reportOnly', ['label' => 'Report Only Mode (No Deletions)', 'options' => [
                    ['name' => 'Yes', 'value' => 'true'],
                    ['name' => 'No', 'value' => 'false']
                ]]),
                $this->settingsOption('select', 'Prompt For Folder Deletion', ['label' => 'Prompt Before Folder Deletion', 'options' => [
                    ['name' => 'Yes', 'value' => 'true'],
                    ['name' => 'No', 'value' => 'false']
                ]])
            ),
        );
    }

    // Generic Get API Results Function, to be shared across any API Wrappers
    private function getAPIResults($Method, $Url, $Data) {
        if ($Method == "get") {
            $Result = $this->api->query->$Method($Url,null,null,true);
        } else {
            $Result = $this->api->query->$Method($Url,$Data,null,null,true);
        }

        if (isset($Result->status_code)) {
            if ($Result->status_code >= 400 && $Result->status_code < 600) {
                switch($Result->status_code) {
                    case 401:
                        $this->api->setAPIResponse('Error','API Key incorrect or expired');
                        $this->logging->writeLog("PlexTVCleaner","Error. API Key incorrect or expired.","error");
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
        if (is_array($Result)) {
            if (isset($Result['response'])) {
                if (isset($Result['response']['data'])) {
                    return $Result['response']['data'];
                } else {
                    return $Result;
                }
            } else {
                return $Result;
            }
        } else {
            $this->api->setAPIResponse('Warning','No results returned from the API');
        }
    }



    // **
    // TAUTULLI
    // **

    // Tautuilli API Wrapper
    public function queryTautulliAPI($Method, $Cmd, $Data = "") {
        if (!isset($this->pluginConfig['tautulliUrl'])) {
            $this->api->setAPIResponse('Error','Tautulli URL Missing');
            return false;
        }

        if (!isset($this->pluginConfig['tautulliApiKey'])) {
            $this->api->setAPIResponse('Error','Tautulli API Key Missing');
            return false;
        }

        $Url = $this->pluginConfig['tautulliUrl']."/api/v2?cmd=".$Cmd;
        $Url = $Url.'&apikey='.$this->pluginConfig['tautulliApiKey'];
        return $this->getAPIResults($Method,$Url,$Data);
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
        $this->api->setAPIResponseData($Result);
        return $Result;
    }

    // Get a list of Media from a particular library
    public function getTautulliMediaFromLibrary($Params) {
        $Result = $this->queryTautulliAPI('GET',$this->buildTautulliAPIQuery('get_library_media_info',$Params));
        $this->api->setAPIResponseData($Result);
        return $Result;
    }

    // Get a list of unwatched items from the specified library
    public function getTautulliUnwatched($SectionID) {
        $Params = array(
            "section_id" => $SectionID,
            "length" => 10000 // Anything higher would probably need some form of paging
        );
        // Get a list of Media
        $Media = $this->queryTautulliAPI('GET',$this->buildTautulliAPIQuery('get_library_media_info',$Params));
        // Filter TV shows that have never been watched
        $unwatched_shows = array_filter($Media['data'], function($show) {
            return empty($show['last_played']);
        });
        $this->api->setAPIResponseData($unwatched_shows);
        return $unwatched_shows;
    }

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


    // **
    // SONARR
    // **
    // Sonarr API Wrapper
    public function querySonarrAPI($Method, $Uri, $Data = "") {
        if (!$this->pluginConfig['sonarrUrl']) {
            $this->api->setAPIResponse('Error','Sonarr URL Missing');
            return false;
        }

        if (!$this->pluginConfig['sonarrApiKey']) {
            $this->api->setAPIResponse('Error','Sonarr API Key Missing');
            return false;
        }

        $Url = $this->pluginConfig['sonarrUrl']."/api/".$this->pluginConfig['sonarrApiVersion']."/".$Uri;
        $Url = $Url.'?apikey='.$this->pluginConfig['sonarrApiKey'];
        return $this->getAPIResults($Method,$Url,$Data);
    }

    // Sonarr API Helper for building queries
    private function buildSonarrAPIQuery($Cmd,$Params = []) {
        $QueryParams = http_build_query($Params);
        if ($QueryParams) {
            $Query = '&'.$QueryParams;
            return $Query;
        } else {
            return $Cmd;
        }
    }

    public function getSonarrTVShows() {
        $Result = $this->querySonarrAPI('GET','series');
        return $Result;
    }


    // **
    // MATCH TAUTULLI -> SONARR
    // **

    function normalizeTitle($title) {
        // Remove special characters and convert to lowercase
        return strtolower(preg_replace('/[^a-zA-Z0-9\s]/', '', $title));
    }

    function queryAndMatchSonarrAndTautulli() {
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
                    // echo $SonarrShow['title']."\r\n";
                    // echo $SonarrNormalizedTitle."\r\n";
                    $SonarrShow['Tautulli'] = [];
                    if (!isset($SonarrShow['MatchStatus'])) {
                        $SonarrShow['MatchStatus'] = 'Unmatched';
                    }
                }
                $Combined[] = $SonarrShow;
            }
            $this->api->setAPIResponseData($Combined);
            return $Combined;
        } else {
            $this->api->setAPIResponse('Error', 'Tautulli or Sonarr did not respond as expected.', null, []);
        }
    }





    // **
    // OLD STUFF
    // **

    public function cleanup($params = null) {
        if (!isset($params['path'])) {
            $this->api->setAPIResponse('Error', 'Show path is required');
            return false;
        }

        $dryRun = isset($params['dryRun']) ? filter_var($params['dryRun'], FILTER_VALIDATE_BOOLEAN) : null;
        $results = $this->cleanupShow($params['path'], $dryRun);
        if (isset($results)) {
            $this->api->setAPIResponseData($results);
            return $results;
        } else {
            $this->api->setAPIResponse('Error', 'Failed to clean up show');
            return false;
        }
    }

    public function getTvShows() {
        if (!file_exists($this->pluginConfig['rootFolder'])) {
            $this->api->setAPIResponse('Error', 'Root folder does not exist');
            return false;
        }
        $shows = [];
        $dir = new DirectoryIterator($this->pluginConfig['rootFolder']);
        foreach ($dir as $fileinfo) {
            if ($fileinfo->isDir() && !$fileinfo->isDot()) {
                $showName = $fileinfo->getFilename();
                if (!in_array($showName, $this->pluginConfig['excludeFolders'])) {
                    $shows[] = [
                        'name' => $showName,
                        'path' => $fileinfo->getPathname(),
                        'episodeCount' => $this->countEpisodes($fileinfo->getPathname()),
                        'size' => $this->getFolderSize($fileinfo->getPathname()),
                        'lastWatched' => $this->getLastWatchedDate($showName)
                    ];
                }
            }
        }
        $this->api->setAPIResponseData($shows);
        return $shows;
    }

    private function countEpisodes($path) {
        $count = 0;
        $dir = new RecursiveDirectoryIterator($path);
        $iterator = new RecursiveIteratorIterator($dir);
        foreach ($iterator as $file) {
            if ($file->isFile() && in_array($file->getExtension(), ['mkv', 'mp4', 'avi'])) {
                $count++;
            }
        }
        return $count;
    }

    private function getFolderSize($path) {
        $size = 0;
        $dir = new RecursiveDirectoryIterator($path);
        $iterator = new RecursiveIteratorIterator($dir);
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        return $size;
    }

    private function getLastWatchedDate($showName) {
        if (empty($this->pluginConfig['tautulliUrl']) || empty($this->pluginConfig['tautulliApiKey'])) {
            return null;
        }

        $url = sprintf(
            '%s?apikey=%s&cmd=get_history&length=1&title=%s',
            rtrim($this->pluginConfig['tautulliUrl'], '/'),
            $this->pluginConfig['tautulliApiKey'],
            urlencode($showName)
        );

        $response = @file_get_contents($url);
        if ($response === false) {
            return null;
        }

        $data = json_decode($response, true);
        if (isset($data['response']['data']['data'][0]['date'])) {
            return $data['response']['data']['data'][0]['date'];
        }

        return null;
    }

    public function cleanupShow($showPath, $dryRun = null) {
        if ($dryRun === null) {
            $dryRun = $this->pluginConfig['reportOnly'];
        }

        if (!file_exists($showPath)) {
            return ['error' => 'Show path does not exist'];
        }

        $filesToDelete = [];
        $totalSize = 0;
        
        // Get all episode files sorted by modification time (newest first)
        $episodes = [];
        $dir = new RecursiveDirectoryIterator($showPath);
        $iterator = new RecursiveIteratorIterator($dir);
        foreach ($iterator as $file) {
            if ($file->isFile() && in_array($file->getExtension(), ['mkv', 'mp4', 'avi'])) {
                $episodes[] = [
                    'path' => $file->getPathname(),
                    'mtime' => $file->getMTime(),
                    'size' => $file->getSize()
                ];
            }
        }

        // Sort episodes by modification time (newest first)
        usort($episodes, function($a, $b) {
            return $b['mtime'] - $a['mtime'];
        });

        // Mark episodes for deletion, keeping the newest N episodes
        for ($i = $this->pluginConfig['tvShowsEpisodeCount']; $i < count($episodes); $i++) {
            $filesToDelete[] = $episodes[$i]['path'];
            $totalSize += $episodes[$i]['size'];
        }

        // If not a dry run and not prompting, or if prompting and user confirmed, delete the files
        if (!$dryRun) {
            if (!$this->pluginConfig['promptForFolderDeletion'] || $this->confirmDeletion($showPath, $filesToDelete, $totalSize)) {
                foreach ($filesToDelete as $file) {
                    unlink($file);
                }
            }
        }

        return [
            'filesToDelete' => $filesToDelete,
            'totalSize' => $totalSize,
            'dryRun' => $dryRun
        ];
    }

    private function confirmDeletion($showPath, $files, $size) {
        // In a web context, this would typically be handled via an API endpoint
        // that would show the confirmation dialog in the UI
        return true;
    }
}


