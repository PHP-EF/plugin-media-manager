<?php
trait NzbGet {
    public function testConnectionNzbGet() {
        $this->getNzbgetQueue() ? $this->api->setAPIResponseMessage('NzbGet API Test Successful') : $this->api->setAPIResponse('Error','NzbGet API Test Failed');
    }

	public function getNzbgetQueue() {
        $url = $this->pluginConfig['nzbgetUrl'];
        $urlGroups = $url . '/jsonrpc/listgroups';
        $urlHistory = $url . '/jsonrpc/history';
		try {
            $options = array();
    		if ($this->pluginConfig['nzbgetUsername'] !== '' && decrypt($this->pluginConfig['nzbgetPassword']) !== '') {
				$options = array('auth' => new Requests_Auth_Basic(array($this->pluginConfig['nzbgetUsername'], decrypt($this->pluginConfig['nzbgetPassword']))));
			}
            $response = $this->api->query->get($urlGroups, null, $options, true);
			if ($response->success) {
				$api['content']['queueItems'] = json_decode($response->body, true);
			}
            $response = $this->api->query->get($urlHistory, null, $options, true);
			if ($response->success) {
				$api['content']['historyItems'] = json_decode($response->body, true);
			}
			$api['content'] = isset($api['content']) ? $api['content'] : false;

            $this->api->setAPIResponseData($api);
            return $api;
		} catch (Requests_Exception $e) {
            $this->logging->writeLog('uTorrent',$e,'error');
            $this->api->setAPIResponse('Error',$e->getMessage());
			return false;
		}
	}
}