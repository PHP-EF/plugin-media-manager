<?php
// **
// USED TO DEFINE PLUGIN INFORMATION & CLASS
// **

// PLUGIN INFORMATION - This should match what is in plugin.json
$GLOBALS['plugins']['Plex TV Cleaner'] = [
    'name' => 'Plex TV Cleaner',
    'author' => 'tinytechlabuk',
    'category' => 'Media Management',
    'link' => 'https://github.com/tinytechlabuk/php-ef-plex-tv-cleaner',
    'version' => '1.0.5',
    'image' => 'logo.png',
    'settings' => true,
    'api' => '/api/plugin/plextvcleaner/settings',
];

class plextvcleaner extends ib {
    private $pluginConfig;
    private $sql;

    public function __construct() {
        parent::__construct();
        $this->loadConfig();
        $dbFile = dirname(__DIR__,2). DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'plextvcleaner.db';
        $this->sql = new PDO("sqlite:$dbFile");
        $this->sql->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->hasDB();
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
            'Plugin' => array(
                $this->settingsOption('auth', 'ACL-PLEXTVCLEANER', ['label' => 'Plex TV Cleaner Plugin Access ACL'])
            ),
            'Tautulli' => array(
                $this->settingsOption('url', 'tautulliUrl', ['label' => 'Tautulli API URL', 'placeholder' => 'http://server:port']),
                $this->settingsOption('password-alt', 'tautulliApiKey', ['label' => 'Tautulli API Key', 'placeholder' => 'Your API Key']),
                $this->settingsOption('input', 'tautulliMonths', ['label' => 'Months to Look Back', 'placeholder' => '12'])
            ),
            'Sonarr' => array(
                $this->settingsOption('url', 'sonarrUrl', ['label' => 'Sonarr API URL', 'placeholder' => 'http://server:port']),
                $this->settingsOption('password-alt', 'sonarrApiKey', ['label' => 'Sonarr API Key', 'placeholder' => 'Your API Key']),
                $this->settingsOption('select', 'sonarrApiVersion', ['label' => 'Sonarr API Version', 'options' => array(array("name" => 'v1', "value" => 'v1'),array("name" => 'v2', "value" => 'v2'),array("name" => 'v3', "value" => 'v3'))]),
                $this->settingsOption('input-multiple', 'tvExcludeFolders', ['label' => 'TV Shows to Exclude', 'values' => $excludeFolders, 'text' => 'Add'])
            ),
            'Cleanup' => array(
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
            'Cron Jobs' => array(
                $this->settingsOption('title', 'sectionTitle', ['text' => 'Sonarr & Tautulli Synchronisation']),
                $this->settingsOption('cron', 'sonarrAndTautulliSyncronisationSchedule', ['label' => 'Synchronisation Schedule', 'placeholder' => '*/60 * * * *']),
                $this->settingsOption('test', '/api/plugin/plextvcleaner/combined/tvshows/update', ['label' => 'Synchronise Now', 'text' => 'Run', 'Method' => 'POST'])
            )
        );
    }

