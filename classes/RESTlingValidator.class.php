<?php
class RESTlingValidator extends Logger
{
    protected $service;  ///< internal handler for the service class.
    protected $method;   ///< the method to be validated; <- this is the operation to be called!
    protected $oMethods; ///< An object with the methods to be validated. <- possible excluded operations

    protected $data;
    protected $type;

    private $state = 0;  ///< returns the validation state; 0: not validated; -1: invalid; 1: valid

    public function setService($service)
    {
        $this->service = $service;
    }

    /**
     * @method setMethods
     * @param {OBJECT} $methodObject
     *
     * Interface for informing the validator, which methods to validate.
     *
     * The $methodObject is an key-value pair array, where the key is the
     * method name and the value is a boolean. TRUE values mean that the
     * request MUST get validated.
     *
     * FALSE values mean that the request MUST NOT get validated, in this
     * case the request is automatically considered as valid.
     *
     * A method that does appear in the $methodObject will get always
     * validated.
     *
     * NOTE: The options method will never get validated by the header
     * validation.
     */
    public function setMethods($methodObject)
    {
        if (!isset($this->oMethods))
        {
            $this->oMethods = array();
        }
        if (isset($methodObject) && !empty($methodObject))
        {
            foreach($methodObject as $key => $value)
            {
                $this->oMethods[$key] = $value;
            }
        }
    }

    /**
     * @method setMethod()
     * @param {STRING} methodName
     *
     * Sets the method name that will get called during the active request.
     *
     * This method is called by the RESTling run() method.
     */
    public function setMethod($methodName)
    {
        if (isset($methodName) && !empty($methodName))
        {
            $this->method = $methodName;
        }
    }

    /**
     * @public @method setData($data, $type)
     *
     * used for data validators. receives the data object to be validated.
     */
    public function setData($data, $type)
    {
        if (isset($data))
        {
            $this->data = $data;
        }

        if (isset($type) && !empty($type))
        {
            $this->type = $type;
        }
    }

    /**
     * @function run()
     *
     * executes the validation.
     *
     * This method should be FINAL and must not be overloaded.
     */
    public function run()
    {

        $this->state = 1;

        if (isset($this->oMethods) &&
            array_key_exists($this->method, $this->oMethods) &&
            !$this->oMethods[$this->method])
        {
            return true;
        }

        if(!$this->validate())
        {
            $this->state = -1;
            return false;
        }

        return true;
    }

    /**
     * @function validate()
     *
     * This does the actual validation after the method validation has passed.
     */
    protected function validate()
    {
        return true;
    }

    /**
     * @function isValid()
     *
     * returns the validation state
     *
     * possible states are -1: failed validation; 0: not processed; and 1: validation succeeded.
     */
    public function isValid()
    {
        return $this->state;
    }

    public function error()
    {
        if ($this->state < 0)
        {
            // return authentication required by default
            $this->service->authentication_required();
            return "";
        }
    }

    /**
     * @public @function mandatory()
     *
     * if a validator is marked mandatory by  returning true from this function, the
     * validation will immediately fail.
     */
    public function mandatory() {
        return false;
    }
}
?>
