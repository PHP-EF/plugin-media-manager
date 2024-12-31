<?php
// **
// USED TO DEFINE API ENDPOINTS
// **

$app->get('/api/plugin/plextvcleaner/settings', function($request, $response, $args) {
    $api = new plextvcleaner_api();
    return $response->withJson($api->settings());
});

$app->post('/api/plugin/plextvcleaner/settings', function($request, $response, $args) {
    $api = new plextvcleaner_api();
    return $response->withJson($api->settings());
});

$app->get('/api/plugin/plextvcleaner/shows', function($request, $response, $args) {
    $api = new plextvcleaner_api();
    return $response->withJson($api->shows());
});

$app->post('/api/plugin/plextvcleaner/cleanup/{showPath}', function($request, $response, $args) {
    $api = new plextvcleaner_api();
    $params = $request->getParsedBody();
    $params['path'] = urldecode($args['showPath']);
    return $response->withJson($api->cleanup($params));
});
