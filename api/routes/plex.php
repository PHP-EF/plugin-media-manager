<?php
$app->post('/mediamanager/plex/oauth', function ($request, $response, $args) {
	$MediaManager = new MediaManager();
	$data = $MediaManager->api->getAPIRequestData($request);
	$enabled = $MediaManager->config->get('Plugins','Media Manager')['plexAuthEnabled'] ?? false;
	if ($enabled) {
		$Login = $MediaManager->oauth($data);
		if ($Login) {
			$MediaManager->api->setAPIResponse('Success','Login Successful, redirecting..',200,['location' => '/']);
		} else {
			$MediaManager->api->setAPIResponse('Error','Plex Login Failed');
		}
	} else {
		$MediaManager->api->setAPIResponse('Error','Plex Authentication Disabled');
	}

	$response->getBody()->write(jsonE($GLOBALS['api']));
	return $response
		->withHeader('Content-Type', 'application/json;charset=UTF-8')
		->withStatus($GLOBALS['responseCode']);
});

$app->get('/mediamanager/plex/servers', function ($request, $response, $args) {
	$MediaManager = new MediaManager();
	$data = $request->getQueryParams();
	if ($MediaManager->auth->checkAccess($MediaManager->config->get("Plugins", "Media Manager")['ADMIN-CONFIG'] ?? "ADMIN-CONFIG")) {
        $MediaManager->getPlexServers($data);
	}
	$response->getBody()->write(jsonE($GLOBALS['api']));
	return $response
		->withHeader('Content-Type', 'application/json;charset=UTF-8')
		->withStatus($GLOBALS['responseCode']);
});


$app->post('/mediamanager/plex/tautulli/sso', function ($request, $response, $args) {
	$MediaManager = new MediaManager();
	$data = $MediaManager->api->getAPIRequestData($request);
	$enabled = $MediaManager->config->get('Plugins','Media Manager')['tautulliSSOEnabled'] ?? true;
	if ($enabled) {
		$MediaManager->initiateTautulliSSO($data);
	} else {
		$MediaManager->api->setAPIResponse('Error','Tautulli SSO Disabled');
	}

	$response->getBody()->write(jsonE($GLOBALS['api']));
	return $response
		->withHeader('Content-Type', 'application/json;charset=UTF-8')
		->withStatus($GLOBALS['responseCode']);
});