<?php

namespace RESTling;

use RESTling\Input  as BaseParser;
use RESTling\Output as BaseResponder;

class Service implements Interfaces\Service
{
    private $model;
    private $securityModel;
    protected $inputHandler;
    private $outputHandler;
    private $securityHandler = [];

    private $responseCode = 200;

    private $inputContentTypeMap = [
        "application/json"                   => "RESTling\\Input\\JSON",
        "application\/x-www-form-urlencoded" => "RESTling\\Input",
        "multipart/form-data"                => "RESTling\\Input\\MultiPartForm",
        // "text/xml"                           => "RESTling\\Input\\XML",
        "text/yaml"                          => "RESTling\\Input\\YAML",
        "text/vnd.yaml"                      => "RESTling\\Input\\YAML",
        "application/x-yaml"                 => "RESTling\\Input\\YAML",
        "text/x-yaml"                        => "RESTling\\Input\\YAML"
                              ];

    private $outputContentTypeMap = [
        "application/json"                   => "RESTling\\Output\\JSON",
        // "application\/x-www-form-urlencoded" => "RESTling\\Output\\FormData",
        // "multipart/form-data"                => "RESTling\\Output\\MultiPartForm",
        // "text/xml"                           => "RESTling\\Output\\XML",
        "text/yaml"                          => "RESTling\\Output\\YAML",
        "text/vnd.yaml"                      => "RESTling\\Output\\YAML",
        "application/x-yaml"                 => "RESTling\\Output\\YAML",
        "text/x-yaml"                        => "RESTling\\Output\\YAML",
        "text/plain"                         => "RESTling\\Output",
        "*/*"                                => "RESTling\\Output"
                              ];

    protected $error;
    protected $operation;

    private $corsHosts = [];

    protected $preferredOutputType;
    protected $availableOutputTypes = [];
    protected $path_info = "";
    protected $allowedContentTypes = [];

    /**
     * @public constructor()
     *
     * instantiates the service class. It performs no other operation.
     */
    public function __construct() {}

    /**
     * @final @public @method setModel($model[, $secure])
     * @parameter RESTling\Model $model
     * @parameter @optional bool $secure, @default false
     *
     * Sets the handler model.
     *
     * @throws Exception "Not a RESTling\Model"
     *
     * @throws Exception "Model Already Set"
     * If secure validates to true, then this method will throw an Exception if
     * the model is already set.
     */
    final public function setModel($m, $secure = false) {
        if ($secure && $this->model) {
            throw new Exception\ModelAlreadySet();
        }
        if (!($m && $m instanceof \RESTling\Interfaces\Model)) {
            throw new Exception\ModelInterfaceMismatch();
        }
        $this->model = $m;
    }

    final protected function getModel() {
        return $this->model;
    }

    final public function setSecurityModel($model, $secure = false) {
        if ($secure && $this->securityModel) {
            throw new Exception\ModelAlreadySet();
        }
        if (!($model && $model instanceof \RESTling\Interfaces\Security\Model)) {
            throw new Exception\SecurityModelInterfaceMismatch();
        }
        $this->model = $model;
    }

    /**
     * @final @protected @function bool hasModel()
     * returns bool
     *
     * This function allows to check if a model is present. Returns true if a
     * model is set.
     */
    final protected function hasModel() {
        return ($this->model && $this->model instanceof \RESTling\Interfaces\Model);
    }

    /**
     * @final @protected @function bool noModel()
     *
     * Inverse of hasModel().
     */
     final protected function noModel() {
         return (!$this->hasModel());
     }


    final public function addSecurityHandler($h) {
        if (!($h && $h instanceof \RESTling\Interfaces\Security)) {
            throw new Exception\SecurityInterfaceMismatch();
        }
        $this->securityHandler[] = $h;
    }

    final public function addCORSHost($host, $aMethods) {
        if (!((is_string($host) || is_array($host)) &&
              (is_string($aMethods) || is_array($aMethods)))) {
            throw new Exception\InvalidCorsParameter();
        }
        // host can be an array. The methods are an array too. Note that this is
        // not associative and that the methods are allowed for the provided
        // hosts.
        if (gettype($aMethods) === 'string')
        {
            $aMethods = explode(" ", $aMethods);
        }

        $methods = [];

        foreach ($aMethods as $m) {
            $methods[] = strtoupper($m);
        }

        if ( gettype($host) === 'string' )
        {
            $host = explode(" ", $host);
        }

        foreach ($host as $h)
        {
            if (array_key_exists($h, $this->corsHosts)) {
                // merge arrays
                foreach ($methods as $m) {
                    if (!in_array($m, $this->corsHosts[$h])) {
                        $this->corsHosts[$h][] = $m;
                    }
                }
            }
            else {
                $this->corsHosts[$h] = $methods;
            }
        }
    }

    /**
     *
     */
    final public function addInputContentTypeMap($contentType, $handlerType) {
        if (gettype($contentType) == "string" &&
            gettype($handlerType) == "string")
        {
            $this->inputContentTypeMap[$contentType] = $handlerType;
        }
    }

