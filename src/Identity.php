<?php
/**
 * Druid library for PHP
 *
 * @package    DruidSdk
 * @copyright  Copyright (c) 2019 Genetsis
 * @version    3.0
 * @see       http://developers.dru-id.com
 */
namespace Genetsis;

// Require composer autoloader
//require __DIR__ . '/../vendor/autoload.php';

use Cache\Adapter\Common\AbstractCachePool;
use Exception;
use Genetsis\Config\ConfigFactory;
use Genetsis\core\ClientToken;
use Genetsis\core\Things;
use Genetsis\core\iTokenTypes;
use Genetsis\core\LoginStatusType;
use Genetsis\core\OAuth;
use Genetsis\core\OAuthConfig;
use Genetsis\Druid\Exceptions\InvalidGrantException;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
//
//if (session_id() === '') {
//    session_start();
//}

/**
 * This is the main class of the DRUID library.
 *
 * It's the class that wraps the whole set of classes of the library and
 * that you'll have to use the most. With it, you'll be able to check if a
 * user is logged, log them out, obtain the personal data of any user,
 * and check if a user has enough data to take part in a promotion, upload
 * content or carry out an action that requires a specific set of personal
 * data.
 *
 * Sample usage:
 * <code>
 *    Identity::init();
 *    // ...
 * </code>
 *
 * @package  Genetsis
 * @version  3.0
 * @access   public
 */
class Identity
{
    /** @var Things Object to store DruID's session data. */
    private static $druid_things;

    /** @var LoggerInterface Object for logging actions. */
    private static $logger;

    /** @var AbstractCachePool Object for cache */
    private static $cache;

    /** @var ClientInterface httpclient for requests */
    private static $httpClient;

    /** @var boolean Indicates if Identity has been initialized (Singleton) */
    private static $initialized = false;

    /** @var boolean Inidicates if synchronizeSessionWithServer has been called */
    private static $synchronized = false;

    /** @var OAuthConfig Druid Configuration */
    private static $druid_config = null;

    /**
     * When you initialize the library with this method, only the configuration defined in "oauthconf.xml" file of the gid_client is loaded
     * You must synchronize data with server if you need access to client or access token. This method is used for example, in ckactions.php actions.
     *
     * @param OAuthConfig $druid_config
     * @throws Exception
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public static function initConfig(OAuthConfig $druid_config)
    {
        self::init($druid_config, false);
    }

    /**
     * When you initialize the library, the configuration defined in "oauthconf.xml" file of the Druid_client is loaded
     * and by default this method auto-sync data (client_token, access_token,...) with server
     *
     * @param OAuthConfig $druid_config Druid Client to load
     * @param boolean $sync Indicates if automatic data synchronization with the server is enabled
     * @param array $options An array of collaborators that may be used to
     *     override the SDK's default behavior. Collaborators include:
     *
     *      PSR-7: `httpClient`: ClientInterface instance (Guzzle by default).
     *      PSR-17: `requestFactory`
     *      PSR-3: `logger`: LoggerInterface instance (Monolog by default)
     *          `logLevel`: define log level Psr\Log\LogLevel value for default LoggerInterface (DEBUG by default)
     *          `logDir`: define log directory for the default LoggerInterface (root by default)
     *      PSR-16: `cache`: AbstractCachePool instance (APCu by default if exist, FileSystem as backup) http://www.php-cache.com/en/latest/#cache-pool-implementations
     *          `cacheDir`: define cache directory for the default AbstractCache (sys_tmp by default)
     *
     * @throws \Exception
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public static function init(OAuthConfig $druid_config, $sync = true, array $options = [])
    {
        try {
            if (!self::$initialized) {

                self::$druid_config = $druid_config;

                self::sdkConfiguration($options);

                self::$initialized = true;

                self::$druid_things = new Things();

                if ($sync) {
                    self::synchronizeSessionWithServer();
                }
            }
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Sdk Configuration Init
     *
     * @param array $options
     * @throws Druid\Exceptions\IdentityException
     */
    private static function sdkConfiguration(array $options) {
        $configFactory = new ConfigFactory();

        if (empty($options['logger'])) {
            // Define default LogginInterface library (Monolog)
            $options['logger'] = $configFactory->getConfig('logger')->set([
                'logLevel' => self::$druid_config->getLogLevel(),
                'logDir' => self::$druid_config->getLogPath()
            ]);
        }
        self::setLogger($options['logger']);

        if (empty($options['cache'])) {
            // Define default Cache (File System)
            $options['cache'] = $configFactory->getConfig('cache')->set([
                'cacheDir' => self::$druid_config->getCachePath()
            ]);
        }
        self::setCache($options['cache']);

        if (empty($options['httpClient'])) {
            // Define de Default Http Client (Guzzle)
            $options['httpClient'] = $configFactory->getConfig('http')->set($options);
        }
        self::setHttpClient($options['httpClient']);
    }

