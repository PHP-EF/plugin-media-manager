<?php
$app->get('/plugin/mediamanager/settings', function($request, $response, $args) {
	$MediaManager = new MediaManager();
	 if ($MediaManager->auth->checkAccess($MediaManager->config->get("Plugins", "Media Manager")['ADMIN-CONFIG'] ?? "ADMIN-CONFIG")) {
        $MediaManager->api->setAPIResponseData($MediaManager->_pluginGetSettings());
	}
	$response->getBody()->write(jsonE($GLOBALS['api']));
	return $response
		->withHeader('Content-Type', 'application/json;charset=UTF-8')
		->withStatus($GLOBALS['responseCode']);
});