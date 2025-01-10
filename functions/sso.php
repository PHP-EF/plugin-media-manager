<?php
trait SSO {
    private function getSSOEnabled() {
        $TautulliSSOEnabled = $this->pluginConfig['tautulliEnableSSO'] ?? false;
        $OverseerrSSOEnabled = $this->pluginConfig['overseerrEnableSSO'] ?? false;

        return array(
            'Tautulli' => $TautulliSSOEnabled,
            'Overseerr' => $OverseerrSSOEnabled
        );

    }

    public function initiateSSO($data) {
        $Enabled = $this->getSSOEnabled();

        if ($Enabled['Tautulli']) {
            $this->initiateTautulliSSO($data);
        }

        if ($Enabled['Overseerr']) {
            $this->initiateOverseerrSSO($data);
        }
    }

    // check if array
    private function initiateTautulliSSO($data) {
        $Url = $this->pluginConfig['tautulliUrl']."/auth/signin";
        $HeadersArr = array(
            'Content-Type' => 'application/x-www-form-urlencoded'
        );
        $data['remember_me'] = 1; // swap out to using remember me from PHP-EF when implemented
        $Results = $this->api->query->post($Url,$data,$HeadersArr);
        if (isset($Results) && isset($Results['status']) && $Results['status'] == 'success') {
            if (isset($Results['token']) && isset($Results['uuid'])) {
                $this->cookie('set','tautulli_token_'.$Results['uuid'], $Results['token'], 30);
            }
            return true;
        } else {
            return false;
        }
    }

    private function initiateOverseerrSSO($data) {
        $Url = $this->pluginConfig['overseerrUrl']."/api/v1/auth/plex";
        $DataArr = array(
            'authToken' => $data['token']
        );
        $Results = $this->api->query->post($Url,$DataArr,null,null,true);
        if (isset($Results->success)) {
            $this->cookie('set','connect.sid', urldecode($Results->cookies['connect.sid']->value), 30);
            return true;
        } else {
            return false;
        }
    }
}