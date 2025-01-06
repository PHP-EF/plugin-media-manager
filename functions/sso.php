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
        $Enabled = getSSOEnabled();

        if ($Enabled['Tautulli']) {
            initiateTautulliSSO($data);
        }

        if ($Enabled['Overseerr']) {
            initiateOverseerrSSO($data);
        }
    }

    private function initiateTautulliSSO($data) {
        $Url = $this->pluginConfig['tautulliUrl']."/auth/signin";
        $HeadersArr = array(
            'Content-Type' => 'application/x-www-form-urlencoded'
        );
        $Results = $this->api->query->post($Url,$data,$HeadersArr);
        if (isset($Results) && isset($Results['status']) && $Results['status'] == 'success') {
            if (isset($Results['token']) && isset($Results['uuid'])) {
                setcookie('tautulli_token_'.$Results['uuid'], $Results['token'], time() + (86400 * 30), "/"); // 30 days
            }
            return $Results;
        } else {
            return false;
        }
    }

    private function initiateOverseerrSSO($data) {
        $Url = $this->pluginConfig['overseerrUrl']."/api/v1/auth/plex";
        $Results = $this->api->query->post($Url,$data,null,null,true);
        if (isset($Results->success)) {
            setcookie('connect.sid', $response->cookies['connect.sid']->value, time() + (86400 * 30), "/"); // 30 days
            return true;
        } else {
            return false;
        }
    }
}