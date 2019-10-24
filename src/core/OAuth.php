<?php
namespace Genetsis\core;

use Exception;
use Genetsis\Druid\Exceptions\InvalidGrantException;
use Genetsis\Identity;


/**
 * This class wraps all methods for interactions with OAuth service,
 * for user authentication and validation. Also generates the URLs to
 * perform this operations as register or login.
 *
 * @package   Genetsis
 * @category  Helper
 * @version   1.0
 * @access    private
 */
class OAuth
{
    /** Different AUTH method. */
    const GRANT_TYPE_AUTH_CODE = 'authorization_code';
    const GRANT_TYPE_REFRESH_TOKEN = 'refresh_token';
    const GRANT_TYPE_CLIENT_CREDENTIALS = 'client_credentials';
    const GRANT_TYPE_VALIDATE_BEARER = 'urn:es.cocacola:oauth2:grant_type:validate_bearer';
    const GRANT_TYPE_EXCHANGE_SESSION = 'urn:es.cocacola:oauth2:grant_type:exchange_session';
    /** Default expiration time. In seconds. */
    const DEFAULT_EXPIRES_IN = 900;
    /** Indicates the percentage to be subtracted from the number of
     * seconds of "expires_in" to not be so close to the expiration date
     * of the token. */
    const SAFETY_RANGE_EXPIRES_IN = 0.10; # 10%
    /** Cookie name for SSO (Single Sign-Out). */
    const SSO_COOKIE_NAME = 'datr';

    /**
     * Gets a "client_token" for the current web client.
     *
     * @param $endpoint_url endpoint where "client_token" is requested.
     * @return ClientToken
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public static function doGetClientToken($endpoint_url)
    {
        try {
            if (($endpoint_url = trim(( string )$endpoint_url)) == '') {
                throw new Exception ('Endpoint URL is empty');
            }

            $params = [
                'grant_type' => self::GRANT_TYPE_CLIENT_CREDENTIALS
            ];
            $response = Request::execute($endpoint_url, $params, Request::HTTP_POST, Request::SECURED);

            self::checkErrors($response);

            if (empty($response['result']->access_token)) {
                throw new Exception ('The client_token retrieved is empty');
            }

            list($expires_in, $expires_at, $refresh_expires_at) = self::generateExpires($response);

            $client_token = new ClientToken(trim($response['result']->access_token), $expires_in, $expires_at, '/');
            self::storeToken($client_token);

            return $client_token;
        } catch (Exception $e) {
            Identity::getLogger()->error($e->getMessage());
            throw $e;
        }
    }

    /**
     * Checks if there are errors in the response.
     *
     * @param array $response Where we will search errors.
     * @return void
     * @throws \Exception If there is an error in the response.
     */
    private static function checkErrors($response)
    {
        if (isset($response['result']->error)) {
            if (isset($response['result']->type)) {
                switch ($response['result']->type) {
                    case 'InvalidGrantException' :
                        throw new InvalidGrantException($response['result']->error . ' (' . (isset($response['result']->type) ? trim($response['result']->type) : '') . ')');;
                }
            }
            throw new Exception($response['result']->error . ' (' . (isset($response['result']->type) ? trim($response['result']->type) : '') . ')');
        }
        if (isset($response['code']) && ($response['code'] != 200)) {
            throw new Exception('Error: ' .$response['code']);
        }
    }

    /**
     * Stores a token in a cookie
     *
     * @param Token $token An object with token data to be stored.
     * @throws \Exception
     */
    private static function storeToken($token)
    {
        if (!($token instanceof Token)) {
            throw new Exception('Token is not valid');
        }

        // Save it in COOKIE
        $encryption = new Encryption(Identity::getOAuthConfig()->getClientId());
        $cod = $encryption->encode($token->getValue());
        @setcookie($token->getName(), $cod, $token->getExpiresAt(), $token->getPath(), '', false, true);
    }

