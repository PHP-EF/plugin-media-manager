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
    'version' => '1.1.0',
    'image' => 'logo.png',
    'settings' => true,
    'api' => '/api/plugin/mediamanager/settings',
];

// Include MediaManager Functions
foreach (glob(__DIR__.'/functions/*.php') as $function) {
    require_once $function; // Include each PHP file
}

class MediaManager extends ib {
    use General,
    Database,
    PlexAuth,
    Tautulli,
    Sonarr,
    Radarr;

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
        $PlexServers = $this->getPlexServers() ?: [];
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
        $PlexServerOptions = array_merge($AppendNone,array_map(function($item) {
            return [
                "name" => $item['name'],
                "value" => $item['machineIdentifier']
            ];
        }, $PlexServers));

        return array(
            'About' => array (
				$this->settingsOption('notice', 'about', ['title' => 'Plugin Information', 'body' => '
                <p>This plugin helps manage and clean up TV show folders in your Plex server environment. It integrates with Sonarr, Radarr & Tautulli to track watched shows where custom cleanup rules are then applied to enable maintaining a manageable library size.</p>
				<p>You can additionally configure Sonarr Throttling, which allows you to specify a threshold for TV Show sizes and throttles downloads accordingly. It works by configuring a webhook in Overseerr and Tautulli to manage TV Show episode downloading based on if episodes are being watched. Shows with seasons/episodes over a configured threshold will be marked as throttled and only the first X number of episodes will be downloaded. Further episodes will only be downloaded when an event is logged in Tautulli. Using this method prevents large TV Shows from being downloaded for nobody to watch them.</p>
				<br/>
				<h3>Tautulli Webhook</h3>
				<p>Configure this Webhook in Tautulli. Using the <code>Playback Start</code> or <code>Watched</code> triggers will provide the best experience.</p>
				<pre><code class="elip hidden-xs pb-1">/api/mediamanager/webhooks/sonarrthrottling/tautulli</code></pre>
				<p>Tautulli JSON Data - This can be customised as long as <b>tvdbId</b> and <b>media_type</b> are present.</p>
				<pre>
{
   "action": "{action}",
    "title": "{title}",
    "username": "{username}",
    "media_type": "{media_type}",
    "tvdbId": "{thetvdb_id}"
}			</pre>
				<p>Tautulli JSON Headers - API Key for Sonarr Throttling Plugin</p>
				<pre>
{
	"authorization": "' . $this->config->get('Plugins','Media Manager')['sonarrThrottlingAuthToken'] . '"
}				</pre>
				<br/>
				<h3>Overseerr Webhook</h3>
				<p>Configure this Webhook in Overseerr</p>
				<pre><code class="elip hidden-xs">/api/mediamanager/webhooks/sonarrthrottling/overseerr</code></pre>
				<br/>
				<p>Overseerr JSON Payload (Default Webhook) - This can be customised as long as <b>media->tvdbId</b> and <b>media->media_type</b> are present</p>
				<pre>
{
    "notification_type": "{{notification_type}}",
    "subject": "{{subject}}",
    "message": "{{message}}",
    "image": "{{image}}",
    "email": "{{notifyuser_email}}",
    "username": "{{notifyuser_username}}",
    "avatar": "{{notifyuser_avatar}}",
    "{{media}}": {
        "media_type": "{{media_type}}",
        "tmdbId": "{{media_tmdbid}}",
        "imdbId": "{{media_imdbid}}",
        "tvdbId": "{{media_tvdbid}}",
        "status": "{{media_status}}",
        "status4k": "{{media_status4k}}"
    },
    "{{extra}}": []
}				</pre>
				<p>Overseerr Authorization Header - API Key for Sonarr Throttling Plugin</p>
				<pre><code>' . $this->config->get('Plugins','Media Manager')['sonarrThrottlingAuthToken'] . '</code></pre>
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
            'Plex' => array(
				$this->settingsOption('js', 'pluginJs', ['src' => '/api/page/plugin/Media Manager/js']),
				$this->settingsOption('js', 'pluginScript', ['script' => "
				function getPlexHeaders(){
					return {
						'Accept': 'application/json',
						'X-Plex-Product': 'PHP-EF',
						'X-Plex-Version': '2.0',
						'X-Plex-Client-Identifier': '".$this->config->get('System','uuid')."',
						'X-Plex-Model': 'Plex OAuth',
						// 'X-Plex-Platform': osName,
						// 'X-Plex-Platform-Version': osVersion,
						// 'X-Plex-Device': browserName,
						// 'X-Plex-Device-Name': browserVersion,
						'X-Plex-Device-Screen-Resolution': window.screen.width + 'x' + window.screen.height,
						'X-Plex-Language': 'en'
					};
				}
				"]),
				$this->settingsOption('password-alt', 'plexToken', ['label' => 'Plex Authentication Token']),
				$this->settingsOption('button', 'getPlexToken', ['label' => 'Get Plex Token', 'text' => 'Retrieve', 'attr' => 'onclick="PlexOAuth(oAuthSuccess,oAuthError, null, \'.modal-body [name=plexToken]\');"']),
				$this->settingsOption('select', 'plexID', ['label' => 'Plex Machine ID', 'options' => $PlexServerOptions]),
				$this->settingsOption('button', 'refreshPlexServers', ['label' => 'Refresh Plex Servers', 'text' => 'Refresh', 'attr' => 'onclick=\'refreshPlexServers(`select[name="plexID"]`);\'']),
				$this->settingsOption('input', 'plexAdmin', ['label' => 'Plex Admin Username']),
				$this->settingsOption('checkbox', 'plexAuthEnabled', ['label' => 'Enable Plex Authentication']),
				$this->settingsOption('checkbox', 'plexAuthAutoCreate', ['label' => 'Auto-Create Plex Users']),
				$this->settingsOption('checkbox', 'plexStrictFriends', ['label' => 'Only allow Plex Friends to login'])
			),
            'Cron Jobs' => array(
                $this->settingsOption('title', 'sonarrSectionTitle', ['text' => 'Sonarr & Tautulli Synchronisation']),
                $this->settingsOption('cron', 'sonarrAndTautulliSyncronisationSchedule', ['label' => 'Synchronisation Schedule', 'placeholder' => '*/60 * * * *']),
                $this->settingsOption('test', '/api/mediamanager/media/tvshows/update', ['label' => 'Synchronise Now', 'text' => 'Run', 'Method' => 'POST']),
                $this->settingsOption('checkbox', 'removeOrphanedTVShows', ['label' => 'Remove Orphaned Shows on Sync']),
                $this->settingsOption('hr'),
                $this->settingsOption('title', 'radarrSectionTitle', ['text' => 'Radarr & Tautulli Synchronisation']),
                $this->settingsOption('cron', 'radarrAndTautulliSyncronisationSchedule', ['label' => 'Synchronisation Schedule', 'placeholder' => '*/60 * * * *']),
                $this->settingsOption('test', '/api/mediamanager/media/movies/update', ['label' => 'Synchronise Now', 'text' => 'Run', 'Method' => 'POST']),
                $this->settingsOption('checkbox', 'removeOrphanedMovies', ['label' => 'Remove Orphaned Movies on Sync'])
            )
        );
    }
}