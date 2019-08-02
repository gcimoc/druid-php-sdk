<?php namespace Genetsis\core;

use Genetsis\Identity;

class Request
{
    /** Http Methods */
    const HTTP_POST = 'POST';
    const HTTP_PUT = 'PUT';
    const HTTP_GET = 'GET';
    const HTTP_DELETE = 'DELETE';
    const HTTP_HEAD = 'HEAD';

    const SECURED = true;
    const NOT_SECURED = false;

    /**
     * @param $url  Endpoint where the request is sent. Without params.
     * @param array $parameters mixed Associative vector with request params. Use key as param name, and value as value. The values shouldn't be prepared.
     * @param string $http_method
     *        - {@link self::HTTP_GET}
     *        - {@link self::HTTP_POST}
     *        - {@link self::HTTP_METHOD_HEAD}
     *        - {@link self::HTTP_METHOD_PUT}
     *        - {@link self::HTTP_METHOD_DELETE}
     * @param bool $credentials If true, client_id and client_secret are included in params
     * @param array $http_headers  A vector of strings with HTTP headers or FALSE if no additional headers to sent.
     * @param array $cookies A vector of strings with cookie data or FALSE if no cookies to sent. One line per cookie ("key=value"), without trailing semicolon.
     * @return array An associative array with that items:
     *     - result: An string or array on success, or FALSE if there is no result.
     *     - code: HTTP code.
     *     - content-type: Content-type related to result
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public static function execute($url, $parameters = [], $http_method = self::HTTP_GET, $credentials = self::NOT_SECURED, $http_headers = [], $cookies = [])
    {
        if (!extension_loaded('curl')) {
            throw new \Exception('The PHP extension curl must be installed to use this library.');
        }

        if ($credentials) {
            $parameters['client_id'] = Identity::getOAuthConfig()->getClientId();
            $parameters['client_secret'] = Identity::getOAuthConfig()->getClientSecret();
        }

        $response = Identity::getHttpClient()->request(
            $http_method,
            $url,
            [
                'headers' => $http_headers,
                'form_params' => $parameters,
                'http_errors' => false
            ]
        );

        return array(
            'result' => ($response->getHeaderLine('Content-Type') === 'application/json') ? json_decode($response->getBody()) : $response->getBody(),
            'code' => $response->getStatusCode(),
            'content_type' => $response->getHeader('Content-Type')
        );
    }
} 