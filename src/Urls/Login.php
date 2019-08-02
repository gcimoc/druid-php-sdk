<?php namespace Genetsis\Urls;

use Exception;
use Genetsis\Identity;

class Login extends DruidUrl implements iDruidUrl
{

    public function __construct()
    {
        parent::__construct();

        $this->setEndpoint(Identity::getOAuthConfig()->getEndpointUrl('auth', 'authorization_endpoint'));
        $this->setScope(Identity::getOAuthConfig()->getDefaultSection());
    }


    public function get() : string
    {
        try {
            $params = [
                'client_id' => Identity::getOAuthConfig()->getClientId(),
                'redirect_uri' => $this->getUrlCallback(),
                'response_type' => 'code',
            ];

            if (!empty($this->getScope())) {
                $params['scope'] = $this->getScope();
            }

            if (!empty($this->getSocial())) {
                $params['ck_auth_provider'] = $this->getSocial();
            }

            if (!empty($this->getPrefill())) {
                $params['x_prefill'] = base64_encode($this->arrayToUserJson($this->getPrefill()));
            }

            if (!empty($this->getState())) {
                $params['state'] = $this->getState();
            }

            return $this->appendQuery($this->getEndpoint(), $params);
        } catch (Exception $e) {
            Identity::getLogger()->error($e->getMessage());
        }
    }

}