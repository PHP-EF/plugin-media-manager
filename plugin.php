<?php
// **
// USED TO DEFINE PLUGIN INFORMATION & CLASS
// **

// PLUGIN INFORMATION - This should match what is in plugin.json
$GLOBALS['plugins']['Media Manager'] = [
    'name' => 'Media Manager',
    'author' => 'TehMuffinMoo',
    'contributors' => 'TinyTechLabUK',
    'category' => 'Media Management',
    'link' => 'https://github.com/php-ef/plugin-media-manager',
    'version' => '1.1.3',
    'image' => 'logo.png',
    'settings' => true,
    'api' => '/api/plugin/mediamanager/settings',
];

// Set Additional Content Security Policy Headers
$GLOBALS['Headers']['CSP']['Connect-Source'] = [
    'https://plex.tv'
];

// Include MediaManager Functions
foreach (glob(__DIR__.'/functions/*.php') as $function) {
    require_once $function; // Include each PHP file
}

// Include MediaManager Widgets
foreach (glob(__DIR__.'/widgets/*.php') as $widget) {
    require_once $widget; // Include each PHP file
}

class MediaManager extends phpef {
    use General,
    Database,
    PlexAuth,
    Tautulli,
    Sonarr,
    Radarr,
    uTorrent,
    qBittorrent,
    NzbGet,
    Sabnzbd,
    SSO;

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
        try {
            $PlexToken = decrypt($this->config->get('Plugins','Media Manager')['sonarrThrottlingAuthToken']);
        } catch (Exception $e) {
            $PlexToken = $e;
        }

