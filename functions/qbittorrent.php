<?php
trait qBittorrent {
    public function testConnectionqBittorrent()
	{
		if (empty($this->pluginConfig['qbittorrentUrl'])) {
			$this->api->setAPIResponse('Error', 'qBittorrent URL is not defined');
			return false;
		}
		$apiVersionQuery = ($this->pluginConfig['qbittorrentApiVersion'] == '1') ? '/query/torrents?sort=' : '/api/v2/torrents/info?sort=';
		try {
            $cookieVal = $this->getqBittorrentToken();
			if ($cookieVal) {
				$HeadersArr['Cookie'] = 'SID=' . $cookieVal;
				$reverse = $this->pluginConfig['qbittorrentReverseSorting'] ?? 'false';
				$url = $this->pluginConfig['qbittorrentUrl'] . $apiVersionQuery . 'eta' . '&reverse=' . $reverse;
                $response = $this->api->query->get($url,$HeadersArr);
				if ($response) {
					if (is_array($response)) {
						$this->api->setAPIResponseMessage('API Connection succeeded');
						return true;
					} else {
						$this->api->setAPIResponse('Error', 'qBittorrent Error Occurred - Check URL or Credentials');
						return true;
					}
				} else {
					$this->api->setAPIResponse('Error', 'qBittorrent Connection Error Occurred - Check URL or Credentials');
					return true;
				}
			} else {
                $this->api->setAPIResponse('Error','qBittorrent Connect Function - Error: Could not get session ID');
				return false;
			}
		} catch (Requests_Exception $e) {
            $this->logging->writeLog('uTorrent',$e,'error');
            $this->api->setAPIResponse('Error',$e->getMessage());
			return false;
		}
	}

    public function getqBittorrentToken($force = false)
	{
		try {
            $qbittorrentCookie = $this->pluginConfig['qbittorrentCookie'] ?? null;
            if (!$qbittorrentCookie || $force) {
                $apiVersionLogin = ($this->pluginConfig['qbittorrentApiVersion'] == '1') ? '/login' : '/api/v2/auth/login';
                $data = array('username' => $this->pluginConfig['qbittorrentUsername'], 'password' => decrypt($this->pluginConfig['qbittorrentPassword']));
                $url = $this->pluginConfig['qbittorrentUrl'] . $apiVersionLogin;
                $HeadersArr = array(
                    'Content-Type' => 'application/x-www-form-urlencoded'
                );
                $response = $this->api->query->post($url,$data,$HeadersArr,null,true);
                $reflection = new ReflectionClass($response->cookies);
                $cookie = $reflection->getProperty("cookies");
                $cookie->setAccessible(true);
                $cookie = $cookie->getValue($response->cookies);
                $cookieVal = $cookie['SID']->value;
                $config = $this->config->get();
                $qbittorrentConfig = array(
                    'qbittorrentCookie' => $cookieVal
                );
                $this->config->setPlugin($config, $qbittorrentConfig, 'Media Manager');
            } else {
                $cookieVal = $qbittorrentCookie;
            }
            return $cookieVal;
		} catch (Requests_Exception $e) {
            $this->logging->writeLog('uTorrent',$e,'error');
            $this->api->setAPIResponse('Error',$e->getMessage());
			return false;
		}
	}

	public function getqBittorrentQueue() {
		try {
            $cookieVal = $this->getqBittorrentToken();
            $downloadWidgetConfig = $this->config->get('Widgets','Download Queues') ?? [];
			if ($cookieVal) {
				$HeadersArr['Cookie'] = 'SID=' . $cookieVal;
                $apiVersionQuery = ($this->pluginConfig['qbittorrentApiVersion'] == '1') ? '/query/torrents?sort=' : '/api/v2/torrents/info?sort=';
                $reverse = $downloadWidgetConfig['qbittorrentReverseSorting'] ?? 'false';
				$url = $this->pluginConfig['qbittorrentUrl'] . $apiVersionQuery . 'eta' . '&reverse=' . $reverse;
                $response = $this->api->query->get($url,$HeadersArr,null,true);
                $httpResponse = $response->status_code;
                if ($httpResponse == 403) {
                    $this->logging->writeLog('qBittorrent','Session or Cookie Expired. Generating new session...','warning');
                    $cookieVal = $this->getqBittorrentToken(true);
                    $HeadersArr['Cookie'] = 'SID=' . $cookieVal;
                    $response = $this->api->query->get($url, $HeadersArr, null, true);
                    $httpResponse = $response->status_code;
                }
                if ($httpResponse == 200) {
                    $responseData = json_decode($response->body);

                    $qbittorrentHideSeeding = $downloadWidgetConfig['qbittorrentHideSeeding'] ?? false;
                    $qbittorrentHideCompleted = $downloadWidgetConfig['qbittorrentHideCompleted'] ?? false;
                    
					if ($qbittorrentHideSeeding || $qbittorrentHideCompleted) {
						$filter = array();
						$torrents = array();
						if ($qbittorrentHideSeeding) {
							array_push($filter, 'uploading', 'stalledUP', 'queuedUP');
						}
						if ($qbittorrentHideCompleted) {
							array_push($filter, 'pausedUP');
						}
						foreach ($responseData as $key => $value) {
							if (!in_array($value['state'], $filter)) {
								$torrents[] = $value;
							}
						}
					} else {
						$torrents = $responseData;
					}
					$api['content']['queueItems'] = $torrents;
					$api['content']['historyItems'] = false;
					$api['content'] = $api['content'] ?? false;
					$this->api->setAPIResponseData($api);
					return $api;
				}
			} else {
                $this->logging->writeLog('uTorrent','Could not get session ID','warning');
                $this->api->setAPIResponse('Error','qBittorrent Connect Function - Error: Could not get session ID');
                return false;
			}
		} catch (Requests_Exception $e) {
            $this->logging->writeLog('uTorrent',$e,'error');
            $this->api->setAPIResponse('Error',$e->getMessage());
			return false;
		}
	}
}