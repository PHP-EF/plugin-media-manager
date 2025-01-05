<?php
$app->post('/plugin/plexauth/oauth', function ($request, $response, $args) {
	$PlexAuth = new PlexAuth();
	$data = $PlexAuth->api->getAPIRequestData($request);
	$enabled = $PlexAuth->config->get('Plugins','Media Manager')['plexAuthEnabled'] ?? false;
	if ($enabled) {
		$Login = $PlexAuth->oauth($data);
		if ($Login) {
			$PlexAuth->api->setAPIResponse('Success','Login Successful, redirecting..',200,['location' => '/']);
		} else {
			$PlexAuth->api->setAPIResponse('Error','Plex Login Failed');
		}
	} else {
		$PlexAuth->api->setAPIResponse('Error','Plex Authentication Disabled');
	}

	$response->getBody()->write(jsonE($GLOBALS['api']));
	return $response
		->withHeader('Content-Type', 'application/json;charset=UTF-8')
		->withStatus($GLOBALS['responseCode']);
});