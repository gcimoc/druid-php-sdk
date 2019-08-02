<?php namespace Genetsis\Config;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Processor\WebProcessor;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class Logger extends AbstractConfig
{
    /**
     * @inheritdoc
     */
    protected function getName() : string
    {
        return 'logger';
    }

    /**
     * @param array $options
     * @return LoggerInterface $logger
     * @throws \Exception
     */
    public function config(array $options)
    {
        $logLevel = (empty($options['logLevel'])) ? LogLevel::DEBUG : $options['logLevel'];
        $logDir = ((empty($options['logDir'])) ? '' : $options['logDir']). 'identity-sdk.log';

        $logger = new \Monolog\Logger('identity-sdk');
        $stream = new StreamHandler($logDir, $logLevel);
        $formater = new LineFormatter("[%datetime%] %channel%.%level_name%: %message% %extra%\n");
        $formater->includeStacktraces();
        $formater->ignoreEmptyContextAndExtra();
        $stream->setFormatter($formater);
        $logger->pushHandler($stream);
        $logger->pushProcessor(new IntrospectionProcessor(LogLevel::ERROR));

        return $logger;
    }
}