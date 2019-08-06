<?php namespace Genetsis\Urls;


use Genetsis\Identity;

class DruidUrl
{
    use QueryBuilderTrait;

    const LOGIN = 'Login';
    const REGISTER = 'Register';
    const EDIT = 'Edit';
    const COMPLETE_ACCOUNT = 'CompleteAccount';

    private static $ids = ['email', 'screen_name', 'national_id', 'phone_number'];
    private static $location = ['telephone'];
    private static $location_address = ['streetAddress', 'locality', 'region', 'postalCode', 'country'];


    /** @var string */
    private $endpoint = '';

    /** @var string */
    private $scope = '';

    /** @var string */
    private $social = '';

    /** @var string */
    private $urlCallback = '';

    /** @var array */
    private $prefill = [];

    /** @var string */
    private $state = '';

    /**
     * Callback configured by Default
     *
     * DruidUrl constructor.
     */
    public function __construct()
    {
        $this->setUrlCallback(Identity::getOAuthConfig()->getCallback());
    }

    /**
     * @return string
     */
    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    /**
     * @param string $endpoint
     * @return DruidUrl
     */
    public function setEndpoint(string $endpoint): DruidUrl
    {
        $this->endpoint = $endpoint;
        return $this;
    }

    /**
     * @return string
     */
    public function getScope(): string
    {
        return $this->scope;
    }

    /**
     * @param string $scope
     * @return DruidUrl
     */
    public function setScope(string $scope): DruidUrl
    {
        $this->scope = $scope;
        return $this;
    }

    /**
     * @return string
     */
    public function getSocial(): string
    {
        return $this->social;
    }

    /**
     * @param string $social
     * @return DruidUrl
     */
    public function setSocial(string $social): DruidUrl
    {
        $this->social = $social;
        return $this;
    }

    /**
     * @return string
     */
    public function getUrlCallback(): string
    {
        return $this->urlCallback;
    }

    /**
     * @param string $urlCallback
     * @return DruidUrl
     */
    public function setUrlCallback(string $urlCallback): DruidUrl
    {
        $this->urlCallback = $urlCallback;
        return $this;
    }

    /**
     * @return array
     */
    public function getPrefill(): array
    {
        return $this->prefill;
    }

    /**
     * @param array $prefill
     * @return DruidUrl
     */
    public function setPrefill(array $prefill): DruidUrl
    {
        $this->prefill = $prefill;
        return $this;
    }

    /**
     * @return string
     */
    public function getState(): string
    {
        return $this->state;
    }

    /**
     * @param string $state
     * @return DruidUrl
     */
    public function setState(string $state): DruidUrl
    {
        $this->state = $state;
        return $this;
    }


    protected function arrayToUserJson(array $userInfo) {
        $user = array("objectType" => "user");

        foreach ($userInfo as $field => $value) {
            if (in_array($field, self::$ids)) {
                $user["ids"][$field] = array("value" => $value);
            } else if (in_array($field, self::$location)) {
                $user["location"][$field] = $value;
            } else if (in_array($field, self::$location_address)) {
                $user["location"]["address"][$field] = $value;
            } else { //is a data
                $user["datas"][$field] = array("value" => $value);
            }
        }

        return json_encode($user);
    }
}
