<?php namespace Genetsis\core;

/**
 * Abstract class which aims to be the parent class of the different types
 * of tokens.
 *
 * @package   Genetsis
 * @category  Bean
 * @version   1.0
 * @access    public
 * @since     2011-09-08
 */
abstract class Token
{
    /** @var string The token name. */
    protected $name = null;
    /** @var string The token value. */
    protected $value = null;
    /** @var integer integer Number the seconds until the token expires. */
    protected $expires_in = null;
    /** @var integer Date when the token expires. As UNIX timestamp. */
    protected $expires_at = null;
    /** @var string Full path to the folder where cookies will be saved. */
    protected $path = '/';

    /**
     * @param string The token value.
     * @param integer Number the seconds until the token expires.
     * @param integer Date when the token expires. As UNIX timestamp.
     * @param string Full path to the folder where cookies will be saved.
     *     Only if necessary.
     */
    public function __construct($value, $expires_in = 0, $expires_at = 0, $path = '/')
    {
        $this->setValue($value);
        $this->setExpiresIn($expires_in);
        $this->setExpiresAt($expires_at);
        $this->setPath($path);

        $this->setName();
    }

    /**
     * Create an instance of an access token based on the name.
     *
     * @param string $name The token name.
     * @param sting $value The token value.
     * @param $expires_in Number the seconds until the token expires.
     * @param $expires_at Date when the token expires. As UNIX timestamp.
     * @param $path Full path to the folder where cookies will be saved.
     * @return bool|AccessToken|ClientToken|RefreshToken An object of type {@link Token} or FALSE if
     *     unable to create it.
     */
    public static function factory($name, $value, $expires_in, $expires_at, $path)
    {
        switch (trim((string)$name)) {
            case iTokenTypes::ACCESS_TOKEN:
                return new AccessToken ($value, $expires_in, $expires_at, $path);
            case iTokenTypes::CLIENT_TOKEN:
                return new ClientToken ($value, $expires_in, $expires_at, $path);
            case iTokenTypes::REFRESH_TOKEN:
                return new RefreshToken($value, $expires_in, $expires_at, $path);
        }
        return false;
    }

    /**
     * Returns the token name.
     *
     * We use it for serialization the token content.
     *
     * @return string The token name.
     * @see iTokenTypes
     */
    public function getName()
    {
        return ((!isset($this->name) || ($this->name === null))
            ? ''
            : $this->name);
    }

    /**
     * Sets token name.
     *
     * We delegate this method to child classes.
     *
     * @return void
     * @see iTokenTypes
     */
    abstract protected function setName();

    /**
     * Returns the token value.
     *
     * @return string The token value. It could be empty.
     */
    public function getValue()
    {
        return ((!isset($this->value) || ($this->value === null))
            ? ''
            : $this->value);
    }

    /**
     * Sets token value.
     *
     * @param string Token value.
     * @return void
     */
    public function setValue($value)
    {
        $this->value = trim((string)$value);
    }

    /**
     * Returns the number of seconds when token expires.
     *
     * @return integer The number of seconds.
     */
    public function getExpiresIn()
    {
        return ((!isset($this->expires_in) || ($this->expires_in === null))
            ? 0
            : $this->expires_in);
    }

    /**
     * Sets the number of seconds when token expires.
     *
     * @param integer The number of seconds it takes to die.
     * @return void
     */
    public function setExpiresIn($expires_in)
    {
        $this->expires_in = (is_integer($expires_in)
            ? $expires_in
            : (int)$expires_in);
        if ($this->expires_in < 0) {
            $this->expires_in = 0;
        }
    }

    /**
     * Returns the date when the "token" should be dead.
     *
     * @return integer UNIX timestamp with the date. Zero if not defined.
     */
    public function getExpiresAt()
    {
        return ((!isset($this->expires_at) || ($this->expires_at === null))
            ? 0
            : $this->expires_at);
    }

    /**
     * Sets the date when the "token" should be dead.
     *
     * @param integer UNIX timestamp.
     * @return void
     */
    public function setExpiresAt($expires_at)
    {
        $this->expires_at = (is_integer($expires_at)
            ? $expires_at
            : (int)$expires_at);
        if ($this->expires_at < 0) {
            $this->expires_at = 0;
        }
    }

    /**
     * Returns the path to cookie folder.
     *
     * @return string The full path to the folder where the cookies will be
     *     saved.
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Sets the path where the cookies will be saved.
     *
     * @param string Full path to the folder.
     * @return void
     * @todo Checks if path exists and is writable.
     */
    public function setPath($path)
    {
        $this->path = trim((string)$path);
    }
}