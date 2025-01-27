<?php
// Define Custom HTML Widgets
class DownloadQueueWidget implements WidgetInterface {
    private $phpef;
    public $widgetConfig;

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
				$this->phpef->settingsOption('auth', 'auth', ['label' => 'Role Required']),
                $this->phpef->settingsOption('checkbox', 'headerEnabled', ['label' => 'Enable Header', 'attr' => 'checked']),
                $this->phpef->settingsOption('input', 'header', ['label' => 'Header Title', 'placeholder' => 'Download Queues']),
            ],
            'uTorrent' => [
				$this->phpef->settingsOption('enable', 'utorrentEnabled'),
                $this->phpef->settingsOption('checkbox', 'utorrentHideSeeding', ['label' => 'Hide Seeding']),
                $this->phpef->settingsOption('checkbox', 'utorrentHideCompleted', ['label' => 'Hide Completed']),
                $this->phpef->settingsOption('refresh', 'utorrentRefresh')
            ],
            'qBittorrent' => [
				$this->phpef->settingsOption('enable', 'qbittorrentEnabled'),
                $this->phpef->settingsOption('checkbox', 'qbittorrentSeeding', ['label' => 'Hide Seeding']),
                $this->phpef->settingsOption('checkbox', 'qbittorrentCompleted', ['label' => 'Hide Completed']),
                $this->phpef->settingsOption('checkbox', 'qbittorrentReverseSorting', ['label' => 'Reverse Sort Order']),
                $this->phpef->settingsOption('refresh', 'qbittorrentRefresh')
            ],
            'NzbGet' => [
				$this->phpef->settingsOption('enable', 'nzbgetEnabled'),
                $this->phpef->settingsOption('refresh', 'nzbgetRefresh')
            ],
            'Sabnzbd' => [
				$this->phpef->settingsOption('enable', 'sabnzbdEnabled'),
                $this->phpef->settingsOption('refresh', 'sabnzbdRefresh')
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
        $WidgetConfig['auth'] = $WidgetConfig['auth'] ?? 'ACL-MEDIAMANAGER';
        $WidgetConfig['utorrentEnabled'] = $WidgetConfig['utorrentEnabled'] ?? false;
        $WidgetConfig['utorrentHideSeeding'] = $WidgetConfig['utorrentHideSeeding'] ?? false;
        $WidgetConfig['utorrentHideCompleted'] = $WidgetConfig['utorrentHideCompleted'] ?? false;
        $WidgetConfig['utorrentRefresh'] = $WidgetConfig['utorrentRefresh'] ?? 60000;
        $WidgetConfig['qbittorrentEnabled'] = $WidgetConfig['qbittorrentEnabled'] ?? false;
        $WidgetConfig['qbittorrentHideSeeding'] = $WidgetConfig['qbittorrentHideSeeding'] ?? false;
        $WidgetConfig['qbittorrentHideCompleted'] = $WidgetConfig['qbittorrentHideCompleted'] ?? false;
        $WidgetConfig['qbittorrentRefresh'] = $WidgetConfig['qbittorrentRefresh'] ?? 60000;
        $WidgetConfig['nzbgetEnabled'] = $WidgetConfig['nzbgetEnabled'] ?? false;
        $WidgetConfig['nzbgetRefresh'] = $WidgetConfig['nzbgetRefresh'] ?? 60000;
        $WidgetConfig['sabnzbdEnabled'] = $WidgetConfig['sabnzbdEnabled'] ?? false;
        $WidgetConfig['sabnzbdRefresh'] = $WidgetConfig['sabnzbdRefresh'] ?? 60000;
        $WidgetConfig['sonarrEnabled'] = $WidgetConfig['sonarrEnabled'] ?? false;
        $WidgetConfig['sonarrRefresh'] = $WidgetConfig['sonarrRefresh'] ?? 60000;
        $WidgetConfig['radarrEnabled'] = $WidgetConfig['radarrEnabled'] ?? false;
        $WidgetConfig['radarrRefresh'] = $WidgetConfig['radarrRefresh'] ?? 60000;
        $WidgetConfig['headerEnabled'] = $this->widgetConfig['headerEnabled'] ?? true;
        $WidgetConfig['header'] = $this->widgetConfig['header'] ?? 'Download Queues';
        return $WidgetConfig;
    }

    public function render() {
        if ($this->phpef->auth->checkAccess($this->widgetConfig['auth']) !== false && $this->widgetConfig['enabled']) {
            $scripts = [];
            $timeouts = [
                'utorrent' => $this->widgetConfig['utorrentRefresh'],
                'qbittorrent' => $this->widgetConfig['qbittorrentRefresh'],
                'nzbget' => $this->widgetConfig['nzbgetRefresh'],
                'sabnzbd' => $this->widgetConfig['sabnzbdRefresh'],
                'sonarr' => $this->widgetConfig['sonarrRefresh'],
                'radarr' => $this->widgetConfig['radarrRefresh']
            ];
    
            foreach (['utorrent', 'qbittorrent', 'nzbget', 'sabnzbd', 'sonarr', 'radarr'] as $client) {
                if ($this->widgetConfig[$client . 'Enabled']) {
                    $scripts[] = "buildDownloaderCombined(\"$client\");";
                    $scripts[] = "homepageDownloader(\"$client\", \"{$timeouts[$client]}\");";
                }
            }
    
            $scripts = implode("\n", $scripts);
            $output = '';
            if ($this->widgetConfig['headerEnabled']) {
                $QueueHeader = $this->widgetConfig['header'];
                $output = <<<EOF
                <div class="col-md-12 homepage-item-collapse" data-bs-toggle="collapse" href="#downloadQueues-collapse" data-bs-parent="#downloadQueues" aria-expanded="true" aria-controls="downloadQueues-collapse">
                    <h4 class="float-left homepage-item-title"><span lang="en">$QueueHeader</span></h4>
                    <h4 class="float-left">&nbsp;</h4>
                    <hr class="hr-alt ml-2">
                </div>
                <div class="panel-collapse collapse show" id="downloadQueues-collapse" aria-labelledby="downloadQueues-heading" role="tabpanel" aria-expanded="true" style="">
                EOF;
            }

            $output .= <<<EOF
                <div class="card card-rounded pt-3">
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
                </div>
            EOF;

            return $output;
        }
    }
}
// Register Custom HTML Widgets
$phpef->dashboard->registerWidget('Download Queues', new DownloadQueueWidget($phpef));