        return array(
            'About' => array (
				$this->settingsOption('notice', 'about', ['title' => 'Plugin Information', 'body' => '
                <p>This plugin helps manage and clean up TV show folders in your Plex server environment. It integrates with Sonarr, Radarr & Tautulli to track watched shows where custom cleanup rules are then applied to enable maintaining a manageable library size.</p>
				<p>You can additionally configure Sonarr Throttling, which allows you to specify a threshold for TV Show sizes and throttles downloads accordingly. It works by configuring a webhook in Overseerr and Tautulli to manage TV Show episode downloading based on if episodes are being watched. Shows with seasons/episodes over a configured threshold will be marked as throttled and only the first X number of episodes will be downloaded. Further episodes will only be downloaded when an event is logged in Tautulli. Using this method prevents large TV Shows from being downloaded for nobody to watch them.</p>
				<br/>
				<h3>Tautulli Webhook</h3>
				<p>Configure this Webhook in Tautulli. Using the <code>Playback Start</code> or <code>Watched</code> triggers will provide the best experience.</p>
				<pre><code class="elip hidden-xs pb-1">/api/mediamanager/webhook/sonarrthrottling/tautulli</code></pre>
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
	"authorization": "' . $PlexToken . '"
}				</pre>
				<br/>
				<h3>Overseerr Webhook</h3>
				<p>Configure this Webhook in Overseerr</p>
				<pre><code class="elip hidden-xs">/api/mediamanager/webhook/sonarrthrottling/overseerr</code></pre>
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
				<pre><code>' . $PlexToken . '</code></pre>
				<br/>']),
			),
            'Plugin' => array(
                $this->settingsOption('auth', 'ACL-MEDIAMANAGER', ['label' => 'Media Manager Plugin Access ACL'])
            ),
            'Tautulli' => array(
                $this->settingsOption('url', 'tautulliUrl', ['label' => 'Tautulli API URL', 'placeholder' => 'http://server:port']),
                $this->settingsOption('password', 'tautulliApiKey', ['label' => 'Tautulli API Key', 'placeholder' => 'Your API Key']),
                $this->settingsOption('checkbox', 'tautulliEnableSSO', ['label' => 'Enable Tautulli SSO'])
            ),
            'Sonarr' => array(
                $this->settingsOption('url', 'sonarrUrl', ['label' => 'Sonarr API URL', 'placeholder' => 'http://server:port']),
                $this->settingsOption('password', 'sonarrApiKey', ['label' => 'Sonarr API Key', 'placeholder' => 'Your API Key']),
                $this->settingsOption('select', 'sonarrApiVersion', ['label' => 'Sonarr API Version', 'options' => array(array("name" => 'v3', "value" => 'v3'),array("name" => 'v2', "value" => 'v2'),array("name" => 'v1', "value" => 'v1'))]),
                $this->settingsOption('hr'),
                $this->settingsOption('title', 'sonarrThrottlingTitle', ['text' => 'Sonarr Throttling']),
                $this->settingsOption('input', 'sonarrThrottlingSeasonThreshold', ['label' => 'Season Threshold', 'placeholder' => '4']),
                $this->settingsOption('input', 'sonarrThrottlingEpisodeThreshold', ['label' => 'Episode Threshold', 'placeholder' => '40']),
                $this->settingsOption('input', 'sonarrThrottlingEpisodeScanQty', ['label' => 'Amount of episodes to perform initial scan for', 'placeholder' => '10']),
                $this->settingsOption('select', 'sonarrThrottlingTag', ['label' => 'Tag to use for Throttled TV Shows', 'options' => $SonarrTagOptions]),
                $this->settingsOption('password', 'sonarrThrottlingAuthToken', ['label' => 'Auth Token for Webhooks']),
                $this->settingsOption('hr'),
                $this->settingsOption('title', 'sonarrCleanupTitle', ['text' => 'Sonarr Cleanup']),
                $this->settingsOption('checkbox', 'sonarrCleanupEnabled', ['label' => 'Enable Sonarr Cleanup']),
                $this->settingsOption('checkbox', 'sonarrCleanupReportOnly', ['label' => 'Report-Only Mode', 'attr' => 'checked']),
                $this->settingsOption('select', 'sonarrCleanupExclusionTag', ['label' => 'Tag to exclude TV Shows from Cleanup', 'options' => $SonarrTagOptions]),
                $this->settingsOption('input', 'sonarrCleanupEpisodesToKeep', ['label' => 'Number of Episodes to Keep', 'placeholder' => '10']),
                $this->settingsOption('input', 'sonarrCleanupMaxAge', ['label' => 'Maximum days before TV Show is cleaned up', 'placeholder' => '180'])
            ),
            'Radarr' => array(
                $this->settingsOption('url', 'radarrUrl', ['label' => 'Radarr API URL', 'placeholder' => 'http://server:port']),
                $this->settingsOption('password', 'radarrApiKey', ['label' => 'Radarr API Key', 'placeholder' => 'Your API Key']),
                $this->settingsOption('select', 'radarrApiVersion', ['label' => 'Radarr API Version', 'options' => array(array("name" => 'v3', "value" => 'v3'),array("name" => 'v2', "value" => 'v2'),array("name" => 'v1', "value" => 'v1'))]),
                $this->settingsOption('hr'),
                $this->settingsOption('title', 'radarrCleanupTitle', ['text' => 'Radarr Cleanup']),
                $this->settingsOption('checkbox', 'radarrCleanupEnabled', ['label' => 'Enable Radarr Cleanup']),
                $this->settingsOption('checkbox', 'radarrCleanupReportOnly', ['label' => 'Report-Only Mode', 'attr' => 'checked']),
                $this->settingsOption('select', 'radarrExclusionTag', ['label' => 'Tag to exclude Movies', 'options' => $RadarrTagOptions]),
                $this->settingsOption('input', 'radarrCleanupMaxAge', ['label' => 'Maximum number of days before Movie is cleaned up', 'placeholder' => '1095'])
            ),
            'Plex' => array(
				$this->settingsOption('js', 'pluginJs', ['src' => '/api/page/plugin/Media Manager/js']),
				$this->settingsOption('js', 'pluginScript', ['id' => 'plexHeaders', 'script' => "
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
				$this->settingsOption('password', 'plexToken', ['label' => 'Plex Authentication Token']),
				$this->settingsOption('button', 'getPlexToken', ['label' => 'Get Plex Token', 'text' => 'Retrieve', 'attr' => 'onclick="PlexOAuth(oAuthSuccess,oAuthError, null, \'.modal-body [name=plexToken]\');"']),
				$this->settingsOption('select', 'plexID', ['label' => 'Plex Machine ID', 'options' => $PlexServerOptions]),
				$this->settingsOption('button', 'refreshPlexServers', ['label' => 'Refresh Plex Servers', 'text' => 'Refresh', 'attr' => 'onclick=\'refreshPlexServers(`select[name="plexID"]`);\'']),
				$this->settingsOption('input', 'plexAdmin', ['label' => 'Plex Admin Username']),
                $this->settingsOption('authgroup', 'plexDefaultGroup', ['label' => 'The default group to give new plex users']),
				$this->settingsOption('checkbox', 'plexAuthEnabled', ['label' => 'Enable Plex Authentication']),
				$this->settingsOption('checkbox', 'plexAuthAutoCreate', ['label' => 'Auto-Create Plex Users']),
				$this->settingsOption('checkbox', 'plexStrictFriends', ['label' => 'Only allow Plex Friends with Shares to login'])                
			),
            'Overseerr' => array(
                $this->settingsOption('url', 'overseerrUrl', ['label' => 'Overseerr API URL', 'placeholder' => 'http://server:port']),
                $this->settingsOption('password', 'overseerrApiKey', ['label' => 'Overseerr API Key', 'placeholder' => 'Your API Key']),
                $this->settingsOption('checkbox', 'overseerrEnableSSO', ['label' => 'Enable Overseerr SSO'])
            ),
            'uTorrent' => array(
                $this->settingsOption('url', 'utorrentUrl', ['label' => 'uTorrent API URL', 'placeholder' => 'http://server:port']),
                $this->settingsOption('test', '/api/mediamanager/utorrent/test', ['label' => 'Test Connection', 'text' => 'Test', 'Method' => 'GET']),
                $this->settingsOption('username', 'utorrentUsername', ['label' => 'uTorrent Username']),
                $this->settingsOption('password', 'utorrentPassword', ['label' => 'uTorrent Password'])
            ),
            'qBittorrent' => array(
                $this->settingsOption('url', 'qbittorrentUrl', ['label' => 'qBittorrent API URL', 'placeholder' => 'http://server:port']),
                $this->settingsOption('test', '/api/mediamanager/qbittorrent/test', ['label' => 'Test Connection', 'text' => 'Test', 'Method' => 'GET']),
                $this->settingsOption('username', 'qbittorrentUsername', ['label' => 'qBittorrent Username']),
                $this->settingsOption('password', 'qbittorrentPassword', ['label' => 'qBittorrent Password']),
                $this->settingsOption('select', 'qbittorrentApiVersion', ['label' => 'qBittorrent API Version', 'options' => array(array("name" => 'v2', "value" => '2'),array("name" => 'v1', "value" => '1'), 'value' => '2')]),
            ),
            'NzbGet' => array(
                $this->settingsOption('url', 'nzbgetUrl', ['label' => 'NzbGet API URL', 'placeholder' => 'http://server:port']),
                $this->settingsOption('test', '/api/mediamanager/nzbget/test', ['label' => 'Test Connection', 'text' => 'Test', 'Method' => 'GET']),
                $this->settingsOption('username', 'nzbgetUsername', ['label' => 'NzbGet Username']),
                $this->settingsOption('password', 'nzbgetPassword'),
            ),
            'Sabnzbd' => array(
                $this->settingsOption('url', 'sabnzbdUrl', ['label' => 'NzbGet API URL', 'placeholder' => 'http://server:port']),
                $this->settingsOption('password', 'sabnzbdToken', ['label' => 'Sabnzbd Token', 'placeholder' => 'Your Sabnzbd Token']),
                $this->settingsOption('test', '/api/mediamanager/sabnzbd/test', ['label' => 'Test Connection', 'text' => 'Test', 'Method' => 'GET'])
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