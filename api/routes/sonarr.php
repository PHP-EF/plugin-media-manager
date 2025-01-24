<?php
// ...
$app->get('/mediamanager/sonarr/cleanup', function($request, $response, $args) {
    $MediaManager = new MediaManager();
    if ($MediaManager->auth->checkAccess($MediaManager->config->get("Plugins", "Media Manager")['ACL-MEDIAMANAGER'] ?? "ACL-MEDIAMANAGER")) {
        $data = $MediaManager->api->getAPIRequestData($request);
        $ids = $data['ids'] ?? [];
        $Results = $MediaManager->sonarrCleanup($ids);
        if ($Results) {
            $MediaManager->api->setAPIResponseData($Results);
        }
    }
    $response->getBody()->write(json_encode($GLOBALS['api']));
    return $response
        ->withHeader('Content-Type', 'application/json;charset=UTF-8')
        ->withStatus($GLOBALS['responseCode']);
});

$app->get('/mediamanager/sonarr/queue', function($request, $response, $args) {
    $MediaManager = new MediaManager();
    $DownloadQueueWidget = new DownloadQueueWidget($MediaManager);
    if ($MediaManager->auth->checkAccess($DownloadQueueWidget->widgetConfig['auth'] ?? null)) {
        $MediaManager->getSonarrQueue();
    }
    $response->getBody()->write(json_encode($GLOBALS['api']));
    return $response
        ->withHeader('Content-Type', 'application/json;charset=UTF-8')
        ->withStatus($GLOBALS['responseCode']);
});