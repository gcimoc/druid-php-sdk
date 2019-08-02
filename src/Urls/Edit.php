<?php namespace Genetsis\Urls;

use Exception;
use Genetsis\Identity;

class Edit extends DruidUrl implements iDruidUrl
{

    public function __construct()
    {
        parent::__construct();

        $this->setEndpoint( Identity::getOAuthConfig()->getEndpointUrl('register', 'edit_account_endpoint'));

    }

    public function get() : string
    {
        try {
            $access_token = Identity::getThings()->getAccessToken();

            if (is_null($access_token)) {
                throw new Exception ('Access token is empty');
            }

            $paramsUrls = [
                'client_id' => Identity::getOAuthConfig()->getClientid(),
                'redirect_uri' => Identity::getOAuthConfig()->getRedirectUrl($this->getUrlCallback())
            ];

            $params = [
                'next' => $this->appendQuery(Identity::getOAuthConfig()->getEndpointUrl('auth','next_url'), $paramsUrls),
                'cancel_url' => $this->appendQuery(Identity::getOAuthConfig()->getEndpointUrl('auth','cancel_url'), $paramsUrls),
                'oauth_token' => $access_token->getValue()
            ];

            if (!empty($this->getScope())) {
                $params['scope'] = $this->getScope();
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