    /**
     *
     */
    final public function addOutputContentTypeMap($contentType, $handlerType) {
        if (gettype($contentType) == "string" &&
            gettype($handlerType) == "string")
        {
            $this->outputContentTypeMap[$contentType] = $handlerType;
        }
    }

    /**
     * @public @method run();
     *
     * The run() method implements the core request processing.
     */
    final public function run($model = null) {

        if ($model && !($model instanceof \RESTling\Interfaces\Model)) {
            $this->error = "No RESTling\ModelInterface Provided";
        }
        elseif ($model) {
            $this->setModel($model);
        }

        $fLoop = [
            "verifyModel",
            "findOperation",
            "parseInput",
            "validateAuthorization",
            "validateInput",
            "performOperation",
            "prepareOutputProcessor"
        ];

        if (empty($this->error)) {
            foreach ($fLoop as $func) {
                if (method_exists($this, $func)) {
                    try {
                        call_user_func(array($this, $func));
                    }
                    catch (Exception $err) {
                        $this->error = $err->getMessage();
                        break;
                    }
                }
            }
        }

        $this->handleError();
        $this->processResponse();

        ob_flush();// definitely terminate pass all response content

        set_time_limit(0); // allow long running processes
        $this->postProcess();
    }

    /**
     * @protected @function
     */
    protected function verifyModel() {
        if ($this->noModel()) {
            throw new Exception\MissingModel();
        }
    }

    /**
     *
     */
    protected function findOperation() {
        if (!$this->operation) {
            $op = strtolower($_SERVER["REQUEST_METHOD"]);

            if (array_key_exists("PATH_INFO", $_SERVER)) {
                $pi = explode("/",$_SERVER["PATH_INFO"]);
                array_shift($pi); // remove the first empty string;
                foreach ($pi as $pElement) {
                    $pElement = trim(urldecode($pElement));
                    if (empty($pElement)) {
                        next;
                    }
                    $pElement = strtolower($pElement);
                    if (method_exists($this->model, "$op_$pElement")) {
                        $op = "$op$pElement";
                    }
                }
            }
            $this->operation = $op;
        }

        if (!method_exists($this->model, $this->operation)) {
            throw new Exception\NotImplemented();
        }
    }

    /**
     *
     */
    protected function validateAuthorization() {
        if (!empty($this->securityHandler)) {
            $validation = false;

            $model = $this->securityModel;
            if (!$model) {
                $model = $this->model;
            }

            foreach ($this->securityHandler as $handler) {
                try {
                    $handler->validate($model, $this->inputHandler);
                }
                catch (Exception $err) {
                    // explicit failure means that the authorization MUST NOT
                    // be granted
                    $this->outputHandler->addTraceback($err->getMessage());
                    throw new Exception\AuthorizationRequired();
                }
                $validation = ($validation || $handler->passes());
            }

            if (!$validation) {
                // at least one security handler must accept
                throw new Exception\AuthorizationRequired();
            }

            $validation = false;
            foreach ($this->securityHandler as $handler) {
                // NOTE: The operation model MUST provide the scope validation
                // this is because the different platforms don't allow
                // generalizing the privilege system.
                try {
                    $handler->verify($this->model, $this->inputHandler);
                }
                catch (Exception $err) {
                    // explicit failure means that the authorization MUST NOT
                    // be granted
                    $this->outputHandler->addTraceback($err->getMessage());
                    throw new Exception\Forbidden();
                }
                $validation = ($validation || $handler->passes());
            }

            if (!$validation) {
                // at least one security handler must accept the scope
                throw new Exception\Forbidden();
            }
        }
    }

    /**
     *
     */
    protected function parseInput() {
        $method = $_SERVER["REQUEST_METHOD"];

        if ($method === "PUT" ||
            $method === "POST") {

            $ctHead = explode(";", $_SERVER['CONTENT_TYPE'], 2);
            $contentType = array_shift($ctHead);

            if (!empty($this->allowedContentTypes) && !in_array($contentType, $this->allowedContentTypes)) {
                throw new Exception\InvalidContentType();
            }

            if (array_key_exists($contentType, $this->inputContentTypeMap)) {
                $className = $this->inputContentTypeMap[$contentType];

                if (!class_exists($className, true)) {
                    throw new Exception\MissingContentParser();
                }

                $this->inputHandler = new $className();
                $this->inputHandler->parse();
                $this->inputHandler->setContentType($contentType);
                $this->inputHandler->path_info = $this->path_info;
            }
        }

        if (!$this->inputHandler) {
            $this->inputHandler = new BaseParser();
        }
    }

    protected function validateInput() {
    }

    /**
     *
     */
    private function performOperation() {
        if ($this->noModel() ||
            !method_exists($this->model, $this->operation)) {
            throw new Exception\NotImplemented();
        }

        call_user_func(array($this->model,
                             $this->operation), $this->inputHandler);
    }

