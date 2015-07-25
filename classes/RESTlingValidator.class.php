<?php
class RESTlingValidator extends Logger
{
    protected $service;  ///< internal handler for the service class.
    protected $method;   ///< the method to be validated;
    protected $oMethods; ///< An object with the methods to be validated.

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
     * request MUST get validated. FALSE values mean that the request
     * MUST NOT get validated, in this case the request is considered as
     * valid.
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
     * @function run()
     *
     * executes the validation.
     *
     * This method should be FINAL and must not be overloaded.
     */
    public function run()
    {
        if (isset($this->oMethods) &&
            array_key_exists($this->method, $this->oMethods) &&
            !$this->oMethods[$this->method])
        {
            return true;
        }

        return $this->validate();
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

    public function error()
    {
        // return authentication required by default
        $this->service->authentication_required();
        return "";
    }
}
?>
