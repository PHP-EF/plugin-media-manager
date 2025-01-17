<?php
$app->get('/mediamanager/qbittorrent/test', function($request, $response, $args) {
    $MediaManager = new MediaManager();
    if ($MediaManager->auth->checkAccess("ADMIN-CONFIG")) {
        $MediaManager->api->setAPIResponseData($MediaManager->testConnectionqBittorrent());
    }
    $response->getBody()->write(json_encode($GLOBALS['api']));
    return $response
        ->withHeader('Content-Type', 'application/json;charset=UTF-8')
        ->withStatus($GLOBALS['responseCode']);
});

$app->get('/mediamanager/qbittorrent/queue', function($request, $response, $args) {
    $MediaManager = new MediaManager();
    if ($MediaManager->auth->checkAccess($MediaManager->config->get("Plugins", "Media Manager")['ACL-MEDIAMANAGER'] ?? "ACL-MEDIAMANAGER")) {
        $MediaManager->api->setAPIResponseData($MediaManager->getqBittorrentQueue());
    }
    $response->getBody()->write(json_encode($GLOBALS['api']));
    return $response
        ->withHeader('Content-Type', 'application/json;charset=UTF-8')
        ->withStatus($GLOBALS['responseCode']);
});