<?php

namespace Genetsis\core;

/**
 * This class stores login status.
 *
 * @package   Genetsis
 * @category  Bean
 * @version   1.0
 * @access    private
 */
class LoginStatus
{
    /** @var Ckusid of user logged. */
    private $ckusid = null;
    /** @var ObjectID of user logged. */
    private $oid = null;
    /** @var LoginStatusType to identify the user connection status. */
    private $connect_state = null;

    /**
     * @param LoginStatusType $connect_state
     */
    public function setConnectState($connect_state)
    {
        $this->connect_state = $connect_state;
    }

    /**
     * @return LoginStatusType
     */
    public function getConnectState()
    {
        return $this->connect_state;
    }

    /**
     * @param $ckusid
     */
    public function setCkusid($ckusid)
    {
        $this->ckusid = $ckusid;
    }

    /**
     * @return Ckusid
     */
    public function getCkusid()
    {
        return $this->ckusid;
    }

    /**
     * @param \Genetsis\core\ObjectID $oid
     */
    public function setOid($oid)
    {
        $this->oid = $oid;
    }

    /**
     * @return \Genetsis\core\ObjectID
     */
    public function getOid()
    {
        return $this->oid;
    }
}