<?php namespace Genetsis\Urls;

use Exception;
use Genetsis\Identity;

class Optin extends Edit implements iDruidUrl
{

    public function __construct()
    {
        parent::__construct();

        $this->setEndpoint(str_replace('edit_account_input', 'optin', Identity::getOAuthConfig()->getEndpointUrl('register', 'edit_account_endpoint')));
    }
}
