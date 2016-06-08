<?php
namespace RESTling;

class Validator extends Logger
{
    protected $service;  ///< internal handler for the service class.
    protected $method;   ///< the method to be validated; <- this is the operation to be called!
    protected $ignoreOps; ///< An object with the methods to be validated. <- possible excluded operations

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
    final public function ignoreOperations($methodObject)
    {
        if (!isset($this->ignoreOps))
        {
            $this->ignoreOps = array();
        }

        if (isset($methodObject) && !empty($methodObject))
        {
            foreach($methodObject as $value)
            {
                if (!in_array($value, $this->oMethod))
                {
                    $this->ignoreOps[] = $value;
                }
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
    final public function setMethod($methodName)
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
    final public function setData($data, $type)
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
     *
     * The validation runs in three steps:
     *
     * 1. skip operations that are marked to be ignored
     * 2. run the generic validation
     * 3. if defined run operation specific validation.
     *
     * for step 2 you will have to implement a validate() function
     *
     * for step 3 you have to implement a validate_<operation> function.
     *
     * Both function MUST return a boolean value to indicate whether the
     * validation succeeded.
     *
     * Operation specific validation:
     *
     * Imagine that your service class has a handler method ```get_test()``` and and many other operations.
     * While all other operations just need the generic validation, ```get_test()``` requires something special.
     *
     * In your validator instance you will then implement the following
     *
     * ```
     * protected function validate() {
     *     ... // check generic operation preconditions.
     *     return true;
     * }
     *
     * protected function validate_get_test() {
     *      ... // check  special preconditions for this case.
     *      return true;
     * }
     * ```
     *
     */
    final public function run()
    {

        $this->state = 1;

        if (isset($this->ignoreOps) &&
            in_array($this->method, $this->ignoreOps))
        {
            return true;
        }

        // generic validate
        if(!$this->validate())
        {
            $this->state = -1;
            return false;
        }

        // method specific validation
        $fMethod = "validate_". $this->method;

        if (method_exists($this, $fMethod) &&
            !call_user_func(array($this, $fMethod)))
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
    final public function isValid()
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
