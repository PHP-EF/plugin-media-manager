<?php
// **
// USED TO DEFINE API ENDPOINTS
// **

class plextvcleaner_api extends api {
    public function settings($params = null) {
        $plugin = new plextvcleaner();
        
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            return $plugin->_pluginGetSettings();
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            if ($data === null) {
                return ['error' => 'Invalid JSON data'];
            }
            
            // Save the settings to the config
            $this->config->set('Plugins', 'Plex TV Cleaner', $data);
            $this->config->save();
            
            return ['success' => true];
        }
        
        return ['error' => 'Invalid request method'];
    }

    public function shows() {
        $plugin = new plextvcleaner();
        return $plugin->getTvShows();
    }

    public function cleanup($params = null) {
        if (!isset($params['path'])) {
            return ['error' => 'Show path is required'];
        }

        $plugin = new plextvcleaner();
        $dryRun = isset($params['dryRun']) ? filter_var($params['dryRun'], FILTER_VALIDATE_BOOLEAN) : null;
        return $plugin->cleanupShow($params['path'], $dryRun);
    }
}

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
