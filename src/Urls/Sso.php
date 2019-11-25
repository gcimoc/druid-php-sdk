<?php namespace Genetsis\Urls;

class Sso extends Edit implements iDruidUrl
{

    public function __construct()
    {
        parent::__construct();

        $this->setEndpoint(str_replace('register', 'login', str_replace('edit_account_input', 'sso', \Genetsis\Identity::getOAuthConfig()->getEndpointUrl('register', 'edit_account_endpoint'))));
    }
}
