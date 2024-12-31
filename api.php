<?php
// **
// USED TO DEFINE API ENDPOINTS
// **

$app->get('/api/plugin/plextvcleaner/settings', function($request, $response, $args) {
    $plextvcleaner = new plextvcleaner();
    return $response->withJson($plextvcleaner->getSettings());
});

$app->post('/api/plugin/plextvcleaner/settings', function($request, $response, $args) {
    $plextvcleaner = new plextvcleaner();
    $settings = $request->getParsedBody();
    $success = $plextvcleaner->saveSettings($settings);
    return $response->withJson(['success' => $success]);
});

$app->get('/api/plugin/plextvcleaner/shows', function($request, $response, $args) {
    $plextvcleaner = new plextvcleaner();
    return $response->withJson($plextvcleaner->getTvShows());
});

$app->post('/api/plugin/plextvcleaner/cleanup/{showPath}', function($request, $response, $args) {
    $plextvcleaner = new plextvcleaner();
    $showPath = urldecode($args['showPath']);
    $params = $request->getParsedBody();
    $dryRun = isset($params['dryRun']) ? (bool)$params['dryRun'] : null;
    
    return $response->withJson($plextvcleaner->cleanupShow($showPath, $dryRun));
});
