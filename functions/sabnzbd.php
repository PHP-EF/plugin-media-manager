<?php
trait Sabnzbd {
    public function testConnectionSabnzbd() {
        $this->getSabnzbdQueue() ? $this->api->setAPIResponseMessage('Sabnzbd API Test Successful') : $this->api->setAPIResponse('Error','Sabnzbd API Test Failed');
    }

	public function getSabnzbdQueue() {
        $sabnzbdUrl = $this->pluginConfig['sabnzbdURL'];
		try {
    		if ($this->pluginConfig['sabnzbdUrl'] !== '' && decrypt($this->pluginConfig['sabnzbdToken']) !== '') {
				$url = $sabnzbdUrl . '/api?mode=queue&output=json&apikey=' . decrypt($this->pluginConfig['sabnzbdToken']);
				try {
					$response = $this->api->query->get($url, null, null, true);
					if ($response->success) {
						$data = json_decode($response->body, true);
						$api['content']['queueItems'] = $data;
					} else {
						$this->setAPIResponse('Error', $response->body);
						$this->logging->writeLog('Sabnzbd','Sabnzbd API Query Failed','error');
						return false;
					}
				} catch (Requests_Exception $e) {
					$this->logging->writeLog('Sabnzbd',$e,'critical');
					$this->api->setAPIResponse('Error',$e->getMessage());
					return false;
				}
				$url = $sabnzbdUrl . '/api?mode=history&output=json&limit=100&apikey=' . decrypt($this->pluginConfig['sabnzbdToken']);
				try {
					$response = $this->api->query->get($url, null, null, true);
					if ($response->success) {
						$data = json_decode($response->body, true);
						$api['content']['historyItems'] = $data;
					} else {
						$this->setAPIResponse('Error', $response->body);
						$this->logging->writeLog('Sabnzbd','Sabnzbd API Query Failed','error');
						return false;
					}
				} catch (Requests_Exception $e) {
					$this->logging->writeLog('Sabnzbd',$e,'critical');
					$this->api->setAPIResponse('Error',$e->getMessage());
					return false;
				}
				$this->api->setAPIResponseData($api);
			} else {
				$this->logging->writeLog('Sabnzbd','URL and/or Token not setup','error');
				$this->setAPIResponse('Error', 'URL and/or Token not setup');
				return false;
			}
		} catch (Requests_Exception $e) {
            $this->logging->writeLog('Sabnzbd',$e,'error');
            $this->api->setAPIResponse('Error',$e->getMessage());
			return false;
		}
	}
}