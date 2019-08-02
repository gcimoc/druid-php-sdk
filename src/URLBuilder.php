<?php namespace Genetsis;

use Exception;
use Genetsis\core\OAuthConfig;
use Genetsis\Druid\Exceptions\IdentityException;
use Genetsis\Urls\DruidUrl;

/**
 * This class is used to build the links to different services of DruID.
 *
 * @package   Genetsis
 * @category  Helper
 * @version   2.0
 * @access    private
 */
class URLBuilder
{

    /**
     * @param string|null $type
     * @return DruidUrl
     * @throws IdentityException
     */
    public static function create(string $type = null) : DruidUrl
    {
        $class = __NAMESPACE__.'\Urls\\'.ucfirst($type);

        if (!class_exists($class)) {
            Identity::getLogger()->error("Druid Url Type Not Defined: ".$class);
            throw new IdentityException("Invalid URL type");
        }

        return new $class;
    }
}