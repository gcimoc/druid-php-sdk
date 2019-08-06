<?php namespace Genetsis\core;


/**
 * Manages Druid OAuth configuration
 *
 * @package Genetsis
 * @category Bean
 * @version 1.0
 * @access private
 * @todo Review source code.
 */
class OAuthConfig
{
    /**
     * @var String
     */
    protected $client_id;

    /**
     * @var String
     */
    protected $client_secret;

    /**
     * Old sections
     * @var array
     */
    protected $entry_points = array();

    /**
     * Old Redirection
     * @var string
     */
    protected $callback;

    /**
     * @var String
     */
    protected $log_level = 'DEBUG';

    /**
     * ../wp-druid-files/runtime/logs/
     * @var string
     */
    protected $log_path;

    /**
     * ../wp-druid-files/runtime/cache/
     * @var string
     */
    protected $cache_path;


    /**
     * @var string
     */
    protected $environment = 'dev';

    /**
     * @var array
     */
    protected $hosts = [
        'dev' => [
            'auth' => 'https://auth.test.id.sevillafc.es',
            'register' => 'https://register.test.id.sevillafc.es',
            'api' => 'https://api.test.id.sevillafc.es',
            'graph' => 'https://graph.test.id.sevillafc.es'
        ],
        'test' => [
            'auth' => 'https://auth.test.id.sevillafc.es',
            'register' => 'https://register.test.id.sevillafc.es',
            'api' => 'https://api.test.id.sevillafc.es',
            'graph' => 'https://graph.test.id.sevillafc.es'
        ],
        'prod' => [
            'auth' => 'https://auth.id.sevillafc.es',
            'register' => 'https://register.id.sevillafc.es',
            'api' => 'https://api.id.sevillafc.es',
            'graph' => 'https://graph.id.sevillafc.es'
        ]
    ];

    /**
     * @var array
     */
    protected $endpoints = [
       'authorization_endpoint' => '/oauth2/authorize',
       'signup_endpoint' => '/oauth2/authorize',
       'token_endpoint' => '/oauth2/token',
       'next_url' => '/oauth2/authorize/redirect',
       'cancel_url' => '/oauth2/authorize/redirect',
       'logout_endpoint' => '/oauth2/revoke',
       'edit_account_endpoint' => '/register/edit_account_input',
       'complete_account_endpoint' => '/register/complete_account_input',
    ];


    /**
     * @var array
     */
    protected $api_endpoints = [
        'user' => '/api/user',
        'activityid' => '/activityid',
        'public_image' => '/activityid/public/v1/image'
    ];

    /**
     * OAuthConfig constructor.
     */
    public function __construct()
    {
    }

    public static function init() {
        return new OAuthConfig();
    }

    /**
     * @return String
     */
    public function getClientId(): String
    {
        return $this->client_id;
    }

    /**
     * @param String $client_id
     * @return OAuthConfig
     */
    public function setClientId(String $client_id): OAuthConfig
    {
        $this->client_id = $client_id;
        return $this;
    }

    /**
     * @return String
     */
    public function getClientSecret(): String
    {
        return $this->client_secret;
    }

    /**
     * @param String $client_secret
     * @return OAuthConfig
     */
    public function setClientSecret(String $client_secret): OAuthConfig
    {
        $this->client_secret = $client_secret;
        return $this;
    }

    /**
     * @return array
     */
    public function getEntryPoints(): array
    {
        return $this->entry_points;
    }

    /**
     * @param array $entry_points
     * @return OAuthConfig
     */
    public function setEntryPoints(array $entry_points): OAuthConfig
    {
        $this->entry_points = $entry_points;
        return $this;
    }

    /**
     * @return string
     */
    public function getCallback(): string
    {
        return $this->callback;
    }

    /**
     * @param string $callback
     * @return OAuthConfig
     */
    public function setCallback(string $callback): OAuthConfig
    {
        $this->callback = $callback;
        return $this;
    }


    /**
     * @return String
     */
    public function getLogLevel(): String
    {
        return $this->log_level;
    }

