<?php

namespace RESTling;

use RESTling\Input\Base  as BaseHandler;
use RESTling\Output\Base as BaseResponder;

class Service
{
    private $model;
    protected $inputHandler;
    private   $outputHandler;
    private   $securityHandler = [];

    private $responseCode = 200;

    private $inputContentTypeMap = [
        "application/json"                   => "RESTling\\Input\\JSON",
        "application\/x-www-form-urlencoded" => "RESTling\\Input\\Base",
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
        "text/plain"                         => "RESTling\\Output\\Base",
        "*/*"                                => "RESTling\\Output\\Base"
                              ];

    protected $error;
    protected $operation;

    private $corsHosts = [];

    protected $preferredOutputType;
    protected $availableOutputTypes = [];
    protected $path_info = "";

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
            throw new Exception("Model already set");
        }
        if (!($m && $m instanceof \RESTling\Model)) {
            throw new Exception("Not a RESTling\\Model");
        }
        $this->model = $m;
    }

    /**
     * @final @protected @function bool hasModel()
     * returns bool
     *
     * This function allows to check if a model is present. Returns true if a
     * model is set.
     */
    final protected function hasModel() {
        return ($this->model && $this->model instanceof \RESTling\Model);
    }

    /**
     * @final @protected @function bool noModel()
     *
     * Inverse of hasModel().
     */
     final protected function noModel() {
         return (!$this->hasModel());
     }

    /**
     * @final @public @method setSecurityHandler($handler)
     * @parameter \RESTling\Validator $handler
     *
     * Sets the security validator.
     *
     * @throws Exception "Not a RESTling\Validator\\Security"
     */
    final public function addSecurityHandler($h) {
        if (!($h && $h instanceof \RESTling\Validator\Security)) {
            throw new Exception("Not a RESTling\\Validator\\Security");
        }
        $this->securityHandler[] = $h;
    }

    /**
     * @final @public @method addCORSHost($host, $methods)
     * @parameter mixed $host
     * @parameter mixed $methods
     *
     * Interface to Cross Origin Resource Sharing (CORS) handling.
     *
     * This function allows to specify, which referring sites can access this
     * service.
     *
     * Both parameters can be either strings or arrays.
     *
     * @throws Exception 'Invalid CORS Parameter', if parameters are of invalid type.
     */
    final public function addCORSHost($host, $aMethods) {
        if (!((is_string($host) || is_array($host)) &&
              (is_string($aMethods) || is_array($aMethods)))) {
            throw new Exception("Invalid CORS Parameter");
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
    final public function run() {

        $fLoop = [
            "verifyModel",
            "findOperation",
            "parseInput",
            "verifyAuthorization",
            "validateInput",
            "validateParameter",
            "verifyAccess",
            "performOperation",
            "prepareOutputProcessor"
        ];

        if (empty($this->error)) {
            foreach ($fLoop as $func) {
                try {
                    call_user_func(array($this, $func));
                }
                catch (Exception $err) {
                    $this->error = $err->getMessage();
                    break;
                }
            }
        }

        $this->handleError();
        $this->processResponse();
    }

    /**
     * @protected @function
     */
    protected function verifyModel() {
        if ($this->noModel()) {
            throw new Exception("No_Model");
        }
    }

    /**
     *
     */
    protected function findOperation() {
        $this->operation = strtolower($_SERVER["REQUEST_METHOD"]);
    }

    /**
     *
     */
    protected function verifyAuthorization() {
        if (!empty($this->securityHandler)) {
            $validation = false;

            foreach ($this->securityHandler as $handler) {
                if ($handler->willVaidate()) {
                    $validation = true;
                    $handler->validate();
                }
            }

            if (!$validation) {
                throw new Exception("Authorization Required");
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

            if (array_key_exists($contentType, $this->inputContentTypeMap)) {
                $className = $this->inputContentTypeMap[$contentType];

                if (!class_exists($className, true)) {
                    throw new Exception("Missing_Content_Parser");
                }

                $this->inputHandler = new $className();
                $this->error = $this->inputHandler->parse();
            }
            else {
                $this->inputHandler = new BaseHandler();
            }
        }
        else {
            $this->inputHandler = new BaseHandler();
        }
    }

    /**
     *
     */
    protected function validateInput()     {
    }

    /**
     *
     */
    protected function validateParameter() {
    }

    /**
     *
     */
    protected function verifyAccess() {
        if ($this->securityHandler &&
            !$this->securityHandler->verifyAccess()) {
            throw new Exception("Forbidden");
        }
    }

    /**
     *
     */
    private function performOperation() {
        if ($this->noModel() ||
            !method_exists($this->model, $this->operation)) {
            throw new Exception("Not Implemented");
        }

        $this->model->setInput($this->inputHandler);

        call_user_func(array($this->model,
                             $this->operation));
    }

    /**
     *
     */
    protected function prepareOutputProcessor() {
        // determine the output handler
        // get accept content types from the client
        $h = getallheaders();
        if (array_key_exists("Accept", $h)) {
            $acp = explode(",", $h["Accept"]);
            $act = [];

            foreach ($acp as $ct) {
                $tmpArray = explode(";", $ct, 2);
                $ct = trim(array_shift($tmpArray));
                if (empty($this->availableOutputTypes) ||
                    in_array($ct, $this->availableOutputTypes)) {
                    $act[] = $ct;
                }
            }
        }

        // TODO sort response types by client preference

        // check the available output formats
        foreach ($act as $contentType) {
            if (array_key_exists($contentType, $this->outputContentTypeMap)) {
                $className = $this->outputContentTypeMap[$contentType];

                if (!class_exists($className, true)) {
                    throw new Exception("Missing_Output_Processor");
                }

                $this->outputHandler = new $className();
                break;
            }
        }

        if (!$this->outputHandler) {
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
                    throw new Exception("Missing_Output_Processor");
                }

                $this->outputHandler = new $className();
            }
        }
    }

    /**
     *
     */
    protected function processResponse() {
        // prepare out put after error handling
        if (!empty($this->error)) {
            $this->outputHandler->setErrorMessage($this->error);
            if ($this->model) {
                $this->outputHandler->setTraceback($this->model->getAllErrors());
                if ($this->model->hasData()) {
                    $this->outputHandler->addTraceback("data", $this->model->getData());
                }
            }
        }

        // generate headers

        // TODO Partial Content
        // TODO Mutlipart Content
        $this->outputHandler->getStatusCode($this->responseCode);
        $this->outputHandler->getContentType();

        // CORS handling
        if (!empty($this->corsHosts) &&
            array_key_exists('HTTP_REFERRER', $_SERVER)) {
            $origin = '';
            $methods = '';

            if (array_key_exists($_SERVER['HTTP_REFERRER'], $this->corsHosts))
            {
                $origin = $_SERVER['HTTP_REFERRER'];
                $methods = join(', ', $this->corsHosts[$origin]);
            }
            elseif (array_key_exists('*', $this->corsHosts))
            {
                $origin = '*';
                $methods = join(', ', $this->corsHosts[$origin]);
            }

            if (!empty($origin)){
                header('Access-Control-Allow-Origin: ' . $origin);
                header('Access-Control-Allow-Methods: ' . $methods);
            }
        }

        // get additional model headers
        if ($this->hasModel() &&
            !empty($headers = $this->model->getHeaders())) {
            foreach ($headers as $headername => $headervalue) {
                header($headername . ": " . $headervalue);
            }
        }

        if ($this->hasModel() &&
            $this->model->hasData()) {
            // stream the output
            while ($this->model->hasData()) {
                $this->outputHandler->send($this->model->getData());
            }
        }

        // wrap up the response (or send error messages)
        $this->outputHandler->finish();
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
        if(!$this->outputHandler) {
            $this->outputHandler = new BaseResponder();
        }

        if (!empty($this->error)) {
            switch ($this->error) {
                case "Missing_Content_Parser":
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
                case "Continue":
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
                case "Moved Temporarly":
                    $this->responseCode = 302;
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
        elseif ($this->hasModel() && !$this->model->hasData()) {
            $this->responseCode = 204;
        }
        else {
            $this->responseCode = 200;
        }
    }
}

?>
