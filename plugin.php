<?php
// **
// USED TO DEFINE PLUGIN INFORMATION & CLASS
// **

// PLUGIN INFORMATION - This should match what is in plugin.json
$GLOBALS['plugins']['Media Manager'] = [
    'name' => 'Media Manager',
    'author' => 'tinytechlabuk',
    'category' => 'Media Management',
    'link' => 'https://github.com/tinytechlabuk/php-ef-media-manager',
    'version' => '1.0.8',
    'image' => 'logo.png',
    'settings' => true,
    'api' => '/api/plugin/mediamanager/settings',
];

class MediaManager extends ib {
    private $pluginConfig;
    private $sql;
    private $sqlHelper;

    public function __construct() {
        parent::__construct();
        $this->loadConfig();
        $dbFile = dirname(__DIR__,2). DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'MediaManager.db';
        $this->sql = new PDO("sqlite:$dbFile");
        $this->sql->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->hasDB();
        $this->sqlHelper = new dbHelper($this->sql);
        $this->checkDB();
    }

    public function _pluginGetSettings() {
        $SonarrTags = $this->getSonarrTags() ?: [];
        $RadarrTags = $this->getRadarrTags() ?: [];
        $AppendNone = array(
            [
                "name" => 'None',
                "value" => ''
            ]
        );
        $SonarrTagOptions = array_merge($AppendNone,array_map(function($item) {
            return [
                "name" => $item['label'],
                "value" => $item['id']
            ];
        }, $SonarrTags));

        $RadarrTagOptions = array_merge($AppendNone,array_map(function($item) {
            return [
                "name" => $item['label'],
                "value" => $item['id']
            ];
        }, $RadarrTags));

        return array(
            'About' => array(
                $this->settingsOption('notice', '', ['title' => 'Information', 'body' => '
                <p>This plugin helps manage and clean up TV show folders in your Plex server environment. It integrates with Tautulli to track watched shows and applies custom cleanup rules to maintain a manageable library size.</p>
                <br/>']),
            ),
            'Plugin' => array(
                $this->settingsOption('auth', 'ACL-MEDIAMANAGER', ['label' => 'Media Manager Plugin Access ACL'])
            ),
            'Tautulli' => array(
                $this->settingsOption('url', 'tautulliUrl', ['label' => 'Tautulli API URL', 'placeholder' => 'http://server:port']),
                $this->settingsOption('password-alt', 'tautulliApiKey', ['label' => 'Tautulli API Key', 'placeholder' => 'Your API Key'])
            ),
            'Sonarr' => array(
                $this->settingsOption('url', 'sonarrUrl', ['label' => 'Sonarr API URL', 'placeholder' => 'http://server:port']),
                $this->settingsOption('password-alt', 'sonarrApiKey', ['label' => 'Sonarr API Key', 'placeholder' => 'Your API Key']),
                $this->settingsOption('select', 'sonarrApiVersion', ['label' => 'Sonarr API Version', 'options' => array(array("name" => 'v3', "value" => 'v3'),array("name" => 'v2', "value" => 'v2'),array("name" => 'v1', "value" => 'v1'))]),
                $this->settingsOption('hr'),
                $this->settingsOption('title', 'sonarrThrottlingTitle', ['text' => 'Sonarr Throttling']),
                $this->settingsOption('input', 'sonarrThrottlingSeasonThreshold', ['label' => 'Season Threshold', 'placeholder' => '4']),
                $this->settingsOption('input', 'sonarrThrottlingEpisodeThreshold', ['label' => 'Episode Threshold', 'placeholder' => '40']),
                $this->settingsOption('input', 'sonarrThrottlingEpisodeScanQty', ['label' => 'Amount of episodes to perform initial scan for', 'placeholder' => '10']),
                $this->settingsOption('select', 'sonarrThrottlingTag', ['label' => 'Tag to use for Throttled TV Shows', 'options' => $SonarrTagOptions]),
                $this->settingsOption('password-alt', 'sonarrThrottlingAuthToken', ['label' => 'Auth Token for Webhooks']),
                $this->settingsOption('hr'),
                $this->settingsOption('title', 'sonarrCleanupTitle', ['text' => 'Sonarr Cleanup']),
                $this->settingsOption('select', 'sonarrCleanupExclusionTag', ['label' => 'Tag to exclude TV Shows from Cleanup', 'options' => $SonarrTagOptions]),
                $this->settingsOption('input', 'sonarrCleanupEpisodesToKeep', ['label' => 'Number of Episodes to Keep', 'placeholder' => '10']),
                $this->settingsOption('input', 'sonarrCleanupMaxAge', ['label' => 'Maximum days before TV Show is cleaned up', 'placeholder' => '180']),
                $this->settingsOption('select', 'sonarrReportOnly', ['label' => 'Report Only Mode (No Deletions)', 'options' => [
                    ['name' => 'Yes', 'value' => 'true'],
                    ['name' => 'No', 'value' => 'false']
                ]])
            ),
            'Radarr' => array(
                $this->settingsOption('url', 'radarrUrl', ['label' => 'Radarr API URL', 'placeholder' => 'http://server:port']),
                $this->settingsOption('password-alt', 'radarrApiKey', ['label' => 'Radarr API Key', 'placeholder' => 'Your API Key']),
                $this->settingsOption('select', 'radarrApiVersion', ['label' => 'Radarr API Version', 'options' => array(array("name" => 'v3', "value" => 'v3'),array("name" => 'v2', "value" => 'v2'),array("name" => 'v1', "value" => 'v1'))]),
                $this->settingsOption('hr'),
                $this->settingsOption('title', 'radarrCleanupTitle', ['text' => 'Radarr Cleanup']),
                $this->settingsOption('select', 'radarrReportOnly', ['label' => 'Report Only Mode (No Deletions)', 'options' => [
                    ['name' => 'Yes', 'value' => 'true'],
                    ['name' => 'No', 'value' => 'false']
                ]]),
                $this->settingsOption('select', 'radarrExclusionTag', ['label' => 'Tag to exclude Movies', 'options' => $RadarrTagOptions]),
                $this->settingsOption('input', 'radarrCleanupMaxAge', ['label' => 'Maximum number of days before Movie is cleaned up', 'placeholder' => '1095'])
            ),
            'Cron Jobs' => array(
                $this->settingsOption('title', 'sonarrSectionTitle', ['text' => 'Sonarr & Tautulli Synchronisation']),
                $this->settingsOption('cron', 'sonarrAndTautulliSyncronisationSchedule', ['label' => 'Synchronisation Schedule', 'placeholder' => '*/60 * * * *']),
                $this->settingsOption('test', '/api/plugin/mediamanager/combined/tvshows/update', ['label' => 'Synchronise Now', 'text' => 'Run', 'Method' => 'POST']),
                $this->settingsOption('checkbox', 'removeOrphanedTVShows', ['label' => 'Remove Orphaned Shows on Sync']),
                $this->settingsOption('hr'),
                $this->settingsOption('title', 'radarrSectionTitle', ['text' => 'Radarr & Tautulli Synchronisation']),
                $this->settingsOption('cron', 'radarrAndTautulliSyncronisationSchedule', ['label' => 'Synchronisation Schedule', 'placeholder' => '*/60 * * * *']),
                $this->settingsOption('test', '/api/plugin/mediamanager/combined/movies/update', ['label' => 'Synchronise Now', 'text' => 'Run', 'Method' => 'POST']),
                $this->settingsOption('checkbox', 'removeOrphanedMovies', ['label' => 'Remove Orphaned Movies on Sync'])
            )
        );
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


    // **
    // DATABASE
    // **

	// Check if Database & Tables Exist
	private function hasDB() {
		if ($this->sql) {
			try {
				// Query to check if both tables exist
				$result = $this->sql->query("SELECT name FROM sqlite_master WHERE type='table' AND name IN ('tvshows','movies','options')");
				$tables = $result->fetchAll(PDO::FETCH_COLUMN);
			
				if (in_array('tvshows', $tables) && in_array('movies', $tables) && in_array('options', $tables)) {
					return true;
				} else {
					$this->createMediaManagerTables();
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

    // Initiate Database Migration if required
    private function checkDB() {
        $currentVersion = $this->sqlHelper->getDatabaseVersion();
        $newVersion = $GLOBALS['plugins']['Media Manager']['version'];
        if ($currentVersion < $newVersion) {
            $this->sqlHelper->updateDatabaseSchema($currentVersion, $newVersion, $this->migrationScripts());
        }
    }

    // Database Migration Script(s) for changes between versions
    public function migrationScripts() {
        return [
            '1.0.5' => [],
            '1.0.6' => [],
            '1.0.7' => [
                "ALTER TABLE tvshows ADD COLUMN sonarrId INTEGER", // Add Sonarr Series ID to DB
                "ALTER TABLE movies ADD COLUMN radarrId INTEGER", // Add Radarr Movie ID to DB
            ]
        ];
    }

	// Create Media Manager Tables
	private function createMediaManagerTables() {
		$this->sql->exec("CREATE TABLE IF NOT EXISTS tvshows (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			title TEXT UNIQUE,
			monitored BOOLEAN,
			status TEXT,
			matchStatus TEXT,
			seasonCount INTEGER,
			episodeCount INTEGER,
            episodeFileCount INTEGER,
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
            ratingKey INTEGER,
            tags TEXT,
            sonarrId INTEGER,
            clean BOOLEAN
		)");

        $this->sql->exec("CREATE TABLE IF NOT EXISTS movies (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT UNIQUE,
            monitored BOOLEAN,
            status TEXT,
            matchStatus TEXT,
            hasFile BOOLEAN,
            sizeOnDisk INTEGER,
            last_played INTEGER,
            added TEXT,
            play_count INTEGER,
            library TEXT,
            library_id INTEGER,
            path TEXT,
            rootFolder TEXT,
            titleSlug TEXT,
            imdbId INTEGER,
            ratingKey INTEGER,
            tags TEXT,
            radarrId INTEGER,
            clean BOOLEAN
        )");

        $this->sql->exec("CREATE TABLE IF NOT EXISTS options (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            Key TEXT,
            Value TEXT
        )");

        $this->sql->exec('INSERT INTO options (Key,Value) VALUES ("dbVersion","'.$GLOBALS['plugins']['Media Manager']['version'].'");');
	}

    // Function to update the TV Shows Table (Synchronisation)
    public function updateTVShowTable() {
        $Shows = $this->queryAndMatchSonarrAndTautulli();
        if ($Shows) {
            $InsertPrepare = 'INSERT INTO tvshows (title, monitored, status, matchStatus, seasonCount, episodeCount, episodeFileCount, episodesDownloadedPercentage, sizeOnDisk, seriesType, last_played, added, play_count, library, library_id, path, rootFolder, titleSlug, tvDbId, ratingKey, tags, sonarrId, clean) VALUES (:title, :monitored, :status, :matchStatus, :seasonCount, :episodeCount, :episodeFileCount, :episodesDownloadedPercentage, :sizeOnDisk, :seriesType, :last_played, :added, :play_count, :library, :library_id, :path, :rootFolder, :titleSlug, :tvDbId, :ratingKey, :tags, :sonarrId, :clean)';
            $UpdatePrepare = 'UPDATE tvshows SET monitored = :monitored, status = :status, matchStatus = :matchStatus, seasonCount = :seasonCount, episodeCount = :episodeCount, episodeFileCount = :episodeFileCount, episodesDownloadedPercentage = :episodesDownloadedPercentage, sizeOnDisk = :sizeOnDisk, seriesType = :seriesType, last_played = :last_played, added = :added, play_count = :play_count, library = :library, library_id = :library_id, path = :path, rootFolder = :rootFolder, titleSlug = :titleSlug, tvDbId = :tvDbId, ratingKey = :ratingKey, tags = :tags, sonarrId = :sonarrId, clean = :clean WHERE title = :title';
    
            // Track titles in $Shows
            $showTitles = array_column($Shows, 'title');
    
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

                    // Update 'clean' to true for shows that meet cleanup criteria
                    try {
                        // Default to false
                        $Show['clean'] = false;

                        // Check if it has been added longer than X days ago
                        $DateAdded = new DateTime($Show['added']);
                        $LastPlayedEpoch = false;
                        if (isset($Show['Tautulli'])) {
                            if (isset($Show['Tautulli']['last_played'])) {
                                $LastPlayedEpoch = $Show['Tautulli']['last_played'] ?? false;
                            }
                        }
                        
                        if ($this->isDateOlderThanXDays($DateAdded,$this->pluginConfig['sonarrCleanupMaxAge'])) {
                            if ($LastPlayedEpoch) {
                                // Check if it has been watched in longer than X days ago
                                $LastPlayed = new DateTime("@$LastPlayedEpoch");
                                if ($this->isDateOlderThanXDays($LastPlayed,$this->pluginConfig['sonarrCleanupMaxAge'])) {
                                    $Show['clean'] = true;
                                }
                            } else {
                                $Show['clean'] = true;
                            }
                        }
                    } catch (Exception $e) {
                        $this->logging->writeLog("MediaManager","Failed to update cleanup status for TV Shows.","error",$e);
                        return array(
                            'result' => 'Error',
                            'message' => $e
                        );
                    }
    
                    // Bind parameters and execute
                    $stmt->execute([
                        ':title' => $Show['title'],
                        ':monitored' => $Show['monitored'],
                        ':status' => $Show['status'],
                        ':matchStatus' => $Show['MatchStatus'],
                        ':seasonCount' => $Show['statistics']['seasonCount'],
                        ':episodeCount' => $Show['statistics']['episodeCount'],
                        ':episodeFileCount' => $Show['statistics']['episodeFileCount'],
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
                        ':ratingKey' => $Show['Tautulli']['rating_key'] ?? null,
                        ':tags' => implode(',',$Show['tags']) ?? null,
                        ':sonarrId' => $Show['id'],
                        ':clean' => $Show['clean'] ?? false
                    ]);
                } catch (Exception $e) {
                    $this->logging->writeLog("MediaManager","Failed to update the TV Shows Table.","error",$e);
                    return array(
                        'result' => 'Error',
                        'message' => $e
                    );
                }
            }
    
            // Update 'MatchStatus' to 'Orphaned' for shows not in $Shows but present in the database
            try {
                $removeOrphaned = $this->config->get('Plugins','Media Manager')['removeOrphanedTVShows'] ?? false;
                if ($removeOrphaned) {
                    $stmt = $this->sql->prepare('DELETE FROM tvshows WHERE title NOT IN (' . implode(',', array_fill(0, count($showTitles), '?')) . ')');
                    $stmt->execute($showTitles);
                } else {
                    $stmt = $this->sql->prepare('UPDATE tvshows SET matchStatus = "Orphaned" WHERE title NOT IN (' . implode(',', array_fill(0, count($showTitles), '?')) . ')');
                    $stmt->execute($showTitles);
                }
            } catch (Exception $e) {
                $this->logging->writeLog("MediaManager","Failed to update orphaned TV Shows.","error",$e);
                return array(
                    'result' => 'Error',
                    'message' => $e
                );
            }
    
            $this->logging->writeLog("MediaManager","Synchronised with Sonarr & Tautulli Successfully.","info");
            return array(
                'result' => 'Success',
                'message' => 'Successfully updated TV Show Table.'
            );
        } else {
            $this->logging->writeLog("MediaManager","Failed to retrieve a list of TV Shows.","error");
        }
    }

    // Function to get the TV Shows Table
    public function getTVShowsTable($Params) {
        // Searching
        if (!empty($params['search'])) {
            $query .= ' AND (title LIKE :search OR status LIKE :search)';
        }
        $SearchColumns = [
            'title',
            'status',
            'matchStatus',
            'seriesType',
            'library'
        ];
        return $this->sqlHelper->queryDBWithParams('tvshows',$Params,$SearchColumns);
    }

    // Function to get the total number of TV Shows
    public function getTotalTVShows() {
        $stmt = $this->sql->prepare('SELECT COUNT(*) as total FROM tvshows');
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'];
    }

    // Function to get TV Shows By Tag
    public function getTVShowsTableByTag($TagID,$Type = 'include') {
        switch($Type) {
            case 'include':
                $stmt = $this->sql->prepare("SELECT * FROM tvshows WHERE ',' || tags || ',' LIKE '%,' || '".$TagID."' || ',%';");
                break;
            case 'exclude':
                $stmt = $this->sql->prepare("SELECT * FROM tvshows WHERE ',' || tags || ',' NOT LIKE '%,' || '".$TagID."' || ',%';");
                break;
        }
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    // Function to get TV Shows By tvDbId
    public function getTVShowsTableByTvDbId($TvDbId) {
        $stmt = $this->sql->prepare("SELECT * FROM tvshows WHERE tvDbId = :tvDbId");
        $stmt->execute(['tvDbId' => $TvDbId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }
    
    // Function to update the Movies Table (Synchronisation)
    public function updateMoviesTable() {
        $Movies = $this->queryAndMatchRadarrAndTautulli();
        if ($Movies) {
            $InsertPrepare = 'INSERT INTO movies (title, monitored, status, matchStatus, hasFile, sizeOnDisk, last_played, added, play_count, library, library_id, path, rootFolder, titleSlug, imdbId, ratingKey, tags, radarrId, clean) VALUES (:title, :monitored, :status, :matchStatus, :hasFile, :sizeOnDisk, :last_played, :added, :play_count, :library, :library_id, :path, :rootFolder, :titleSlug, :imdbId, :ratingKey, :tags, :radarrId, :clean)';
            $UpdatePrepare = 'UPDATE movies SET monitored = :monitored, status = :status, matchStatus = :matchStatus, hasFile = :hasFile, sizeOnDisk = :sizeOnDisk, last_played = :last_played, added = :added, play_count = :play_count, library = :library, library_id = :library_id, path = :path, rootFolder = :rootFolder, titleSlug = :titleSlug, imdbId = :imdbId, ratingKey = :ratingKey, tags = :tags, radarrId = :radarrId, clean = :clean WHERE title = :title';
    
            // Track titles in $Movies
            $movieTitles = array_column($Movies, 'title');
    
            foreach ($Movies as $Movie) {
                try {
                    // Check if the show exists
                    $stmt = $this->sql->prepare('SELECT COUNT(*) FROM movies WHERE title = :title');
                    $stmt->execute([':title' => $Movie['title']]);
                    $exists = $stmt->fetchColumn();
    
                    if ($exists) {
                        // Update existing record
                        $stmt = $this->sql->prepare($UpdatePrepare);
                    } else {
                        // Insert new record
                        $stmt = $this->sql->prepare($InsertPrepare);
                    }

                    // Update 'clean' to true for movies that meet cleanup criteria
                    try {
                        // Default to false
                        $Movie['clean'] = false;

                        // Check if it has been added longer than X days ago
                        $DateAdded = new DateTime($Movie['added']);
                        $LastPlayedEpoch = false;
                        if (isset($Movie['Tautulli'])) {
                            if (isset($Movie['Tautulli']['last_played'])) {
                                $LastPlayedEpoch = $Movie['Tautulli']['last_played'] ?? false;
                            }
                        }
                        
                        if ($this->isDateOlderThanXDays($DateAdded,$this->pluginConfig['radarrCleanupMaxAge'])) {
                            if ($LastPlayedEpoch) {
                                // Check if it has been watched in longer than X days ago
                                $LastPlayed = new DateTime("@$LastPlayedEpoch");
                                if ($this->isDateOlderThanXDays($LastPlayed,$this->pluginConfig['radarrCleanupMaxAge'])) {
                                    $Movie['clean'] = true;
                                }
                            } else {
                                $Movie['clean'] = true;
                            }
                        }
                    } catch (Exception $e) {
                        $this->logging->writeLog("MediaManager","Failed to update cleanup status for Movies.","error",$e);
                        return array(
                            'result' => 'Error',
                            'message' => $e
                        );
                    }
    
                    // Bind parameters and execute
                    $stmt->execute([
                        ':title' => $Movie['title'],
                        ':monitored' => $Movie['monitored'],
                        ':status' => $Movie['status'],
                        ':matchStatus' => $Movie['MatchStatus'],
                        ':hasFile' => $Movie['hasFile'],
                        ':sizeOnDisk' => $Movie['statistics']['sizeOnDisk'],
                        ':last_played' => $Movie['Tautulli']['last_played'] ?? null,
                        ':added' => $Movie['added'],
                        ':play_count' => $Movie['Tautulli']['play_count'] ?? null,
                        ':library' => $Movie['Tautulli']['library_name'] ?? null,
                        ':library_id' => $Movie['Tautulli']['section_id'] ?? null,
                        ':path' => $Movie['path'],
                        ':rootFolder' => $Movie['rootFolderPath'],
                        ':titleSlug' => $Movie['titleSlug'],
                        ':imdbId' => $Movie['imdbId'] ?? null,
                        ':ratingKey' => $Movie['Tautulli']['rating_key'] ?? null,
                        ':tags' => implode(',',$Movie['tags']) ?? null,
                        ':radarrId' => $Movie['id'],
                        ':clean' => $Movie['clean'] ?? false
                    ]);
                } catch (Exception $e) {
                    $this->logging->writeLog("MediaManager","Failed to update the Movies Table.","error",$e);
                    return array(
                        'result' => 'Error',
                        'message' => $e
                    );
                }
            }
    
            // Update 'MatchStatus' to 'Orphaned' for shows not in $Shows but present in the database
            try {
                $removeOrphaned = $this->config->get('Plugins','Media Manager')['removeOrphanedMovies'] ?? false;
                if ($removeOrphaned) {
                    $stmt = $this->sql->prepare('DELETE FROM movies WHERE title NOT IN (' . implode(',', array_fill(0, count($movieTitles), '?')) . ')');
                    $stmt->execute($movieTitles);
                } else {
                    $stmt = $this->sql->prepare('UPDATE movies SET matchStatus = "Orphaned" WHERE title NOT IN (' . implode(',', array_fill(0, count($movieTitles), '?')) . ')');
                    $stmt->execute($movieTitles);
                }
            } catch (Exception $e) {
                $this->logging->writeLog("MediaManager","Failed to update orphaned Movies.","error",$e);
                return array(
                    'result' => 'Error',
                    'message' => $e
                );
            }
    
            $this->logging->writeLog("MediaManager","Synchronised with Radarr & Tautulli Successfully.","info");
            return array(
                'result' => 'Success',
                'message' => 'Successfully updated Movies Table.'
            );
        } else {
            $this->logging->writeLog("MediaManager","Failed to retrieve a list of Movies.","error");
        }
    }

    // Function to get the Movies Table
    public function getMoviesTable($Params) {
        // Searching
        if (!empty($params['search'])) {
            $query .= ' AND (title LIKE :search OR status LIKE :search)';
        }
        $SearchColumns = [
            'title',
            'status',
            'matchStatus',
            'library'
        ];
        return $this->sqlHelper->queryDBWithParams('movies',$Params,$SearchColumns);
    }

    // Function to get the total number of Movies
    public function getTotalMovies() {
        $stmt = $this->sql->prepare('SELECT COUNT(*) as total FROM movies');
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'];
    }

    // Function to get TV Shows By Tag
    public function getMoviesTableByTag($TagID,$Type = 'include') {
        switch($Type) {
            case 'include':
                $stmt = $this->sql->prepare("SELECT * FROM movies WHERE ',' || tags || ',' LIKE '%,' || '".$TagID."' || ',%';");
                break;
            case 'exclude':
                $stmt = $this->sql->prepare("SELECT * FROM movies WHERE ',' || tags || ',' NOT LIKE '%,' || '".$TagID."' || ',%';");
                break;
        }
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
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


    // **
    // *Arr Shared Functions
    // **

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



    // **
    // SONARR
    // **

    // Sonarr API Wrapper
    public function querySonarrAPI($Method, $Uri, $Data = "", $Params = []) {
        if (!isset($this->pluginConfig['sonarrUrl']) || empty($this->pluginConfig['sonarrUrl'])) {
            $this->api->setAPIResponse('Error','Sonarr URL Missing');
            return false;
        }
        if (!isset($this->pluginConfig['sonarrApiKey']) || empty($this->pluginConfig['sonarrApiKey'])) {
            $this->api->setAPIResponse('Error','Sonarr API Key Missing');
            return false;
        }
        $Params['apikey'] = $this->pluginConfig['sonarrApiKey'];
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

    // Function to query list of TV Shows from Sonarr by Series ID
    public function updateSonarrTVShow($data) {
        if ($data['id']) {
            $Result = $this->querySonarrAPI('PUT','series/'.$data['id'],$data);
            return $Result;
        } else {
            $this->api->setAPIResponse('Error','id missing from sonarr series data');
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
    // RADARR
    // **

    // Radarr API Wrapper
    public function queryRadarrAPI($Method, $Uri, $Data = "", $Params = []) {
        if (!isset($this->pluginConfig['radarrUrl']) || empty($this->pluginConfig['radarrUrl'])) {
            $this->api->setAPIResponse('Error','Radarr URL Missing');
            return false;
        }
        if (!isset($this->pluginConfig['radarrApiKey']) || empty($this->pluginConfig['radarrApiKey'])) {
            $this->api->setAPIResponse('Error','Radarr API Key Missing');
            return false;
        }
        $Params['apikey'] = $this->pluginConfig['radarrApiKey'];
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
        return $Result;
    }

    public function getRadarrTags() {
        $Result = $this->queryRadarrAPI('GET','tag');
        return $Result;
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
            $this->api->setAPIResponse('Error', 'Tautulli or Sonarr did not respond as expected.', null, []);
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
                $this->api->setAPIResponse('Error', 'Tautulli or Radarr did not respond as expected.', null, []);
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

			## Search TV Shows Table for matching tvDbId
			$SonarrSeriesFromDB = $this->getTVShowsTableByTvDbId($request['tvdbId']);

            ## Retrieve Series from Sonarr
            $SonarrSeries = $this->getSonarrTVShowById($SonarrSeriesFromDB['sonarrId']);

			## Check if Sonarr Object Exists
			if (isset($SonarrSeries)) {
                ## Check if TV Show is tagged as Throttled within Sonarr
                if (!in_array($ThrottledTag,$SonarrSeries['tags'])) {
                    $this->api->setAPIResponseMessage('TV Show is not throttled');
                    $this->logging->writeLog("SonarrThrottling","Tautulli Webhook: TV Show is not throttled: ".$SonarrSeriesFromDB['title'],"info",$request);
                    return false;
                }

                $Episodes = $this->getSonarrEpisodesBySeriesId($SonarrSeriesFromDB['sonarrId']);

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
                            
                            $Response = 'Search request sent for: '.$SonarrSeriesFromDB['title'].' - S'.$Episode['seasonNumber'].'E'.$Episode['episodeNumber'].' - '.$Episode['title'];
                            $this->api->setAPIResponseMessage($Response);
                            $this->logging->writeLog("SonarrThrottling","Tautulli Webhook: Search Request Sent","info",[$Response]);
                            return true;
                        } else {
                            $Response = 'Failed to send search request for: '.$SonarrSeriesFromDB['title'].' - S'.$Episode['seasonNumber'].'E'.$Episode['episodeNumber'].' - '.$Episode['title'];
                            $this->api->setAPIResponse('Error',$Response);
                            $this->logging->writeLog("SonarrThrottling","Tautulli Webhook: Failed to send Search Request","error",[$Response]);
                            return true;
                        }
					}
				}
                // If no more episodes available (i.e show is full), mark as monitored and unthrottled.
				if (empty($MoreEpisodesAvailable)) {
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
                        $Response = 'Failed to find series in Sonarr: '.$SonarrSeriesFromDB['title'];
                        $this->api->setAPIResponse('Error',$Response);
                        $this->logging->writeLog("SonarrThrottling","Tautulli Webhook: Failed to find series in Sonarr","error",[$Response]);
                        return false;
                    }
				}
			} else {
                $this->api->setAPIResponse('Error', 'Sonarr ID Missing From Database.');
                $this->logging->writeLog("SonarrThrottling","Sonarr ID Missing From Database: ".$SonarrSeriesFromDB['title'],"error");
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