    /**
     * Gets an "access_token" for the current web client.
     *
     * @param string $endpoint_url The endpoint where "access_token" is requested.
     * @param string $code The authorization code returned by DruID.
     * @param string $redirect_url Where the user will be redirected.
     * @param string $scope scope of the action.
     * @return mixed An instance of {@link AccessToken} with data retrieved
     *     or FALSE.
     * @throws \Exception If there is an error.
     */
    public static function doGetAccessToken(string $endpoint_url, string $code, string $redirect_url, string $scope)
    {
        try {
            if (($endpoint_url = trim(( string )$endpoint_url)) == '') {
                throw new Exception ('Endpoint URL is empty');
            }
            if (($code = trim(( string )$code)) == '') {
                throw new Exception ('Code is empty');
            }
            if (($redirect_url = trim(( string )$redirect_url)) == '') {
                throw new Exception ('Redirect URL is empty');
            }

            if (($scope = trim(( string )$scope)) == '') {
                throw new Exception ('scope is empty, and must be explicitly defined');
            }

            $params = [
                'grant_type' => self::GRANT_TYPE_AUTH_CODE,
                'code' => $code,
                'redirect_uri' => $redirect_url,
                'scope' => $scope
            ];
            $response = Request::execute($endpoint_url, $params, Request::HTTP_POST, Request::SECURED);

            self::checkErrors($response);

            if (empty($response ['result']->access_token)) {
                throw new Exception ('The access_token retrieved is empty');
            }
            if (empty($response ['result']->refresh_token)) {
                throw new Exception ('The refresh_token retrieved is empty');
            }

            list($expires_in, $expires_at, $refresh_expires_at) = self::generateExpires($response);

            $result = [
                'access_token' => new AccessToken (trim($response ['result']->access_token), $expires_in, $expires_at, '/'),
                'refresh_token' => new RefreshToken (trim($response ['result']->refresh_token), 0, $refresh_expires_at, '/')
            ];

            self::storeToken($result['access_token']);
            self::storeToken($result['refresh_token']);

            $loginStatus = new LoginStatus();
            if (isset ($response ['result']->login_status)) {
                $loginStatus->setCkusid($response['result']->login_status->uid);
                $loginStatus->setOid($response['result']->login_status->oid);
                $loginStatus->setConnectState($response['result']->login_status->connect_state);
            }

            $result['login_status'] = $loginStatus;

            return $result;
        } catch (InvalidGrantException $e) {
            throw new InvalidGrantException('Maybe "code" is reused - '.$e->getMessage());
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Updates tokens.
     *
     * @param string $endpoint_url The endpoint where the request will be sent.
     * @return boolean TRUE if the tokens have been updated or FALSE
     *     otherwise.
     * @throws \Exception If there is an error.
     */
    public static function doRefreshToken($endpoint_url)
    {
        try {
            if (($endpoint_url = trim(( string )$endpoint_url)) == '') {
                throw new Exception ('Endpoint URL is empty');
            }
            if (!($refresh_token = Identity::getThings()->getRefreshToken()) instanceof RefreshToken) {
                throw new Exception ('Refresh token is empty');
            }

            // Send request.
            $params = [];
            $params['grant_type'] = self::GRANT_TYPE_REFRESH_TOKEN;
            $params['refresh_token'] = $refresh_token->getValue();
            $response = Request::execute($endpoint_url, $params, Request::HTTP_POST, Request::SECURED);

            self::checkErrors($response);

            if (empty($response['result']->access_token)) {
                throw new Exception('The access_token retrieved is empty');
            }
            if (empty($response['result']->refresh_token)) {
                throw new Exception('The refresh_token retrieved is empty');
            }

            list($expires_in, $expires_at, $refresh_expires_at) = self::generateExpires($response);

            $result = [
                'access_token' => new AccessToken (trim($response ['result']->access_token), $expires_in, $expires_at, '/'),
                'refresh_token' => new RefreshToken (trim($response ['result']->refresh_token), 0, $refresh_expires_at, '/')
            ];

            self::storeToken($result['access_token']);
            self::storeToken($result['refresh_token']);

            $loginStatus = new LoginStatus();
            if (isset ($response ['result']->login_status)) {
                $loginStatus->setCkusid($response['result']->login_status->uid);
                $loginStatus->setOid($response['result']->login_status->oid);
                $loginStatus->setConnectState($response['result']->login_status->connect_state);
            }
            $result['login_status'] = $loginStatus;

            return $result;
        } catch (InvalidGrantException $e) {
            throw new InvalidGrantException($e->getMessage());
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Checks if user is logged.
     *
     * @param string $endpoint_url The endpoint where the request will be sent.
     * @return LoginStatus An object with user status.
     * @throws \Exception If there is an error.
     */
    public static function doValidateBearer($endpoint_url)
    {
        try {
            if (($endpoint_url = trim(( string )$endpoint_url)) == '') {
                throw new Exception ('Endpoint URL is empty');
            }
            if (!(($access_token = Identity::getThings()->getAccessToken()) instanceof AccessToken) || ($access_token->getValue() == '')) {
                throw new Exception ('Access token is empty');
            }

            $params = [
                'grant_type' => self::GRANT_TYPE_VALIDATE_BEARER,
                'oauth_token' => $access_token->getValue()
            ];

            unset ($access_token);
            $response = Request::execute($endpoint_url, $params, Request::HTTP_POST, Request::SECURED);

            self::checkErrors($response);

            $loginStatus = new LoginStatus();
            if (isset ($response ['result']->login_status)) {
                $loginStatus->setCkusid($response['result']->login_status->uid);
                $loginStatus->setOid($response['result']->login_status->oid);
                $loginStatus->setConnectState($response['result']->login_status->connect_state);
            }

            return $loginStatus;
        } catch (InvalidGrantException $e) {
            throw new InvalidGrantException($e->getMessage());
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Checks if user is logged by Exchange Session (SSO)
     *
     * @param string $endpoint_url The endpoint where the request will be sent.
     * @param string $cookie_value The content of the cookie that stores the SSO.
     * @return array An instance of {@link AccessToken} if its connected or
     *     NULL if not.
     * @throws InvalidGrantException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public static function doExchangeSession($endpoint_url, $cookie_value)
    {
        try {
            $access_token = null;

            if (($endpoint_url = trim(( string )$endpoint_url)) == '') {
                throw new Exception ('Endpoint URL is empty');
            }
            if (($cookie_value = trim($cookie_value)) == '') {
                throw new Exception ('SSO cookie is empty');
            }

            $params = [
                'grant_type' => self::GRANT_TYPE_EXCHANGE_SESSION
            ];
            $response = Request::execute($endpoint_url, $params, Request::HTTP_POST, Request::SECURED, [], [self::SSO_COOKIE_NAME => $cookie_value]);

            self::checkErrors($response);

            if (empty($response ['result']->access_token)) {
                throw new Exception ('The access_token retrieved is empty');
            }
            if (empty($response ['result']->refresh_token)) {
                throw new Exception ('The refresh_token retrieved is empty');
            }

            list($expires_in, $expires_at, $refresh_expires_at) = self::generateExpires($response);

            $result = [
                'access_token' => new AccessToken(trim($response['result']->access_token), $expires_in, $expires_at, '/'),
                'refresh_token' => new RefreshToken(trim($response['result']->refresh_token), 0, $refresh_expires_at, '/')
            ];

            self::storeToken($result['access_token']);
            self::storeToken($result['refresh_token']);

            $loginStatus = new LoginStatus();
            if (isset($response['result']->login_status)) {
                $loginStatus->setCkusid($response['result']->login_status->uid);
                $loginStatus->setOid($response['result']->login_status->oid);
                $loginStatus->setConnectState($response['result']->login_status->connect_state);
            }

            $result['login_status'] = $loginStatus;

            return $result;
        } catch (InvalidGrantException $e) {
            throw new InvalidGrantException($e->getMessage());
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Performs revocation process. Removes all tokens from that user.
     *
     * @param string $endpoint_url The endpoint where the request will be sent.
     * @return void
     * @throws \Exception If there is an error.
     */
    public static function doLogout($endpoint_url)
    {
        try {
            if (($endpoint_url = trim(( string )$endpoint_url)) == '') {
                throw new Exception ('Endpoint URL is empty');
            }
            if (!($refresh_token = Identity::getThings()->getRefreshToken()) instanceof RefreshToken) {
                throw new Exception ('Refresh token is empty');
            }

            $params = [
                'token' => $refresh_token->getValue(),
                'token_type' => 'refresh_token'
            ];
            unset ($refresh_token);
            Request::execute($endpoint_url, $params, Request::HTTP_POST, Request::SECURED);

            unset($_COOKIE[self::SSO_COOKIE_NAME]);
            setcookie(self::SSO_COOKIE_NAME, null, -1,null);

            self::deleteStoredToken(iTokenTypes::ACCESS_TOKEN);
            self::deleteStoredToken(iTokenTypes::REFRESH_TOKEN);

        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Removes a specific token.
     *
     * It will removed from SESSION and COOKIE.
     *
     * @param string The token we want to remove. Are defined in {@link iTokenTypes}
     * @return void
     */
    public static function deleteStoredToken ($name)
    {
        if (isset($_COOKIE[$name])) {
            setcookie($name, '', time()-42000, '/');
            unset($_COOKIE[$name]);
        }
    }


    /**
     * Checks if we have a specific token.
     *
     * @param string $name The token we want to check. Are defined in {@link iTokenTypes}
     * @return bool TRUE if exists or FALSE otherwise.
     */
    public static function hasToken($name)
    {
        return (self::getStoredToken($name) instanceof Token);
    }

    /**
     * Returns a specific stored token.
     * SESSION has more priority than COOKIE.
     *
     * @param string $name The token we want to recover. Are defined in {@link iTokenTypes}
     * @return bool|AccessToken|ClientToken|RefreshToken|mixed|string An instance of {@link Token} or FALSE if we
     *     can't recover it.
     * @throws \Exception
     */
    public static function getStoredToken($name)
    {
        if (($name = trim((string)$name)) == '') {
            throw new Exception ('Token type not exist');
        }

        $encryption = new Encryption(Identity::getOAuthConfig()->getClientId());
        if (isset($_COOKIE[$name])) {
            return Token::factory($name, $encryption->decode($_COOKIE[$name]), 0, 0, '/');
        } else {
            return null;
        }
    }

    /**
     * Checks if the user has completed all required data for the specified
     * section (scope).
     *
     * @param string $endpoint_url The endpoint where the request will be sent.
     * @param string $scope Section-key identifier of the web client. The
     *     section-key is located in "oauthconf.xml" file.
     * @return boolean TRUE if the user has completed all required data or
     *     FALSE if not.
     * @throws \Exception If there is an error.
     */
    public static function doCheckUserCompleted(string $endpoint_url, string $scope)
    {
        try {
            $response = self::checkCompleteData($endpoint_url, $scope);

            return call_user_func(function($result){
                $completed = true;
                if (isset($result->data) && is_array($result->data)) {
                    foreach ($result->data as $data) {
                        if ((isset($data->meta,$data->meta->data,$data->meta->data->needsToConfirmIds) && ($data->meta->data->needsToConfirmIds === 'true')) || (isset($data->meta,$data->meta->data,$data->meta->data->needsToCompleteData) && $data->meta->data->needsToCompleteData === 'true')){
                            $completed = false;
                        }
                    }
                }
                return $completed;
            }, $response['result']);

        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Checks if the user has accepted terms and conditions for the specified section (scope).
     *
     * @param string $endpoint_url The endpoint where the request will be sent.
     * @param string $scope Section-key identifier of the web client. The section-key is located in "oauthconf.xml" file.
     * @return boolean TRUE if the user need to accept the terms and conditions (not accepted yet) or
     *      FALSE if it has already accepted them (no action required).
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public static function doCheckUserNeedAcceptTerms($endpoint_url, $scope)
    {
        try {
            $response = self::checkCompleteData($endpoint_url, $scope);

            return call_user_func(function($result){
                if (isset($result->data) && is_array($result->data)) {
                    foreach ($result->data as $data) {
                        if (isset($data->meta,$data->meta->data,$data->meta->data->needsToAcceptTerms)) {
                            return $data->meta->data->needsToAcceptTerms === 'true';
                        }
                    }
                }
                return false;
            }, $response['result']);

        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * @param $endpoint_url
     * @param $scope
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private static function checkCompleteData($endpoint_url, $scope): array
    {
        if (($endpoint_url = trim(( string )$endpoint_url)) == '') {
            throw new Exception ('Endpoint URL is empty');
        }

        if (($scope = trim((string)$scope)) == '') {
            throw new Exception ('Scope is empty');
        }

        if (!(($access_token = Identity::getThings()->getAccessToken()) instanceof AccessToken) || ($access_token->getValue() == '')) {
            throw new Exception ('Access token is empty');
        }

        $params = [
            'oauth_token' => $access_token->getValue(),
            's' => 'needsToCompleteData',
            'f' => 'UserMeta',
            'w.section' => $scope
        ];

        $response = Request::execute($endpoint_url, $params, Request::HTTP_POST);

        self::checkErrors($response);
        return $response;
    }

    /**
     * @param array $response
     * @return array
     */
    private static function generateExpires(array $response): array
    {
        $expires_in = (!empty($response['result']->expires_in)) ? intval($response['result']->expires_in) : self::DEFAULT_EXPIRES_IN;

        $expires_in = ($expires_in - ($expires_in * self::SAFETY_RANGE_EXPIRES_IN));
        $expires_at = (time() + $expires_in);
        $refresh_expires_at = ($expires_at + (60 * 60 * 24 * 12));
        return [$expires_in, $expires_at, $refresh_expires_at];
    }


}
