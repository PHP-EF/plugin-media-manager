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
    'version' => '1.0.0',
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
                $excludeFolders[] = ['name' => $folder, 'value' => $folder];
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
                $this->settingsOption('select-multiple', 'Exclude Folders', ['label' => 'TV Shows to Exclude', 'options' => $excludeFolders])
            ),
            'Tautulli Settings' => array(
                $this->settingsOption('input', 'Tautulli API URL', ['label' => 'Tautulli API URL', 'placeholder' => 'http://server:port/api/v2', 'value' => $this->tautulliApi]),
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

    public function getTvShows() {
        if (!file_exists($this->rootFolder)) {
            return ['error' => 'TV show root folder does not exist'];
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

class plextvcleaner_api extends API {
    public function settings($params = null) {
        $plugin = new plextvcleaner();
        return $plugin->_pluginGetSettings();
    }

    public function shows() {
        $plugin = new plextvcleaner();
        return $plugin->getTvShows();
    }

    public function cleanup($params = null) {
        if (!isset($params['path'])) {
            return ['error' => 'Show path is required'];
        }

        $plugin = new plextvcleaner();
        $dryRun = isset($params['dryRun']) ? filter_var($params['dryRun'], FILTER_VALIDATE_BOOLEAN) : null;
        return $plugin->cleanupShow($params['path'], $dryRun);
    }
}
