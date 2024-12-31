<?php
// **
// USED TO DEFINE API ENDPOINTS
// **

$app->get('/plugin/plextvcleaner/settings', function($request, $response, $args) {
	$plextvcleaner = new plextvcleaner();
	 if ($plextvcleaner->auth->checkAccess($plextvcleaner->config->get("Plugins", "Plex TV Cleaner")['ACL-PLEXTVCLEANER'] ?? "ACL-PLEXTVCLEANER")) {
        $plextvcleaner->api->setAPIResponseData($plextvcleaner->_pluginGetSettings());
	}
	$response->getBody()->write(jsonE($GLOBALS['api']));
	return $response
		->withHeader('Content-Type', 'application/json;charset=UTF-8')
		->withStatus($GLOBALS['responseCode']);
});

$app->get('/plugin/plextvcleaner/tautulli/libraries', function($request, $response, $args) {
    $plextvcleaner = new plextvcleaner();
    if ($plextvcleaner->auth->checkAccess($plextvcleaner->config->get("Plugins", "Plex TV Cleaner")['ACL-PLEXTVCLEANER'] ?? "ACL-PLEXTVCLEANER")) {
        $plextvcleaner->getTautulliLibraries();
    }
    $response->getBody()->write(jsonE($GLOBALS['api']));
    return $response
        ->withHeader('Content-Type', 'application/json;charset=UTF-8')
        ->withStatus($GLOBALS['responseCode']);
});

$app->get('/plugin/plextvcleaner/tautulli/libraries/{id}', function($request, $response, $args) {
    $plextvcleaner = new plextvcleaner();
    if ($plextvcleaner->auth->checkAccess($plextvcleaner->config->get("Plugins", "Plex TV Cleaner")['ACL-PLEXTVCLEANER'] ?? "ACL-PLEXTVCLEANER")) {
        $data = $request->getQueryParams();
        $Length = $data['limit'] ?? 100;
        $Start = ($data['page'] == 1) ? 0 : ($data['page'] * $Length ?? 0);
        $plextvcleaner->getTautulliMediaFromLibrary($args['id'],$Start,$Length);
    }
    $response->getBody()->write(jsonE($GLOBALS['api']));
    return $response
        ->withHeader('Content-Type', 'application/json;charset=UTF-8')
        ->withStatus($GLOBALS['responseCode']);
});


// ** OLD STUFF ** //

$app->get('/plugin/plextvcleaner/shows', function($request, $response, $args) {
    $plextvcleaner = new plextvcleaner();
    if ($plextvcleaner->auth->checkAccess($plextvcleaner->config->get("Plugins", "Plex TV Cleaner")['ACL-PLEXTVCLEANER'] ?? "ACL-PLEXTVCLEANER")) {
        $plextvcleaner->getTvShows();
    }
    $response->getBody()->write(jsonE($GLOBALS['api']));
    return $response
        ->withHeader('Content-Type', 'application/json;charset=UTF-8')
        ->withStatus($GLOBALS['responseCode']);
});

$app->post('/plugin/plextvcleaner/cleanup/{showPath}', function($request, $response, $args) {
    $plextvcleaner = new plextvcleaner();
    if ($plextvcleaner->auth->checkAccess($plextvcleaner->config->get("Plugins", "Plex TV Cleaner")['ACL-PLEXTVCLEANER'] ?? "ACL-PLEXTVCLEANER")) {
        $params = $plextvcleaner->api->getAPIRequestData($request);
        $plextvcleaner->cleanup($params);
    }
    $response->getBody()->write(jsonE($GLOBALS['api']));
    return $response
        ->withHeader('Content-Type', 'application/json;charset=UTF-8')
        ->withStatus($GLOBALS['responseCode']);
});
