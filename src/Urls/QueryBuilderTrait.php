<?php namespace Genetsis\Urls;

/**
 * Provides a standard way to generate query strings.
 */
trait QueryBuilderTrait
{
    /**
     * Build a query string from an array.
     *
     * @param array $params
     *
     * @return string
     */
    protected function buildQueryString(array $params) : string
    {
        return http_build_query($params, null, '&', \PHP_QUERY_RFC3986);
    }

    /**
     * Appends a query string to a URL.
     *
     * @param  string $url The URL to append the query to
     * @param  string $query The HTTP query string
     * @return string The resulting URL
     */
    protected function appendQuery(string $url, array $query) : string
    {
        $query = trim($this->buildQueryString($query), '?&');
        if ($query) {
            $glue = strstr($url, '?') === false ? '?' : '&';
            return $url . $glue . $query;
        }
        return $url;
    }
}