<?php
// **
// GET PLUGIN SETTINGS
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
// MATCH TAUTULLI -> SONARR
// **
$app->get('/plugin/plextvcleaner/combined/tvshows', function($request, $response, $args) {
    $plextvcleaner = new plextvcleaner();
    if ($plextvcleaner->auth->checkAccess($plextvcleaner->config->get("Plugins", "Plex TV Cleaner")['ACL-PLEXTVCLEANER'] ?? "ACL-PLEXTVCLEANER")) {
        $Results = $plextvcleaner->getTVShowTable();
        if ($Results) {
            $plextvcleaner->api->setAPIResponseData($Results);
        }
    }
    $response->getBody()->write(jsonE($GLOBALS['api']));
    return $response
        ->withHeader('Content-Type', 'application/json;charset=UTF-8')
        ->withStatus($GLOBALS['responseCode']);
});

$app->post('/plugin/plextvcleaner/combined/tvshows/update', function($request, $response, $args) {
    $plextvcleaner = new plextvcleaner();
    if ($plextvcleaner->auth->checkAccess($plextvcleaner->config->get("Plugins", "Plex TV Cleaner")['ACL-PLEXTVCLEANER'] ?? "ACL-PLEXTVCLEANER")) {
        $Results = $plextvcleaner->updateTVShowTable();;
        if ($Results['result'] != 'Error') {
            $plextvcleaner->api->setAPIResponseMessage($Results['message']);
        } else {
            $plextvcleaner->api->setAPIResponse($Results['result'],$Results['message']);
        }
    }
    $response->getBody()->write(jsonE($GLOBALS['api']));
    return $response
        ->withHeader('Content-Type', 'application/json;charset=UTF-8')
        ->withStatus($GLOBALS['responseCode']);
});


