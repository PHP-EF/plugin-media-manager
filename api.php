<?php
// **
// USED TO DEFINE API ENDPOINTS
// **

$app->get('/api/plugin/plextvcleaner/settings', function($request, $response, $args) {
	$plextvcleaner = new plextvcleaner();
	 if ($plextvcleaner->auth->checkAccess($plextvcleaner->config->get("Plugins", "Plex TV Cleaner")['ACL-PLEXTVCLEANER'] ?? "ACL-PLEXTVCLEANER")) {
        $plextvcleaner->api->setAPIResponseData($plextvcleaner->_pluginGetSettings());
	}
	$response->getBody()->write(jsonE($GLOBALS['api']));
	return $response
		->withHeader('Content-Type', 'application/json;charset=UTF-8')
		->withStatus($GLOBALS['responseCode']);
});

//To be updated
// $app->get('/api/plugin/plextvcleaner/shows', function($request, $response, $args) {
//     $api = new plextvcleaner();
//     return $response->withJson($api->shows());
// });

// $app->post('/plugin/plextvcleaner/cleanup/{showPath}', function($request, $response, $args) {
//     $api = new plextvcleaner();
//     $params = $request->getParsedBody();
//     $params['path'] = urldecode($args['showPath']);
//     return $response->withJson($api->cleanup($params));
// });
