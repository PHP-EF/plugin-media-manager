<?php
// **
// GET PLUGIN SETTINGS
// **
$app->get('/plugin/MediaManager/settings', function($request, $response, $args) {
	$MediaManager = new MediaManager();
	 if ($MediaManager->auth->checkAccess($MediaManager->config->get("Plugins", "Media Manager")['ACL-MEDIAMANAGER'] ?? "ACL-MEDIAMANAGER")) {
        $MediaManager->api->setAPIResponseData($MediaManager->_pluginGetSettings());
	}
	$response->getBody()->write(jsonE($GLOBALS['api']));
	return $response
		->withHeader('Content-Type', 'application/json;charset=UTF-8')
		->withStatus($GLOBALS['responseCode']);
});

// **
// MATCH TAUTULLI -> SONARR
// **
$app->get('/plugin/MediaManager/combined/tvshows', function($request, $response, $args) {
    $MediaManager = new MediaManager();
    if ($MediaManager->auth->checkAccess($MediaManager->config->get("Plugins", "Media Manager")['ACL-MEDIAMANAGER'] ?? "ACL-MEDIAMANAGER")) {
        $params = $request->getQueryParams();
        $Results = $MediaManager->getTVShowsTable($params);
        $total = $MediaManager->getTotalTVShows(); // Function to get total count of records
        if ($Results) {
            $MediaManager->api->setAPIResponseData(['total' => $total, 'rows' => $Results]);
        }
    }
    $response->getBody()->write(json_encode($GLOBALS['api']));
    return $response
        ->withHeader('Content-Type', 'application/json;charset=UTF-8')
        ->withStatus($GLOBALS['responseCode']);
});

$app->post('/plugin/MediaManager/combined/tvshows/update', function($request, $response, $args) {
    $MediaManager = new MediaManager();
    if ($MediaManager->auth->checkAccess($MediaManager->config->get("Plugins", "Media Manager")['ACL-MEDIAMANAGER'] ?? "ACL-MEDIAMANAGER")) {
        $Results = $MediaManager->updateTVShowTable();;
        if ($Results['result'] != 'Error') {
            $MediaManager->api->setAPIResponseMessage($Results['message']);
        } else {
            $MediaManager->api->setAPIResponse($Results['result'],$Results['message']);
        }
    }
    $response->getBody()->write(jsonE($GLOBALS['api']));
    return $response
        ->withHeader('Content-Type', 'application/json;charset=UTF-8')
        ->withStatus($GLOBALS['responseCode']);
});


