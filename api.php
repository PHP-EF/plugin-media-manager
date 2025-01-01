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
        $plextvcleaner->queryAndMatchSonarrAndTautulli();
    }
    $response->getBody()->write(jsonE($GLOBALS['api']));
    return $response
        ->withHeader('Content-Type', 'application/json;charset=UTF-8')
        ->withStatus($GLOBALS['responseCode']);
});