    /**
     * This method verifies the authorization tokens (client_token,
     * access_token and refresh_token). Also updates the web client status,
     * storing the client_token, access_token and refresh tokend and
     * login_status in Things {@link Things}.
     *
     * Is INVOKE ON EACH REQUEST in order to check and update
     * the status of the user (not logged, logged or connected), and
     * verify that every token that you are gonna use before is going to be
     * valid.
     *
     * @return void
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public static function synchronizeSessionWithServer()
    {
        if (!self::$synchronized) {
            self::$synchronized = true;

            try {
                self::$logger->debug('Synchronizing session with server');
                self::checkAndUpdateClientToken();

                self::loadUserTokenFromPersistence();

                if (self::$druid_things->getAccessToken() == null) {
                    self::$logger->debug('User is not logged, check SSO');
                    self::checkSSO();
                    if (self::$druid_things->getRefreshToken() != null) {
                        self::$logger->debug('User not logged but has Refresh Token');
                        self::checkAndRefreshAccessToken();
                    }
                } else {
                    if (self::isExpired(self::$druid_things->getAccessToken()->getExpiresAt())) {
                        self::$logger->debug('User logged but Access Token is expires');
                        self::checkAndRefreshAccessToken();
                    } else {
                        self::$logger->debug('User logged - check Validate Bearer');
                        self::checkLoginStatus();
                    }
                    if (!self::isConnected()) {
                        self::$logger->warn('User logged but is not connected (something wrong) - clear session data');
                        self::clearLocalSessionData();
                    }
                }
            } catch (Exception $e) {
                self::$logger->error($e->getMessage());
            }
            $_SESSION['Things'] = @serialize(self::$druid_things);
        }
    }

    /**
     * Checks and updates the "client_token" and cache if we have a valid one
     * If we don not have a Client Token in session, we check if we have a cookie
     * If we don not have a client Token in session or in a cookie, We request a new Client Token.
     * This method set the Client Token in Things
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public static function checkAndUpdateClientToken()
    {
        try {
            self::$logger->debug('Checking and update client_token.');

            if (!(($client_token = self::getCache()->get('client_token')) instanceof ClientToken) || ($client_token->getValue() == '')) {
                self::$logger->debug('Get Client token');

                if ((self::$druid_things->getClientToken() == null) || (OAuth::getStoredToken(iTokenTypes::CLIENT_TOKEN) == null)) {
                    self::$logger->debug('Not has clientToken in session or cookie');

                    if (!$client_token = OAuth::getStoredToken(iTokenTypes::CLIENT_TOKEN)) {
                        self::$logger->debug('Token Cookie does not exists. Requesting a new one.');
                        $client_token = OAuth::doGetClientToken(self::$druid_config->getEndpointUrl('auth','token_endpoint'));
                    }
                    self::$druid_things->setClientToken($client_token);
                } else {
                    self::$logger->debug('Client Token from session');
                }
                self::getCache()->set('client_token', self::$druid_things->getClientToken(), self::$druid_things->getClientToken()->getExpiresIn());
            } else {
                self::$logger->debug('Client Token from cache');
                self::$druid_things->setClientToken($client_token);
            }
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Checks if user is logged via SSO (datr cookie) - Single Sign On
     *
     * The method obtain the "access_token" of the logged user in
     * "*.cocacola.es" through the cookie, with Grant Type EXCHANGE_SESSION
     * To SSO on domains that are not under .cocacola.es the site must include this file
     * <script type="text/javascript" src="https://register.cocacola.es/login/sso"></script>
     *
     * @return void
     * @throws /Exception
     */
    private static function checkSSO()
    {
        try {
            $datr = call_user_func(function(){
                if (!isset($_COOKIE) || !is_array($_COOKIE)) {
                    return false;
                }

                if (isset($_COOKIE['datr']) && !empty($_COOKIE['datr'])) {
                    return $_COOKIE['datr'];
                }

                foreach ($_COOKIE as $key => $val) {
                    if (strpos($key, 'datr_') === 0) {
                        return $val;
                    }
                }

                return false;
            });

            if ($datr) {
                self::$logger->info('DATR cookie was found.');

                $response = OAuth::doExchangeSession(self::$druid_config->getEndpointUrl('auth','token_endpoint'), $datr);
                self::$druid_things->setAccessToken($response['access_token']);
                self::$druid_things->setRefreshToken($response['refresh_token']);
                self::$druid_things->setLoginStatus($response['login_status']);
            } else {
                self::$logger->debug('DATR cookie not exist, user is not logged');
            }
        } catch (InvalidGrantException $e) {
            unset($_COOKIE[OAuth::SSO_COOKIE_NAME]);
            setcookie(OAuth::SSO_COOKIE_NAME, null, -1, null);

            self::$logger->warn('Invalid Grant, check an invalid DATR');
            throw $e;
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Checks if a token has expired.
     *
     * @param integer $expiresAt The expiration date. In UNIX timestamp.
     * @return boolean TRUE if is expired or FALSE otherwise.
     */
    private static function isExpired($expiresAt)
    {
        if (!is_null($expiresAt)) {
            return (time() > $expiresAt);
        }
        return true;
    }

    /**
     * Checks and refresh the user's "access_token".
     *
     * @return void
     * @throws /Exception
     */
    private static function checkAndRefreshAccessToken()
    {
        try {
            self::$logger->debug('Checking and refreshing the AccessToken.');

            $response = OAuth::doRefreshToken(self::$druid_config->getEndpointUrl('auth','token_endpoint'));
            self::$druid_things->setAccessToken($response['access_token']);
            self::$druid_things->setRefreshToken($response['refresh_token']);
            self::$druid_things->setLoginStatus($response['login_status']);
        } catch (InvalidGrantException $e) {
            self::clearLocalSessionData();
            throw $e;
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Deletes the local data of the user's session.
     *
     * @return void
     */
    private static function clearLocalSessionData()
    {
        self::$logger->debug('Clear Session Data');
        self::$druid_things->setAccessToken(null);
        self::$druid_things->setRefreshToken(null);
        self::$druid_things->setLoginStatus(null);

        OAuth::deleteStoredToken(iTokenTypes::ACCESS_TOKEN);
        OAuth::deleteStoredToken(iTokenTypes::REFRESH_TOKEN);

        if (isset($_SESSION)) {
            unset($_SESSION['Things']);
            foreach ($_SESSION as $key => $val) {
                if (preg_match('#^headerAuth#Ui', $key) || in_array($key, array('nickUserLogged', 'isConnected'))) {
                    unset($_SESSION[$key]);
                }
            }
        }
    }

    /**
     * Checks the user's status from Validate Bearer.
     * Update Things {@link Things} login status
     *
     * @return void
     * @throws /Exception
     */
    private static function checkLoginStatus()
    {
        try {
            self::$logger->debug('Checking login status');
            if (self::$druid_things->getLoginStatus()->getConnectState() == LoginStatusType::connected) {
                self::$logger->debug('User is connected, check access token');
                $loginStatus = OAuth::doValidateBearer(self::$druid_config->getEndpointUrl('auth','token_endpoint'));
                self::$druid_things->setLoginStatus($loginStatus);
            }
        } catch (InvalidGrantException $e) {
            self::$logger->warn('Invalid Grant, maybe access token is expires and sdk not checkit - call to refresh token');
            self::checkAndRefreshAccessToken();
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Helper to check if the user is connected (logged on DruID)
     *
     * @return boolean TRUE if is logged, FALSE otherwise.
     */
    public static function isConnected()
    {
        if ((!is_null(self::getThings())) && (!is_null(self::getThings()->getAccessToken())) &&
            (!is_null(self::getThings()->getLoginStatus()) && (self::getThings()->getLoginStatus()->getConnectState() == LoginStatusType::connected))
        ) {
            return true;
        }
        return false;
    }

    /**
     * Helper to access library data
     *
     * @return \Genetsis\core\Things
     */
    public static function getThings()
    {
        return self::$druid_things;
    }

    /**
     * In that case, the url of "post-login" will retrieve an authorization
     * code as a GET parameter.
     *
     * Once the authorization code is provided to the web client, the SDK
     * will send it again to DruID at "token_endpoint" to obtain the
     * "access_token" of the user and create the cookie.
     *
     * This method is needed to authorize user when the web client takes
     * back the control of the browser.
     *
     * @param string $code Authorization code returned by DruID.
     * @param string $scope scope where you want to authorize user.
     * @return void
     * @throws /Exception
     */
    public static function authorizeUser($code, $scope)
    {
        try {
            self::$logger->debug('Authorize user');

            if ($code == '') {
                throw new Exception('Authorize Code is empty');
            }

            $response = OAuth::doGetAccessToken(self::$druid_config->getEndpointUrl('auth','token_endpoint'), $code, self::$druid_config->getRedirectUrl(), $scope);
            self::$druid_things->setAccessToken($response['access_token']);
            self::$druid_things->setRefreshToken($response['refresh_token']);
            self::$druid_things->setLoginStatus($response['login_status']);

            $_SESSION['Things'] = @serialize(self::$druid_things);

        } catch (InvalidGrantException $e) {
            self::$logger->error($e->getMessage());
        } catch (Exception $e) {
            self::$logger->error($e->getMessage());
        }
    }

    /**
     * Checks if the user have been completed all required fields for that
     * section.
     *
     * The "scope" (section) is a group of fields configured in DruID for
     * a web client.
     *
     * A section can be also defined as a "part" (section) of the website
     * (web client) that only can be accesed by a user who have filled a
     * set of personal information configured in DruID (all of the fields
     * required for that section).
     *
     * This method is commonly used for promotions or sweepstakes: if a
     * user wants to participate in a promotion, the web client must
     * ensure that the user have all the fields filled in order to let him
     * participate.
     *
     * @param $scope string Section-key identifier of the web client. The
     *     section-key is located in "oauthconf.xml" file.
     * @throws \Exception
     * @return boolean TRUE if the user have already completed all the
     *     fields needed for that section, false in otherwise
     */
    public static function checkUserComplete($scope)
    {
        $userCompleted = false;
        try {
            self::$logger->info('Checking if the user has filled its data out for this section:' . $scope);

            if (self::isConnected()) {
                $userCompleted = OAuth::doCheckUserCompleted(self::$druid_config->getApiUrl('api.user', 'base_url') . self::$druid_config->getApiUrl('api', 'user'), $scope);
            }
        } catch (Exception $e) {
            self::$logger->error($e->getMessage());
        }
        return $userCompleted;
    }

    /**
     * Checks if the user needs to accept terms and conditions for that section.
     *
     * The "scope" (section) is a group of fields configured in DruID for
     * a web client.
     *
     * A section can be also defined as a "part" (section) of the website
     * (web client) that only can be accessed by a user who have filled a
     * set of personal information configured in DruID.
     *
     * @param $scope string Section-key identifier of the web client. The
     *     section-key is located in "oauthconf.xml" file.
     * @throws \Exception
     * @return boolean TRUE if the user need to accept terms and conditions, FALSE if it has
     *      already accepted them.
     */
    public static function checkUserNeedAcceptTerms($scope)
    {
        $status = false;
        try {
            self::$logger->info('Checking if the user has accepted terms and conditions for this section:' . $scope);

            if (self::isConnected()) {
                $status = OAuth::doCheckUserNeedAcceptTerms(self::$druid_config->getApiUrl('api.user', 'base_url') . self::$druid_config->getApiUrl('api.user', 'user'), $scope);
            }
        } catch (Exception $e) {
            self::$logger->error($e->getMessage());
        }
        return $status;
    }

    /**
     * Performs the logout process.
     *
     * It makes:
     * - The logout call to DruID
     * - Clear cookies
     * - Purge Tokens and local data for the logged user
     *
     * @return void
     * @throws Exception
     */
    public static function logoutUser()
    {
        try {
            if ((self::$druid_things->getAccessToken() != null) && (self::$druid_things->getRefreshToken() != null)) {
                self::$logger->info('User Single Sign Logout');
                UserApi::deleteCacheUser(self::$druid_things->getLoginStatus()->getCkUsid());

                OAuth::doLogout(self::$druid_config->getEndpointUrl('auth','logout_endpoint'));
                self::clearLocalSessionData();
            }
        } catch (Exception $e) {
            self::$logger->error($e->getMessage());
        }
    }

    /**
     * @return LoggerInterface
     */
    public static function getLogger(): LoggerInterface
    {
        return self::$logger;
    }

    /**
     * @param LoggerInterface $logger
     */
    public static function setLogger(LoggerInterface $logger): void
    {
        self::$logger = $logger;
    }


    public static function getOAuthConfig() {
        return self::$druid_config;
    }

    /**
     * @return AbstractCachePool
     */
    public static function getCache(): AbstractCachePool
    {
        return self::$cache;
    }

    /**
     * @param AbstractCachePool $cache
     */
    public static function setCache(AbstractCachePool $cache): void
    {
        self::$cache = $cache;
    }

    /**
     * @return ClientInterface
     */
    public static function getHttpClient(): ClientInterface
    {
        return self::$httpClient;
    }

    /**
     * @param ClientInterface $httpClient
     */
    public static function setHttpClient(ClientInterface $httpClient): void
    {
        self::$httpClient = $httpClient;
    }


    /**
     * Update the user's "access_token" from persistent data (SESSION or COOKIE)
     *
     * @return void
     */
    private static function loadUserTokenFromPersistence ()
    {
        try {
            if (is_null(self::$druid_things->getAccessToken())){
                self::$logger->debug('Load access token from cookie');

                if (OAuth::hasToken(iTokenTypes::ACCESS_TOKEN)) {
                    self::$druid_things->setAccessToken(OAuth::getStoredToken(iTokenTypes::ACCESS_TOKEN));
                }
                if (OAuth::hasToken(iTokenTypes::REFRESH_TOKEN)) {
                    self::$druid_things->setRefreshToken(OAuth::getStoredToken(iTokenTypes::REFRESH_TOKEN));
                }
            }

        } catch (Exception $e) {
            self::$logger->error($e->getMessage());
        }
    }
}
