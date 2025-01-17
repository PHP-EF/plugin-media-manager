<?php
// Define Custom HTML Widgets
class DownloadQueueWidget implements WidgetInterface {
    private $phpef;

    public function __construct($phpef) {
        $this->phpef = $phpef;
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
                $this->phpef->settingsOption('checkbox', 'uTorrentHideSeeding', ['label' => 'Hide Seeding']),
                $this->phpef->settingsOption('checkbox', 'uTorrentHideCompleted', ['label' => 'Hide Completed']),
                // $this->settingsOption('refresh', 'uTorrentRefresh'),
                $this->phpef->settingsOption('checkbox', 'uTorrentCombine', ['label' => 'Add to combined queue']),
            ]
        ];
        return $SettingsArr;
    }

    public function render() {
        $Config = $this->phpef->config->get('Widgets','uTorrent Queue') ?? [];
        $Auth = $Config['auth'] ?? null;
        $Enabled = $Config['enabled'] ?? false;
        if ($this->phpef->auth->checkAccess($Auth) !== false && $Enabled) {
            return <<<EOF
                <script src="/api/page/plugin/Media Manager/js"></script>
                <div id="homepageOrderdownloader">
                    <script>
                        buildDownloaderCombined("utorrent");
                        homepageDownloader("utorrent", "60000");
                    </script>
                </div>
                <link href="/api/page/plugin/Media Manager/css" rel="stylesheet">
            EOF;
        }
    }
}

// Register Custom HTML Widgets
$phpef->dashboard->registerWidget('uTorrent Queue', new DownloadQueueWidget($phpef));