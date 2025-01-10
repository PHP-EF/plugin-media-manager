<?php
// Tautulli Webhook
$app->post('/mediamanager/webhook/sonarrthrottling/tautulli', function($request, $response, $args) {
    $MediaManager = new MediaManager();
    $Headers = getallheaders();
    if ((isset($Headers['Authorization']) && $Headers['Authorization'] == $MediaManager->sonarrThrottlingDecryptAuthorizationKey()) || $MediaManager->auth->checkAccess($MediaManager->config->get("Plugins", "Media Manager")['ACL-MEDIAMANAGER'] ?? "ACL-MEDIAMANAGER")) {
        $data = $MediaManager->api->getAPIRequestData($request);
        $MediaManager->sonarrThrottlingTautulliWebhook($data);
    }
    $response->getBody()->write(jsonE($GLOBALS['api']));
    return $response
        ->withHeader('Content-Type', 'application/json;charset=UTF-8')
        ->withStatus($GLOBALS['responseCode']);
});

// Overseerr Webhook
$app->post('/mediamanager/webhook/sonarrthrottling/overseerr', function($request, $response, $args) {
    $MediaManager = new MediaManager();
    $Headers = getallheaders();
    if ((isset($Headers['Authorization']) && $Headers['Authorization'] == $MediaManager->sonarrThrottlingDecryptAuthorizationKey()) || $MediaManager->auth->checkAccess($MediaManager->config->get("Plugins", "Media Manager")['ACL-MEDIAMANAGER'] ?? "ACL-MEDIAMANAGER")) {
        $data = $MediaManager->api->getAPIRequestData($request);
        $MediaManager->sonarrThrottlingOverseerrWebhook($data);
    }
    $response->getBody()->write(jsonE($GLOBALS['api']));
    return $response
        ->withHeader('Content-Type', 'application/json;charset=UTF-8')
        ->withStatus($GLOBALS['responseCode']);
});