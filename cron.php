<?php
// Set Schedules From Configuration
$SonarrAndTautulliSyncronisationSchedule = $ib->config->get('Plugins','Plex TV Cleaner')['sonarrAndTautulliSyncronisationSchedule'] ?? '*/5 * * * *';

// Scheduled Syncronisation of Sonarr / Tautulli into tvshows Table
$scheduler->call(function() {
    $plextvcleaner = new plextvcleaner();
    $pluginConfig = $plextvcleaner->config->get('Plugins','Plex TV Cleaner');
    if (isset($pluginConfig['sonarrUrl']) && isset($pluginConfig['sonarrApiKey']) && isset($pluginConfig['tautulliUrl']) && isset($pluginConfig['tautulliApiKey'])) {
        $plextvcleaner->updateTVShowTable();
    }
})->at($SonarrAndTautulliSyncronisationSchedule);