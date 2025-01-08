<?php
// Add hook to include custom JS to login page
$this->addHook('login_page_js', function() {
    global $phpef;
    // Check if plugin is enabled
    $enabled = $phpef->config->get('Plugins','Media Manager')['plexAuthEnabled'] ?? false;

    if ($enabled) {
        echo "
        function getPlexHeaders(){
            return {
                'Accept': 'application/json',
                'X-Plex-Product': 'PHP-EF',
                'X-Plex-Version': '2.0',
                'X-Plex-Client-Identifier': '".$phpef->config->get('System','uuid')."',
                'X-Plex-Model': 'Plex OAuth',
                // 'X-Plex-Platform': osName,
                // 'X-Plex-Platform-Version': osVersion,
                // 'X-Plex-Device': browserName,
                // 'X-Plex-Device-Name': browserVersion,
                'X-Plex-Device-Screen-Resolution': window.screen.width + 'x' + window.screen.height,
                'X-Plex-Language': 'en'
            };
        }
        ";
        echo file_get_contents(__DIR__.'/main.js');   
    }
});

$this->addHook('login_page_buttons', function() {
    global $phpef;
    $enabled = $phpef->config->get('Plugins','Media Manager')['plexAuthEnabled'] ?? false;

    if ($enabled) {
        echo '<button id="plexOAuth" class="plexOAuth" style="background: #e5a00d; border: none; color: #FFF; font-size: 18px;"> Login With Plex </button>';
    }
});