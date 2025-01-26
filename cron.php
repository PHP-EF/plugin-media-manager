<?php
// Set Schedules From Configuration
$SonarrAndTautulliSyncronisationSchedule = $phpef->config->get('Plugins','Media Manager')['sonarrAndTautulliSyncronisationSchedule'] ?? '*/60 * * * *';
$RadarrAndTautulliSyncronisationSchedule = $phpef->config->get('Plugins','Media Manager')['radarrAndTautulliSyncronisationSchedule'] ?? '*/60 * * * *';

// Scheduled Synchronisation of Sonarr / Tautulli into tvshows Table
$scheduler->call(function() {
    $MediaManager = new MediaManager();
    $pluginConfig = $MediaManager->config->get('Plugins', 'Media Manager');
    if (isset($pluginConfig['sonarrUrl']) && isset($pluginConfig['sonarrApiKey']) && isset($pluginConfig['tautulliUrl']) && isset($pluginConfig['tautulliApiKey'])) {
        try {
            $MediaManager->updateTVShowTable();
            $MediaManager->updateCronStatus('Media Manager','TV Show Sync', 'success');
        } catch (Exception $e) {
            $MediaManager->updateCronStatus('Media Manager','TV Show Sync', 'error', $e->getMessage());
        }
    }
})->at($SonarrAndTautulliSyncronisationSchedule);

// Scheduled Synchronisation of Radarr / Tautulli into movies Table
$scheduler->call(function() {
    $MediaManager = new MediaManager();
    $pluginConfig = $MediaManager->config->get('Plugins', 'Media Manager');
    if (isset($pluginConfig['radarrUrl']) && isset($pluginConfig['radarrApiKey']) && isset($pluginConfig['tautulliUrl']) && isset($pluginConfig['tautulliApiKey'])) {
        try {
            $MediaManager->updateMoviesTable();
            $MediaManager->updateCronStatus('Media Manager','Movie Sync', 'success');
        } catch (Exception $e) {
            $MediaManager->updateCronStatus('Media Manager','Movie Sync', 'error', $e->getMessage());
        }
    }
})->at($RadarrAndTautulliSyncronisationSchedule);