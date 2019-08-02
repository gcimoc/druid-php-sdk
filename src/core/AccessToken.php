<?php
namespace Genetsis\core;

/**
 * This class stores "access_token" data.
 *
 * @package   Genetsis
 * @category  Bean
 * @version   1.0
 * @access    public
 * @since     2011-09-08
 */
class AccessToken extends Token
{
    /**
     * @param string The token value.
     * @param integer Number the seconds until the token expires.
     * @param integer Date when the token expires. As UNIX timestamp.
     * @param string Full path to the folder where cookies will be saved.
     *     Only if necessary.
     */
    public function __construct($value, $expires_in = 0, $expires_at = 0, $path = '/')
    {
        parent::__construct($value, $expires_in, $expires_at, $path);
    }

    /**
     * Sets token name.
     *
     * @return void
     * @see iTokenTypes
     */
    protected function setName()
    {
        $this->name = iTokenTypes::ACCESS_TOKEN;
    }
}