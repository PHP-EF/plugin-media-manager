<?php
trait Database {
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
            ],
            '1.0.8' => [],
            '1.0.9' => [],
            '1.1.0' => [],
            '1.1.1' => [],
            '1.1.2' => [],
            '1.1.3' => [],
            '1.1.4' => []
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

                    // Update 'clean' to true for shows that meet cleanup criteria and if cleanup is enabled
                    $cleanupEnabled = $this->pluginConfig['sonarrCleanupEnabled'] ?? false;
                    if ($cleanupEnabled) {
                        // Exclude shows with exclusion tag
                        $exclusionTag = $this->pluginConfig['sonarrCleanupExclusionTag'] ?? [];
                        $tags = $show['tags'] ?? [];
                        if (!in_array($exclusionTag, $tags)) {
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
                        } else {
                            $Show['clean'] = false;
                        }
                    } else {
                        $Show['clean'] = false;
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

    // Function to get TV Shows By Cleanup State
    public function getTVShowsTableByCleanupState($state) {
        $stmt = $this->sql->prepare("SELECT * FROM tvshows WHERE clean = :clean");
        $stmt->execute(['clean' => $state]);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
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

                    // Update 'clean' to true for movies that meet cleanup criteria and if cleanup is enabled
                    $cleanupEnabled = $this->pluginConfig['radarrCleanupEnabled'] ?? false;
                    if ($cleanupEnabled) {
                        // Exclude shows with exclusion tag
                        $exclusionTag = $this->pluginConfig['radarrCleanupExclusionTag'] ?? [];
                        if (!in_array($exclusionTag, $Movie['tags'] ?? [])) {
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
                        } else {
                            $Movie['clean'] = false;
                        }
                    } else {
                        $Movie['clean'] = false;
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
}