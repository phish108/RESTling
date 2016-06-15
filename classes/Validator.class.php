<?php
namespace RESTling;

class Validator extends Logger
{
    protected $service;  ///< internal handler for the service class.
    protected $method;   ///< the HTTP method to be validated;
    protected $operation; ///< this is the operation to be called!
    protected $path_info; ///< the request's processed pathinfo

    protected $ignoreOps; ///< An object with the methods to be validated. <- possible excluded operations
    protected $ignoreMethods;

    protected $data;
    protected $type;
    protected $param; ///< processed query parameters

    private $state = 0;  ///< returns the validation state; 0: not validated; -1: invalid; 1: valid

    /**
     * @public @method setService($service)
     * @public @method setPathInfo($pathinfo)
     * @public @method setOperation($pathinfo)
     * @public @method setMethod($pathinfo)
     * @public @method setData($data, $type)
     *
     * Setters used by the RESTling\Service class in order to pass the
     * processed request data to the validator.
     *
     * setData() is only used for data validators
     * (set via RESTling\Service::addDataValidators()).
     */
    public function setService($service)
    {
        $this->service = $service;
    }

    public function setPathInfo($pathinfo) {
        if (!empty($pathinfo))
        {
            $this->path_info = $pathinfo;
        }
    }

    public function setOperation($op) {
        if (!empty($op))
        {
            $this->operation = $op;
        }
    }

    final public function setMethod($methodName)
    {
        if (!empty($methodName))
        {
            $this->method = $methodName;
        }
    }

    final public function setData($data, $type)
    {
        if (!empty($data))
        {
            $this->data = $data;
        }

        if (!empty($type))
        {
            $this->type = $type;
        }
    }

    final public function setParam($data)
    {
        if (!empty($data))
        {
            $this->param = $data;
        }
    }

    /**
     * @public @method ignoreOperations($names)
     * @public @method ignoreMethods($names)
     * @param {array} $names
     *
     * informing the validator to automatically accept the provided list
     * of names.
     *
     * ignoreOperations ignores the computed operations to be called.
     * ignoreMethods ignores the provided HTTP methods.
     *
     * The validator will first check for the http method and then for the operations.
     * If the "get" method is ignore the will automatically ignore all subordinate
     * operations (e.g. get_example() will not be validated).
     *
     * For complex service APIs it is recommended to ignore individual operations.
     */
    final public function ignoreOperations($methodObject)
    {
        if (!isset($this->ignoreOps))
        {
            $this->ignoreOps = array();
        }

        if (!empty($methodObject))
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

    final public function ignoreMethods($methodObject)
    {
        if (!isset($this->ignoreOps))
        {
            $this->ignoreOps = array();
        }

        if (!empty($methodObject))
        {
            foreach($methodObject as $value)
            {
                $value = strtolower($value);
                if (!in_array($value, $this->oMethod))
                {
                    $this->ignoreMethods[] = $value;
                }
            }
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

        if (!empty($this->ignoreMethods) &&
            in_array($this->method, $this->ignoreMethods))
        {
            return true;
        }

        if (!empty($this->ignoreOps) &&
            in_array($this->operation, $this->ignoreOps))
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
        $fMethod = "validate_". $this->operation;

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
        return ($this->state > 0);
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