    /**
     * @param String $log_level
     * @return OAuthConfig
     */
    public function setLogLevel(String $log_level): OAuthConfig
    {
        $this->log_level = $log_level;
        return $this;
    }

    /**
     * @return string
     */
    public function getLogPath(): string
    {
        return $this->log_path . $this->getClientId().'/';
    }

    /**
     * @param string $log_path
     * @return OAuthConfig
     */
    public function setLogPath($log_path): OAuthConfig
    {
        $this->log_path = $log_path;
        return $this;
    }

    /**
     * @return string
     */
    public function getCachePath(): string
    {
        return $this->cache_path . $this->getClientId() .'/';
    }

    /**
     * @param string $cache_path
     * @return OAuthConfig
     */
    public function setCachePath($cache_path): OAuthConfig
    {
        $this->cache_path = $cache_path;
        return $this;
    }

    /**
     * @return string
     */
    public function getEnvironment(): string
    {
        return $this->environment;
    }

    /**
     * @param string $environment
     * @return OAuthConfig
     */
    public function setEnvironment($environment): OAuthConfig
    {
        $this->environment = $environment;
        return $this;
    }

    /**
     * @return array
     */
    public function getHosts(): array
    {
        return $this->hosts;
    }

    /**
     * @param array $hosts
     * @return OAuthConfig
     */
    public function setHosts(array $hosts): OAuthConfig
    {
        $this->hosts = $hosts;
        return $this;
    }

    /**
     * @return array
     */
    public function getEndpoints(): array
    {
        return $this->endpoints;
    }

    /**
     * @param array $endpoints
     * @return OAuthConfig
     */
    public function setEndpoints(array $endpoints): OAuthConfig
    {
        $this->endpoints = $endpoints;
        return $this;
    }

    /**
     * @return array
     */
    public function getApiEndpoints(): array
    {
        return $this->api_endpoints;
    }

    /**
     * @param array $api_endpoints
     * @return OAuthConfig
     */
    public function setApiEndpoints(array $api_endpoints): OAuthConfig
    {
        $this->api_endpoints = $api_endpoints;
        return $this;
    }


    public function getHost($type = 'auth') {
        return $this->getHosts()[$this->getEnvironment()][$type];
    }

    /**
     * Returns an endpoint to interact with DruID servers.
     *
     * @param $type string endpoint host
     * @param $verb string endpoint path
     * @return string The URL selected. It could be empty if not exists that type.
     */
    public function getEndpointUrl($type, $verb)
    {
        $type = trim((string)$type);
        return $this->getHost($type).$this->getEndpoints()[$verb];
    }

    /**
     * Returns an endpoint to interact with API-Query.
     * @param $type string api|graph
     * @param null $verb string
     * @return string
     */
    public function getApiUrl($type, $verb = null)
    {
        $type = trim((string)$type);
        $verb = trim((string)$verb);
        return $this->getHost($type).$this->getApiEndpoints()[$verb];
    }

    /**
     * Returns an URL to redirect user.
     * Redirects can to have more than a url associate to a type.
     * Value in first position is the default value.
     *
     * @param string $urlCallback Url for callback. This url must to be defined in 'oauthconf.xml'
     * @return string The URL selected. It could be empty if not exists
     *     that type or if $urlCallback is not defined in 'oauthconf.xml'.
     */
    public function getRedirectUrl($urlCallback = null)
    {
        return ($urlCallback) ?: $this->getCallback();
    }

//
//    /**
//     * Returns a section.
//     *
//     * @param string $type Identifier to select a section.
//     * @return string The section selected. It could be empty if not exists that type.
//     */
//    public static function
// getSection($type)
//    {
//        $type = trim((string)$type);
//        return (isset(self::$config['sections'][$type]) ? self::$config['sections'][$type] : false);
//    }
//
//    /**
//     * Return default section or false if no exist a default.
//     *
//     * @return mixed default section or false
//     */

    public function getDefaultSection()
    {
        return $this->getEntryPoints()[0];
    }

}
