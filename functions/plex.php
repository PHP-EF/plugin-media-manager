<?php
trait PlexAuth {
    private $pluginConfig;

    public function __construct() {
    //     parent::__construct();
        $this->pluginConfig = $this->config->get('Plugins','Media Manager') ?? [];
    }

    public function queryPlexAPI($Method, $Url, $Data = "") {
        if (!isset($this->pluginConfig['plexToken']) || empty($this->pluginConfig['plexToken'])) {
            $this->api->setAPIResponse('Error','Plex Auth Token Missing');
            $this->logging->writeLog("MediaManager","Plex Auth Token Missing","error");
            return false;
        } else {
            try {
                $PlexToken = decrypt($this->pluginConfig['plexToken'],$this->config->get('Security','salt'));
            } catch (Exception $e) {
                $this->api->setAPIResponse('Error','Unable to decrypt Plex Auth Token');
                $this->logging->writeLog('MediaManager','Unable to decrypt Plex Auth Token','error');
                return false;
            }
        }
        $Headers = array(
            'X-Plex-Token' => $PlexToken,
            'X-Plex-Product' => 'PHP-EF',
            'X-Plex-Version' => '2.0',
            'X-Plex-Client-Identifier' => $this->config->get('System','uuid'),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        );
        return $this->getAPIResults($Method,$Url,$Data,$Headers);
    }

    // Plex SSO via OAuth
    public function oauth($data) {
        if ($this->checkPlexToken($data['token'])) {
            //Initiate Tautulli SSO if Enabled
            $this->initiateTautulliSSO($data);
            return true;
        };
        return false;
    }

    // Checks Plex Auth Token as part of OAuth Process
    public function checkPlexToken($token = '') {
        $plexAuthEnabled = $this->pluginConfig['plexAuthEnabled'] ?? false;
        $plexAuthAutoCreate = $this->pluginConfig['plexAuthAutoCreate'] ?? false;
        $plexAdmin = $this->pluginConfig['plexAdmin'] ?? null;
        if ($plexAuthEnabled) {
            try {
                if (($token !== '')) {
                    $Headers = array(
                        'X-Plex-Token' => $token,
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json'
                    );
                    $tokenResult = $this->api->query->get('https://plex.tv/users/account.json',$Headers);
                    if (isset($tokenResult['user'])) {
                        $tokenUser = array(
                            'Username' => $tokenResult['user']['username'],
                            'FirstName' => $tokenResult['user']['username'],
                            'LastName' => '',
                            'Email' => $tokenResult['user']['email'],
                            'Groups' => ''
                        );
                        if (strtolower($plexAdmin) == strtolower($tokenUser['Username']) || $this->checkPlexUser($tokenUser['Username'])) {
                            if ($this->auth->createUserIfNotExists($tokenUser,'Plex',$plexAuthAutoCreate,false)) {
                                return true;
                            } else {
                                $this->logging->writeLog('PlexAuth','User logged in successfully but auto-create users is disabled.','error',$tokenUser);
                                return false;
                            };
                        }
                    }
                } else {
                    return false;
                }
            } catch (Exception $e) {
                $this->logging->writeLog('PlexAuth','Failed to login with Plex','error',$e);
            }
        } else {
            $this->logging->writeLog('PlexAuth','Plex Authentication is disabled','error');
        }
        return false;
    }

    // Check Plex Username against friends list
    private function checkPlexUser($username) {
        $plexStrictFriends = $this->pluginConfig['plexStrictFriends'] ?? true;
        $plexID = $this->pluginConfig['plexID'] ?? null;
        
        try {
            $response = $this->queryPlexAPI('GET','https://plex.tv/api/users');

            if ($response) {
                libxml_use_internal_errors(true);
                $userXML = simplexml_load_string($response->body);
                if (is_array($userXML) || is_object($userXML)) {
                    $usernameLower = strtolower($username);
                    foreach ($userXML as $child) {
                        if (isset($child['username']) && strtolower($child['username']) == $usernameLower || isset($child['email']) && strtolower($child['email']) == $usernameLower) {
                            $this->logging->writeLog('Plex','Found User on Friends List','debug');
                            $machineMatches = false;
                            if ($plexStrictFriends) {
                                foreach ($child->Server as $server) {
                                    if ((string)$server['machineIdentifier'] == $plexID) {
                                        $machineMatches = true;
                                    }
                                }
                            } else {
                                $machineMatches = true;
                            }
                            if ($machineMatches) {
                                $this->logging->writeLog('Plex','User Approved for Login','debug');
                                return true;
                            } else {
                                $this->logging->writeLog('Plex','User Not Approved for Login','debug');
                            }
                        }
                    }
                }
            }
            return false;
        } catch (Exception $e) {
            $this->logging->writeLog('PlexAuth','Failed to check Plex User','error',$e);
        }
        return false;
    }

    public function getPlexServers($data = []) {
        $ownedOnly = isset($data['owned']) ?? false;
        try {
            $response = $this->queryPlexAPI('GET','https://plex.tv/pms/servers');
            libxml_use_internal_errors(true);
            if ($response->success) {
                $items = array();
                $plex = simplexml_load_string($response->body);
                foreach ($plex as $server) {
                    if ($ownedOnly) {
                        if ($server['owned'] == 1) {
                            $items[] = array(
                                'name' => (string)$server['name'],
                                'address' => (string)$server['address'],
                                'machineIdentifier' => (string)$server['machineIdentifier'],
                                'owned' => (float)$server['owned'],
                            );
                        }
                    } else {
                        $items[] = array(
                            'name' => (string)$server['name'],
                            'address' => (string)$server['address'],
                            'machineIdentifier' => (string)$server['machineIdentifier'],
                            'owned' => (float)$server['owned'],
                        );
                    }
                }
                $this->api->setAPIResponseData($items);
                return $items;
            } else {
                $this->api->setAPIResponse('Error', 'Plex Error occurred', null, $response->body);
                $this->logging->writeLog('Plex Connection','Plex Error','error');
                return false;
            }
        } catch (Requests_Exception $e) {
            $this->api->setAPIResponse('Error', $e->getMessage());
            $this->logging->writeLog('Plex Connection','Plex Error','error');
            return false;
        }
    }
}