    private function loadConfig() {
        $this->pluginConfig = $this->config->get('Plugins', 'Plex TV Cleaner');
        $this->pluginConfig['tautulliMonths'] = $this->pluginConfig['tautulliMonths'] ?? 12;
        $this->pluginConfig['episodesToKeep'] = $this->pluginConfig['episodesToKeep'] ?? 3;
        $this->pluginConfig['reportOnly'] = $this->pluginConfig['reportOnly'] ?? true;
        $this->pluginConfig['promptForFolderDeletion'] = $this->pluginConfig['promptForFolderDeletion'] ?? true;
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
    // DATABASE
    // **

	// Check if Database & Tables Exist
	private function hasDB() {
		if ($this->sql) {
			try {
				// Query to check if both tables exist
				$result = $this->sql->query("SELECT name FROM sqlite_master WHERE type='table' AND name IN ('tvshows')");
				$tables = $result->fetchAll(PDO::FETCH_COLUMN);
			
				if (in_array('tvshows', $tables)) {
					return true;
				} else {
					$this->createPlexTVCleanerTables();
				}
			} catch (PDOException $e) {
				$this->api->setAPIResponse("Error",$e->getMessage());
				return false;
			}
		} else {
			$this->api->setAPIResponse("Error","Database Not Initialized");
			return false;
		}
	}

	// Create Plex TV Cleaner Tables
	private function createPlexTVCleanerTables() {
		$this->sql->exec("CREATE TABLE IF NOT EXISTS tvshows (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			title TEXT UNIQUE,
			monitored BOOLEAN,
			status TEXT,
			matchStatus TEXT,
			seasonCount INTEGER,
			episodeCount INTEGER,
			episodesDownloadedPercentage INTEGER,
            sizeOnDisk INTEGER,
            seriesType TEXT,
            last_played INTEGER,
            added TEXT,
			play_count INTEGER,
            library TEXT,
            library_id INTEGER,
            path TEXT,
            rootFolder TEXT,
            titleSlug TEXT,
            tvDbId INTEGER,
            ratingKey INTEGER
		)");
	}

    public function updateTVShowTable() {
        $Shows = $this->queryAndMatchSonarrAndTautulli();
        if ($Shows) {
            $InsertPrepare = 'INSERT INTO tvshows (title, monitored, status, matchStatus, seasonCount, episodeCount, episodesDownloadedPercentage, sizeOnDisk, seriesType, last_played, added, play_count, library, library_id, path, rootFolder, titleSlug, tvDbId, ratingKey) VALUES (:title, :monitored, :status, :matchStatus, :seasonCount, :episodeCount, :episodesDownloadedPercentage, :sizeOnDisk, :seriesType, :last_played, :added, :play_count, :library, :library_id, :path, :rootFolder, :titleSlug, :tvDbId, :ratingKey)';
            $UpdatePrepare = 'UPDATE tvshows SET monitored = :monitored, status = :status, matchStatus = :matchStatus, seasonCount = :seasonCount, episodeCount = :episodeCount, episodesDownloadedPercentage = :episodesDownloadedPercentage, sizeOnDisk = :sizeOnDisk, seriesType = :seriesType, last_played = :last_played, added = :added, play_count = :play_count, library = :library, library_id = :library_id, path = :path, rootFolder = :rootFolder, titleSlug = :titleSlug, tvDbId = :tvDbId, ratingKey = :ratingKey WHERE title = :title';
        
            foreach ($Shows as $Show) {
                try {
                    // Check if the show exists
                    $stmt = $this->sql->prepare('SELECT COUNT(*) FROM tvshows WHERE title = :title');
                    $stmt->execute([':title' => $Show['title']]);
                    $exists = $stmt->fetchColumn();
            
                    if ($exists) {
                        // Update existing record
                        $stmt = $this->sql->prepare($UpdatePrepare);
                    } else {
                        // Insert new record
                        $stmt = $this->sql->prepare($InsertPrepare);
                    }
            
                    // Bind parameters and execute
                    $stmt->execute([
                        ':title' => $Show['title'],
                        ':monitored' => $Show['monitored'],
                        ':status' => $Show['status'],
                        ':matchStatus' => $Show['MatchStatus'],
                        ':seasonCount' => $Show['statistics']['seasonCount'],
                        ':episodeCount' => $Show['statistics']['episodeCount'],
                        ':episodesDownloadedPercentage' => $Show['statistics']['percentOfEpisodes'],
                        ':sizeOnDisk' => $Show['statistics']['sizeOnDisk'],
                        ':seriesType' => $Show['seriesType'],
                        ':last_played' => $Show['Tautulli']['last_played'] ?? null,
                        ':added' => $Show['added'],
                        ':play_count' => $Show['Tautulli']['play_count'] ?? null,
                        ':library' => $Show['Tautulli']['library_name'] ?? null,
                        ':library_id' => $Show['Tautulli']['section_id'] ?? null,
                        ':path' => $Show['path'],
                        ':rootFolder' => $Show['rootFolderPath'],
                        ':titleSlug' => $Show['titleSlug'],
                        ':tvDbId' => $Show['tvdbId'],
                        ':ratingKey' => $Show['Tautulli']['rating_key'] ?? null
                    ]);
                } catch (Exception $e) {
                    $this->logging->writeLog("PlexTVCleaner","Failed to update the TV Shows Table.","error",$e);
                    return array(
                        'result' => 'Error',
                        'message' => $e
                    );
                }
            }

            $this->logging->writeLog("PlexTVCleaner","Synchronised with Sonarr & Tautulli Successfully.","info");
            return array(
                'result' => 'Success',
                'message' => 'Successfully updated TV Show Table.'
            );
        } else {
            $this->logging->writeLog("PlexTVCleaner","Failed to retrieve a list of TV Shows.","error");
        }
    }

    public function getTVShowTable() {
        $stmt = $this->sql->prepare('SELECT * FROM tvshows');
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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

    private function normalizeTitle($title) {
        // Remove special characters and convert to lowercase
        return strtolower(preg_replace('/[^a-zA-Z0-9\s]/', '', $title));
    }

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
            $this->api->setAPIResponse('Error', 'Tautulli or Sonarr did not respond as expected.', null, []);
            return false;
        }
    }
}


