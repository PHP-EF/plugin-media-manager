<?php
// Define Custom HTML Widgets
class DownloadQueueWidget implements WidgetInterface {
    private $phpef;
    private $widgetConfig;

    public function __construct($phpef) {
        $this->phpef = $phpef;
        $this->widgetConfig = $this->getWidgetConfig();
    }

    public function settings() {
        $customHTMLQty = 5;
        $SettingsArr = [];
        $SettingsArr['info'] = [
            'name' => 'Download Queues',
            'description' => 'Download Queues Widget',
			'image' => ''
        ];
        $SettingsArr['Settings'] = [
            'Widget Settings' => [
				$this->phpef->settingsOption('enable', 'enabled'),
				$this->phpef->settingsOption('auth', 'auth', ['label' => 'Role Required'])
            ],
            'uTorrent' => [
				$this->phpef->settingsOption('enable', 'utorrentEnabled'),
                $this->phpef->settingsOption('checkbox', 'utorrentHideSeeding', ['label' => 'Hide Seeding']),
                $this->phpef->settingsOption('checkbox', 'utorrentHideCompleted', ['label' => 'Hide Completed']),
                $this->phpef->settingsOption('refresh', 'utorrentRefresh')
            ],
            'NzbGet' => [
				$this->phpef->settingsOption('enable', 'nzbgetEnabled'),
                $this->phpef->settingsOption('refresh', 'nzbgetRefresh')
            ],
            'Sonarr' => [
				$this->phpef->settingsOption('enable', 'sonarrEnabled'),
                $this->phpef->settingsOption('refresh', 'sonarrRefresh')
            ],
            'Radarr' => [
				$this->phpef->settingsOption('enable', 'radarrEnabled'),
                $this->phpef->settingsOption('refresh', 'radarrRefresh')
            ]
        ];
        return $SettingsArr;
    }

    private function getWidgetConfig() {
        $WidgetConfig = $this->phpef->config->get('Widgets','Download Queues') ?? [];
        $WidgetConfig['enabled'] = $WidgetConfig['enabled'] ?? false;
        $WidgetConfig['auth'] = $WidgetConfig['auth'] ?? false;
        $WidgetConfig['utorrentEnabled'] = $WidgetConfig['utorrentEnabled'] ?? false;
        $WidgetConfig['utorrentHideSeeding'] = $WidgetConfig['utorrentHideSeeding'] ?? false;
        $WidgetConfig['utorrentHideCompleted'] = $WidgetConfig['utorrentHideCompleted'] ?? false;
        $WidgetConfig['utorrentRefresh'] = $WidgetConfig['utorrentRefresh'] ?? 60000;
        $WidgetConfig['nzbgetEnabled'] = $WidgetConfig['nzbgetEnabled'] ?? false;
        $WidgetConfig['nzbgetRefresh'] = $WidgetConfig['nzbgetRefresh'] ?? 60000;
        $WidgetConfig['sonarrEnabled'] = $WidgetConfig['sonarrEnabled'] ?? false;
        $WidgetConfig['sonarrRefresh'] = $WidgetConfig['sonarrRefresh'] ?? 60000;
        $WidgetConfig['radarrEnabled'] = $WidgetConfig['radarrEnabled'] ?? false;
        $WidgetConfig['radarrRefresh'] = $WidgetConfig['radarrRefresh'] ?? 60000;
        return $WidgetConfig;
    }

    public function render() {
        if ($this->phpef->auth->checkAccess($this->widgetConfig['auth']) !== false && $this->widgetConfig['enabled']) {
            $scripts = [];
            $timeouts = [
                'utorrent' => $this->widgetConfig['utorrentRefresh'],
                'nzbget' => $this->widgetConfig['nzbgetRefresh'],
                'sonarr' => $this->widgetConfig['sonarrRefresh'],
                'radarr' => $this->widgetConfig['radarrRefresh']
            ];
    
            foreach (['utorrent', 'nzbget', 'sonarr', 'radarr'] as $client) {
                if ($this->widgetConfig[$client . 'Enabled']) {
                    $scripts[] = "buildDownloaderCombined(\"$client\");";
                    $scripts[] = "homepageDownloader(\"$client\", \"{$timeouts[$client]}\");";
                }
            }
    
            $scripts = implode("\n", $scripts);
    
            return <<<EOF
                <div id="homepageOrderdownloader">
                    <script>
                        appendScript({ src: "/api/page/plugin/Media Manager/js" })
                        .then(() => {
                            $scripts
                        })
                        .catch(error => console.error(error));
                    </script>
                </div>
                <link href="/api/page/plugin/Media Manager/css" rel="stylesheet">
            EOF;
        }
    }
}
// Register Custom HTML Widgets
$phpef->dashboard->registerWidget('Download Queues', new DownloadQueueWidget($phpef));