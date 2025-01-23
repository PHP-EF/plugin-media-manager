<?php
$app->get('/mediamanager/sabnzbd/test', function($request, $response, $args) {
    $MediaManager = new MediaManager();
    if ($MediaManager->auth->checkAccess($MediaManager->config->get("Plugins", "Media Manager")['ACL-MEDIAMANAGER'] ?? "ACL-MEDIAMANAGER")) {
        $MediaManager->api->setAPIResponseData($MediaManager->testConnectionSabnzbd());
    }
    $response->getBody()->write(json_encode($GLOBALS['api']));
    return $response
        ->withHeader('Content-Type', 'application/json;charset=UTF-8')
        ->withStatus($GLOBALS['responseCode']);
});

$app->get('/mediamanager/sabnzbd/queue', function($request, $response, $args) {
    $MediaManager = new MediaManager();
    if ($MediaManager->auth->checkAccess($MediaManager->config->get("Plugins", "Media Manager")['ACL-MEDIAMANAGER'] ?? "ACL-MEDIAMANAGER")) {
        $MediaManager->api->setAPIResponseData($MediaManager->getSabnzbdQueue());
    }
    $response->getBody()->write(json_encode($GLOBALS['api']));
    return $response
        ->withHeader('Content-Type', 'application/json;charset=UTF-8')
        ->withStatus($GLOBALS['responseCode']);
});