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

// **
// TAUTULLI
// **

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
        $data['section_id'] = $args['id'];
        $plextvcleaner->getTautulliMediaFromLibrary($data);
    }
    $response->getBody()->write(jsonE($GLOBALS['api']));
    return $response
        ->withHeader('Content-Type', 'application/json;charset=UTF-8')
        ->withStatus($GLOBALS['responseCode']);
});

$app->get('/plugin/plextvcleaner/tautulli/libraries/{id}/unwatched', function($request, $response, $args) {
    $plextvcleaner = new plextvcleaner();
    if ($plextvcleaner->auth->checkAccess($plextvcleaner->config->get("Plugins", "Plex TV Cleaner")['ACL-PLEXTVCLEANER'] ?? "ACL-PLEXTVCLEANER")) {
        $plextvcleaner->getTautulliUnwatched($args['id']);
    }
    $response->getBody()->write(jsonE($GLOBALS['api']));
    return $response
        ->withHeader('Content-Type', 'application/json;charset=UTF-8')
        ->withStatus($GLOBALS['responseCode']);
});

$app->get('/plugin/plextvcleaner/tautulli/tvshows', function($request, $response, $args) {
    $plextvcleaner = new plextvcleaner();
    if ($plextvcleaner->auth->checkAccess($plextvcleaner->config->get("Plugins", "Plex TV Cleaner")['ACL-PLEXTVCLEANER'] ?? "ACL-PLEXTVCLEANER")) {
        $plextvcleaner->api->setAPIResponseData($plextvcleaner->getTautulliTVShows());
    }
    $response->getBody()->write(jsonE($GLOBALS['api']));
    return $response
        ->withHeader('Content-Type', 'application/json;charset=UTF-8')
        ->withStatus($GLOBALS['responseCode']);
});


// **
// SONARR
// **
$app->get('/plugin/plextvcleaner/sonarr/tvshows', function($request, $response, $args) {
    $plextvcleaner = new plextvcleaner();
    if ($plextvcleaner->auth->checkAccess($plextvcleaner->config->get("Plugins", "Plex TV Cleaner")['ACL-PLEXTVCLEANER'] ?? "ACL-PLEXTVCLEANER")) {
        $plextvcleaner->api->setAPIResponseData($plextvcleaner->getSonarrTVShows());
    }
    $response->getBody()->write(jsonE($GLOBALS['api']));
    return $response
        ->withHeader('Content-Type', 'application/json;charset=UTF-8')
        ->withStatus($GLOBALS['responseCode']);
});

// **
// MATCH TAUTULLI -> SONARR
// **
$app->get('/plugin/plextvcleaner/combined/tvshows', function($request, $response, $args) {
    $plextvcleaner = new plextvcleaner();
    if ($plextvcleaner->auth->checkAccess($plextvcleaner->config->get("Plugins", "Plex TV Cleaner")['ACL-PLEXTVCLEANER'] ?? "ACL-PLEXTVCLEANER")) {
        $plextvcleaner->queryAndMatchSonarrAndTautulli();
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
