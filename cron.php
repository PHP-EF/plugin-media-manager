<?php
// Set Schedules From Configuration
$SonarrAndTautulliSyncronisationSchedule = $ib->config->get('Plugins','Media Manager')['sonarrAndTautulliSyncronisationSchedule'] ?? '*/60 * * * *';
$RadarrAndTautulliSyncronisationSchedule = $ib->config->get('Plugins','Media Manager')['radarrAndTautulliSyncronisationSchedule'] ?? '*/60 * * * *';

// Scheduled Syncronisation of Sonarr / Tautulli into tvshows Table
$scheduler->call(function() {
    $MediaManager = new MediaManager();
    $pluginConfig = $MediaManager->config->get('Plugins','Media Manager');
    if (isset($pluginConfig['sonarrUrl']) && isset($pluginConfig['sonarrApiKey']) && isset($pluginConfig['tautulliUrl']) && isset($pluginConfig['tautulliApiKey'])) {
        $MediaManager->updateTVShowTable();
    }
})->at($SonarrAndTautulliSyncronisationSchedule);

// Scheduled Syncronisation of Radarr / Tautulli into movies Table
$scheduler->call(function() {
    $MediaManager = new MediaManager();
    $pluginConfig = $MediaManager->config->get('Plugins','Media Manager');
    if (isset($pluginConfig['radarrUrl']) && isset($pluginConfig['radarrApiKey']) && isset($pluginConfig['tautulliUrl']) && isset($pluginConfig['tautulliApiKey'])) {
        $MediaManager->updateMoviesTable();
    }
})->at($RadarrAndTautulliSyncronisationSchedule);