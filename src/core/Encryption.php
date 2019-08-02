<?php

namespace Genetsis\core;

/**
 * This class is used to wrap encryption functions.
 *
 * @package   Genetsis
 * @category  Helper
 * @version   1.0
 * @access    private
 */
class Encryption
{
    /** @var string The secret key to encrypt data. */
    private $_skey = "yourSecretKey";

    const METHOD = 'aes-256-cbc';

    /**
     * @param string The secret key to encrypt data.
     */
    public function __construct($client_secret)
    {
        $this->_skey = str_pad(trim((string)$client_secret), 32, '\0');
    }

    /**
     * Encodes a string using a secret key.
     *
     * @param string The string to be encoded.
     * @return mixed The string encoded or FALSE.
     */
    public function encode($value)
    {
        if (!$value) {
            return false;
        }

        $ivsize = openssl_cipher_iv_length(self::METHOD);
        $iv = openssl_random_pseudo_bytes($ivsize);

        $ciphertext = openssl_encrypt(
            $value,
            self::METHOD,
            $this->_skey,
            OPENSSL_RAW_DATA,
            $iv
        );

        return trim($this->safe_b64encode($iv . $ciphertext));

        /*$iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
        $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
        $crypttext = mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $this->_skey, $value, MCRYPT_MODE_ECB, $iv);
        return trim($this->safe_b64encode($crypttext)); */
    }

    /**
     * Encodes a string using base64.
     *
     * @param $string The string to be encoded.
     * @return mixed The encoded string.
     */
    public function safe_b64encode($string)
    {
        return str_replace(array('+', '/', '='), array('-', '_', ''), base64_encode($string));
    }

    /**
     * Decodes a string using a secret key.
     *
     * @param string The string to be decoded.
     * @return mixed The string decoded or FALSE.
     */
    public function decode($value)
    {
        if (!$value) {
            return false;
        }

        $value = $this->safe_b64decode($value);
        $ivsize = openssl_cipher_iv_length(self::METHOD);
        $iv = mb_substr($value, 0, $ivsize, '8bit');
        $ciphertext = mb_substr($value, $ivsize, null, '8bit');

        return trim(
            openssl_decrypt(
                $ciphertext,
                self::METHOD,
                $this->_skey,
                OPENSSL_RAW_DATA,
                $iv
            )
        );

        /*$iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
        $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
        $decrypttext = mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $this->_skey, $this->safe_b64decode($value), MCRYPT_MODE_ECB, $iv);
        return trim($decrypttext); */
    }

    /**
     * Decodes base64 encoded string.
     *
     * @param $string The string to be decoded.
     * @return The string decoded.
     */
    public function safe_b64decode($string)
    {
        $data = str_replace(array('-', '_'), array('+', '/'), $string);
        $mod4 = (strlen($data) % 4);
        if ($mod4) {
            $data .= substr('====', $mod4);
        }
        return base64_decode($data);
    }
}