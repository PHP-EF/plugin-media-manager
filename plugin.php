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
    'version' => '1.0.3',
    'image' => 'logo.png',
    'settings' => true,
    'api' => '/api/plugin/plextvcleaner/settings',
];

class plextvcleaner extends ib {
    private $rootFolder;
    private $excludeFolders;
    private $tautulliApi;
    private $tautulliApiKey;
    private $tautulliMonths;
    private $tvShowsEpisodeCount;
    private $reportOnly;
    private $promptForFolderDeletion;

    public function __construct() {
        parent::__construct();
        $this->loadConfig();
    }

    private function loadConfig() {
        $config = $this->config->get('Plugins', 'Plex TV Cleaner');
        $this->rootFolder = $config['Root Folder'] ?? '';
        $this->excludeFolders = $config['Exclude Folders'] ?? [];
        $this->tautulliApi = $config['Tautulli API URL'] ?? '';
        $this->tautulliApiKey = $config['Tautulli API Key'] ?? '';
        $this->tautulliMonths = $config['Tautulli Months'] ?? 12;
        $this->tvShowsEpisodeCount = $config['Episodes to Keep'] ?? 3;
        $this->reportOnly = $config['Report Only'] ?? true;
        $this->promptForFolderDeletion = $config['Prompt For Folder Deletion'] ?? true;
    }

    public function _pluginGetSettings() {
        $excludeFolders = [];
        if (!empty($this->excludeFolders)) {
            foreach ($this->excludeFolders as $folder) {
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
                $this->settingsOption('input', 'Root Folder', ['label' => 'Plex TV Root Folder', 'placeholder' => '\\\\SERVER\\Plex\\TV', 'value' => $this->rootFolder]),
                $this->settingsOption('input-multiple', 'Exclude Folders', ['label' => 'TV Shows to Exclude', 'values' => $excludeFolders, 'text' => 'Add'])
            ),
            'Tautulli Settings' => array(
                $this->settingsOption('url', 'Tautulli API URL', ['label' => 'Tautulli API URL', 'placeholder' => 'http://server:port/api/v2', 'value' => $this->tautulliApi]),
                $this->settingsOption('input', 'Tautulli API Key', ['label' => 'Tautulli API Key', 'placeholder' => 'Your API Key', 'value' => $this->tautulliApiKey]),
                $this->settingsOption('input', 'Tautulli Months', ['label' => 'Months to Look Back', 'placeholder' => '12', 'value' => $this->tautulliMonths])
            ),
            'Cleanup Settings' => array(
                $this->settingsOption('input', 'Episodes to Keep', ['label' => 'Number of Episodes to Keep', 'placeholder' => '3', 'value' => $this->tvShowsEpisodeCount]),
                $this->settingsOption('select', 'Report Only', ['label' => 'Report Only Mode (No Deletions)', 'options' => [
                    ['name' => 'Yes', 'value' => 'true'],
                    ['name' => 'No', 'value' => 'false']
                ], 'value' => $this->reportOnly ? 'true' : 'false']),
                $this->settingsOption('select', 'Prompt For Folder Deletion', ['label' => 'Prompt Before Folder Deletion', 'options' => [
                    ['name' => 'Yes', 'value' => 'true'],
                    ['name' => 'No', 'value' => 'false']
                ], 'value' => $this->promptForFolderDeletion ? 'true' : 'false'])
            ),
        );
    }

    // Tautuilli API Wrapper
    public function queryTautulliAPI($Method, $Cmd, $Data = "") {
        if (!$this->tautulliApi) {
            $this->api->setAPIResponse('Error','Tautulli URL Missing');
            return false;
        }

        if (!$this->tautulliApiKey) {
            $this->api->setAPIResponse('Error','Ansible API Key Missing');
            return false;
        }

        $Url = $this->tautulliApi."/api/v2?cmd=".$Cmd;
        $Url = $Url.'&apikey='.$this->tautulliApiKey;
        return $this->getAPIResults($Method,$Url,$Data);
    }

    private function buildTautulliAPIQuery($Cmd,$Params = []) {
        $QueryParams = http_build_query($Params);
        if ($QueryParams) {
            $Query = $Cmd.'&'.$QueryParams;
            return $Query;
        } else {
            return $Cmd;
        }
    }

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
                    $this->api->setAPIResponse('Error','Tautulli API Key incorrect or expired');
                    $this->logging->writeLog("Ansible","Error. Tautulli API Key incorrect or expired.","error");
                    break;
                    case 404:
                    $this->api->setAPIResponse('Error','HTTP 404 Not Found');
                    break;
                    default:
                    $this->api->setAPIResponse('Error','HTTP '.$Result->status_code);
                    break;
                }
            }
        }
        if (isset($Result['response'])) {
            if (isset($Result['response']['data'])) {
                return $Result['response']['data'];
            } else {
                return $Result;
            }
        } else {
            $this->api->setAPIResponse('Warning','No results returned from the API');
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
                $Results = array_merge($Results, $Result['data']);
            } else {
                echo "Warning: Result is not an array\n";
            }
        }
        $this->api->setAPIResponseData($Results);
    }

    // ** OLD STUFF ** //

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
        if (!file_exists($this->rootFolder)) {
            $this->api->setAPIResponse('Error', 'Root folder does not exist');
            return false;
        }
        $shows = [];
        $dir = new DirectoryIterator($this->rootFolder);
        foreach ($dir as $fileinfo) {
            if ($fileinfo->isDir() && !$fileinfo->isDot()) {
                $showName = $fileinfo->getFilename();
                if (!in_array($showName, $this->excludeFolders)) {
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
        if (empty($this->tautulliApi) || empty($this->tautulliApiKey)) {
            return null;
        }

        $url = sprintf(
            '%s?apikey=%s&cmd=get_history&length=1&title=%s',
            rtrim($this->tautulliApi, '/'),
            $this->tautulliApiKey,
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
            $dryRun = $this->reportOnly;
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
        for ($i = $this->tvShowsEpisodeCount; $i < count($episodes); $i++) {
            $filesToDelete[] = $episodes[$i]['path'];
            $totalSize += $episodes[$i]['size'];
        }

        // If not a dry run and not prompting, or if prompting and user confirmed, delete the files
        if (!$dryRun) {
            if (!$this->promptForFolderDeletion || $this->confirmDeletion($showPath, $filesToDelete, $totalSize)) {
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


