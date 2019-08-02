<?php namespace Genetsis\Config;

use Genetsis\Identity;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Middleware;
use Psr\Log\LogLevel;

class Http extends AbstractConfig
{

    /**
     * @inheritdoc
     */
    protected function getName() : string
    {
        return 'http';
    }

    /**
     * @param array $options
     *
     * @return ClientInterface $http
     */
    public function config(array $options)
    {
        // We associate the logger received with the Guzzle client to register the requests.
        $stack = HandlerStack::create();
        $stack->push(Middleware::log(Identity::getLogger(), new MessageFormatter(), LogLevel::INFO));
        $stack->push(Middleware::log(Identity::getLogger(), new MessageFormatter("\n-----------------\nREQUEST: {request}\n\nRESPONSE:\n{response}\n-----------------\n"), LogLevel::DEBUG));

        $options['handler'] = $stack;
        $client_options = $this->getAllowedClientOptions($options);

        return new Client(
            array_intersect_key($options, array_flip($client_options))
        );
    }

    /**
     * Returns the list of options that can be passed to the HttpClient
     *
     * @param array $options An array of options to set on this provider.
     *     Options include `clientId`, `clientSecret`, `redirectUri`, and `state`.
     *     Individual providers may introduce more options, as needed.
     * @return array The options to pass to the HttpClient constructor
     */
    protected function getAllowedClientOptions(array $options)
    {
        $client_options = ['timeout', 'proxy', 'handler'];
        // Only allow turning off ssl verification if it's for a proxy
        if (!empty($options['proxy'])) {
            $client_options[] = 'verify';
        }
        return $client_options;
    }
}