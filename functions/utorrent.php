<?php
trait uTorrent {

    public function testConnectionuTorrent()
	{
		if (empty($this->pluginConfig['utorrentUrl'])) {
			$this->api->setAPIResponse('error', 'uTorrent URL is not defined');
			return false;
		}
		try {
			$response = $this->getuTorrentToken();
		} catch (Requests_Exception $e) {
            $this->logging->writeLog('uTorrent',$e,'error');
            $this->api->setAPIResponse('Error',$e->getMessage());
			return false;
		}
	}

    public function getuTorrentToken()
	{
		try {
			$tokenUrl = '/gui/token.html';
			$url = $this->pluginConfig['utorrentUrl'] . $tokenUrl;
			$data = array('username' => $this->pluginConfig['utorrentUsername'], 'password' => decrypt($this->pluginConfig['utorrentPassword']));
			if ($this->pluginConfig['utorrentUsername'] !== '' && decrypt($this->pluginConfig['utorrentPassword']) !== '') {
				$options = array('auth' => new Requests_Auth_Basic(array($this->pluginConfig['utorrentUsername'], decrypt($this->pluginConfig['utorrentPassword']))));
			}
            $response = $this->api->query->post($url,$data,null,$options);
            $config = $this->config->get();
            $responseBody = $response->body;
            $dom = new DOMDocument();
            @$dom->loadHTML($responseBody);
            $tokenElement = $dom->getElementById('token');
            $id = $tokenElement ? $tokenElement->textContent : null;
			$uTorrentConfig = array(
				"utorrentToken" => $id,
				"utorrentCookie" => "",
			);
			$reflection = new ReflectionClass($response->cookies);
			$cookie = $reflection->getProperty("cookies");
			$cookie->setAccessible(true);
			$cookie = $cookie->getValue($response->cookies);
			if ($cookie['GUID']) {
				$uTorrentConfig['utorrentCookie'] = $cookie['GUID']->value;
			}
			if ($uTorrentConfig['utorrentToken'] || $uTorrentConfig['utorrentCookie']) {
                $this->config->setPlugin($uTorrentConfig, 'Media Manager');
			}
            $this->api->setAPIResponseMessage('Successfully retrieved token');
		} catch (Requests_Exception $e) {
            $this->logging->writeLog('uTorrent',$e,'error');
            $this->api->setAPIResponse('Error',$e->getMessage());
			return false;
		}
	}

    public function getuTorrentQueue()
	{
		try {
			if (!$this->pluginConfig['utorrentToken'] || !$this->pluginConfig['utorrentCookie']) {
				$this->getuTorrentToken();
			}
			$queryUrl = '/gui/?token=' . $this->pluginConfig['utorrentToken'] . '&list=1';
			$url = $this->pluginConfig['utorrentUrl'] . $queryUrl;
            // Why do I need to pass basic auth when I already generated a token?
			if ($this->pluginConfig['utorrentUsername'] !== '' && decrypt($this->pluginConfig['utorrentPassword']) !== '') {
				$options = array('auth' => new Requests_Auth_Basic(array($this->pluginConfig['utorrentUsername'], decrypt($this->pluginConfig['utorrentPassword']))));
			}
			$headers = array(
				'Cookie' => 'GUID=' . $this->pluginConfig['utorrentCookie']
			);
			$response = $this->api->query->get($url, $headers, $options, true);
			$httpResponse = $response->status_code;
			if ($httpResponse == 400) {
                $this->logging->writeLog('uTorrent','Token or Cookie Expired. Generating new session...','warning');
				$this->getuTorrentToken();
				$response = $this->api->query->get($url, $headers, null, true);
				$httpResponse = $response->status_code;
			}
			if ($httpResponse == 200) {
				$responseData = json_decode($response->body);
				$keyArray = (array)$responseData->torrents;
				//Populate values
				$valueArray = array();
                $downloadWidgetConfig = $this->config->get('Widgets','Download Queues') ?? [];
                $utorrentHideSeeding = $downloadWidgetConfig['utorrentHideSeeding'] ?? false;
                $utorrentHideCompleted = $downloadWidgetConfig['utorrentHideCompleted'] ?? false;
				foreach ($keyArray as $keyArr) {
					preg_match('/(?<Status>(\w+\s+)+)(?<Percentage>\d+.\d+.*)/', $keyArr[21], $matches);
					$Status = str_replace(' ', '', $matches['Status']);
					if ($utorrentHideSeeding && $Status == "Seeding") {
						// Do Nothing
					} else if ($utorrentHideCompleted && $Status == "Finished") {
						// Do Nothing
					} else {
						$value = array(
							'Hash' => $keyArr[0],
							'TorrentStatus' => $keyArr[1],
							'Name' => $keyArr[2],
							'Size' => $keyArr[3],
							'Progress' => $keyArr[4],
							'Downloaded' => $keyArr[5],
							'Uploaded' => $keyArr[6],
							'Ratio' => $keyArr[7],
							'upSpeed' => $keyArr[8],
							'downSpeed' => $keyArr[9],
							'eta' => $keyArr[10],
							'Labels' => $keyArr[11],
							'PeersConnected' => $keyArr[12],
							'PeersInSwarm' => $keyArr[13],
							'SeedsConnected' => $keyArr[14],
							'SeedsInSwarm' => $keyArr[15],
							'Availability' => $keyArr[16],
							'TorrentQueueOrder' => $keyArr[17],
							'Remaining' => $keyArr[18],
							'DownloadUrl' => $keyArr[19],
							'RssFeedUrl' => $keyArr[20],
							'Message' => $keyArr[21],
							'StreamId' => $keyArr[22],
							'DateAdded' => $keyArr[23],
							'DateCompleted' => $keyArr[24],
							'AppUpdateUrl' => $keyArr[25],
							'RootDownloadPath' => $keyArr[26],
							'Unknown27' => $keyArr[27],
							'Unknown28' => $keyArr[28],
							'Status' => $Status,
							'Percent' => str_replace(' ', '', $matches['Percentage']),
						);
						array_push($valueArray, $value);
					}
				}
				$api['content']['queueItems'] = $valueArray;
				$api['content'] = $api['content'] ?? false;
                $this->api->setAPIResponseData($api);
				return $api;
			}
		} catch (Requests_Exception $e) {
            $this->logging->writeLog('uTorrent',$e,'error');
            $this->api->setAPIResponse('Error',$e->getMessage());
			return false;
		}
	}

}