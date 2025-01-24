<?php
$app->get('/mediamanager/nzbget/test', function($request, $response, $args) {
    $MediaManager = new MediaManager();
    if ($MediaManager->auth->checkAccess($MediaManager->config->get("Plugins", "Media Manager")['ACL-MEDIAMANAGER'] ?? "ACL-MEDIAMANAGER")) {
        $MediaManager->api->setAPIResponseData($MediaManager->testConnectionNzbGet());
    }
    $response->getBody()->write(json_encode($GLOBALS['api']));
    return $response
        ->withHeader('Content-Type', 'application/json;charset=UTF-8')
        ->withStatus($GLOBALS['responseCode']);
});

$app->get('/mediamanager/nzbget/queue', function($request, $response, $args) {
    $MediaManager = new MediaManager();
    $DownloadQueueWidget = new DownloadQueueWidget($MediaManager);
    if ($MediaManager->auth->checkAccess($DownloadQueueWidget->widgetConfig['auth'] ?? null)) {
        $MediaManager->getNzbgetQueue();
    }
    $response->getBody()->write(json_encode($GLOBALS['api']));
    return $response
        ->withHeader('Content-Type', 'application/json;charset=UTF-8')
        ->withStatus($GLOBALS['responseCode']);
});