<?php
// ...
$app->get('/mediamanager/radarr/queue', function($request, $response, $args) {
    $MediaManager = new MediaManager();
    $DownloadQueueWidget = new DownloadQueueWidget($MediaManager);
    if ($MediaManager->auth->checkAccess($DownloadQueueWidget->widgetConfig['auth'] ?? null)) {
        $MediaManager->api->setAPIResponseData($MediaManager->getRadarrQueue());
    }
    $response->getBody()->write(json_encode($GLOBALS['api']));
    return $response
        ->withHeader('Content-Type', 'application/json;charset=UTF-8')
        ->withStatus($GLOBALS['responseCode']);
});