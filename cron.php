<?php
// Set Schedules From Configuration
$SonarrAndTautulliSyncronisationSchedule = $ib->config->get('Plugins','Media Manager')['sonarrAndTautulliSyncronisationSchedule'] ?? '*/5 * * * *';

// Scheduled Syncronisation of Sonarr / Tautulli into tvshows Table
$scheduler->call(function() {
    $MediaManager = new MediaManager();
    $pluginConfig = $MediaManager->config->get('Plugins','Media Manager');
    if (isset($pluginConfig['sonarrUrl']) && isset($pluginConfig['sonarrApiKey']) && isset($pluginConfig['tautulliUrl']) && isset($pluginConfig['tautulliApiKey'])) {
        $MediaManager->updateTVShowTable();
    }
})->at($SonarrAndTautulliSyncronisationSchedule);