    /**
     *
     */
    protected function prepareOutputProcessor() {
        // if the model requested a delayed redirect
        if ($this->model &&
            !empty($this->model->getLocation())) {

            throw new Exception\Redirect();
        }

        // determine the output handler
        // get accept content types from the client
        $act = [];
        $input = $this->inputHandler;

        $iCT = $input->getResponseContentType();
        $this->preferredOutputType = $iCT;

        if (empty($iCT) && $input->hasParameter("Accept", "header")) {
            $acp = explode(",", $input->getParameter("Accept", "header"));

            foreach ($acp as $ct) {
                $tmpArray = explode(";", $ct, 2);
                $ct = trim(array_shift($tmpArray));
                if (empty($this->availableOutputTypes) ||
                    in_array($ct, $this->availableOutputTypes)) {
                    $act[] = $ct;
                }
            }
            // TODO sort response types by client preference
        }

        // check the available output formats
        foreach ($act as $contentType) {
            if (array_key_exists($contentType, $this->outputContentTypeMap)) {
                $this->preferredOutputType = $contentType;
                break;
            }
        }

        // if we found no handler we use the default handler
        $outputType;

        if (!empty($this->preferredOutputType))
        {
            $outputType = $this->preferredOutputType;
        }

        if (empty($outputType) &&
            !empty($this->availableOutputTypes))
        {
            $outputType = $this->availableOutputTypes[0];
        }

        if (empty($outputType) ||
            !array_key_exists($outputType, $this->outputContentTypeMap))
        {
            $outputType = "*/*";
        }

        if (array_key_exists($outputType, $this->outputContentTypeMap)) {
            $className = $this->outputContentTypeMap[$contentType];

            if (!class_exists($className, true)) {
                throw new Exception\MissingOutputProcessor();
            }

            $this->outputHandler = new $className();
        }
    }

    /**
     *
     */
    protected function processResponse() {
        if(!$this->outputHandler) {
            $this->outputHandler = new BaseResponder();
        }
        // prepare out put after error handling
        if ($this->responseCode) {
            $this->outputHandler->setStatus($this->responseCode);
        }
        if (!empty($this->error)) {
            $this->outputHandler->addTraceback($this->error);
        }

        // CORS handling
        if (!empty($this->corsHosts) &&
            array_key_exists('HTTP_REFERRER', $_SERVER)) {
            $origin = '';
            $methods = '';

            if (array_key_exists($_SERVER['HTTP_REFERRER'], $this->corsHosts))
            {
                $origin = $_SERVER['HTTP_REFERRER'];
                $methods = $this->corsHosts[$origin];
            }
            elseif (array_key_exists('*', $this->corsHosts))
            {
                $origin = '*';
                $methods = $this->corsHosts[$origin];
            }

            if (!empty($origin)){
                $this->outputHandler->setCORSContext($origin, $methods);
            }
        }

        // generate the output
        $this->outputHandler->process($this->model);
    }

    /**
     *
     */
    protected function getAllowedMethods() {
        return [];
    }

    /**
     *
     */
    protected function handleError() {
        if (!empty($this->error)) {
            switch ($this->error) {
                case "Missing_Content_Parser":
                case "Missing Content Parser":
                    $this->responseCode = 415;
                    break;
                case "Not Implemented":
                    $this->responseCode = 501;
                    break;
                case "Forbidden":
                    $this->responseCode = 403;
                    break;
                case "Authorization Required":
                case "Unauthorized":
                    $this->responseCode = 401;
                    break;
                case "Not Found":
                    $this->responseCode = 404;
                    break;
                case "Not Allowed":
                case "Method Not Allowed":
                    $this->responseCode = 405;
                    // FIXME include Allow header
                    header("Allow: " . join(", ", $this->getAllowedMethods()));
                    break;
                case "Invalid Body Format":
                case "Not Acceptable":
                    $this->responseCode = 406;
                    break;
                case "Continue Request":
                    $this->responseCode = 100;
                    break;
                case "Created":
                    $this->responseCode = 201;
                    break;
                case "Accepted":
                    $this->responseCode = 202;
                    break;
                case "Reset Content":
                    $this->responseCode = 205;
                    break;
                case "Moved Permanently":
                    $this->responseCode = 301;
                    break;
                case "Redirect":
                case "Moved Temporarly":
                    $this->responseCode = 302;
                    header("Location: " . $this->model->getLocation());
                    break;
                case "Not Modified":
                    $this->responseCode = 304;
                    break;
                case "Use Proxy":
                    $this->responseCode = 305;
                    break;
                case "Payment Required":
                    $this->responseCode = 402;
                    break;
                case "Conflict":
                    $this->responseCode = 409;
                    break;
                case "Gone":
                    $this->responseCode = 410;
                    break;
                case "Service Unavailable":
                    $this->responseCode = 503;
                    break;
                case "Too Many Requests":
                    $this->responseCode = 429;
                    break;
                default:
                    $this->responseCode = 400;
                    break;
            }
        }
    }

    /**
 	 * Allows a service to run a worker job after the response handling is
     * completed.
	 */
	protected function postProcess() {
        if ($this->hasModel()) {
            $this->model->runWorkers();
        }
    }
}

?>
