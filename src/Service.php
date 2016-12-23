<?php

namespace RESTling;

use RESTling\Input\Base  as BaseHandler;
use RESTling\Output\Base as BaseResponder;

class Service
{
    private   $model;
    protected $inputHandler;
    private   $outputHandler;
    private   $securityHandler;

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

    public function __construct() {}

    final public function setModel($m) {
        $this->model = $m;
    }

    final public function setSecurityHandler($h) {
        $this->securityHandler = $h;
    }

    final public function addCORSHost($host, $aMethods) {
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

    final public function addInputContentTypeMap($contentType, $handlerType) {
        if (gettype($contentType) == "string" &&
            gettype($handlerType) == "string")
        {
            $this->inputContentTypeMap[$contentType] = $handlerType;
        }
    }

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
            "hasModel",
            "findOperation",
            "verifyAuthorization",
            "validateMethod",
            "parseInput",
            "validateInput",
            "validateParameter",
            "verifyAccess",
            "performOperation",
            "prepareOutputProcessor"
        ];

        foreach ($fLoop as $func) {
            call_user_func(array($this, $func));

            if (!empty($this->error)) {
                break;
            }
        }

        $this->handleError();
        $this->processResponse();
    }

    protected function hasModel() {
        if (!$this->model) {
            $this->error = "No_Model";
        }
    }

    protected function findOperation() {
        $this->operation = strtolower($_SERVER["REQUEST_METHOD"]);
    }

    protected function verifyAuthorization() {
        if ($this->securityHandler &&
            !$this->securityHandler->verifyAuthorization()) {
            $this->error = "Authorization Required";
        }
    }

    private function parseInput() {
        $method = $_SERVER["REQUEST_METHOD"];

        if ($method === "PUT" ||
            $method === "POST") {

            $ctHead = explode(";", $_SERVER['CONTENT_TYPE'], 2);
            $contentType = array_shift($ctHead);

            if (array_key_exists($contentType, $this->inputContentTypeMap)) {
                $className = $this->inputContentTypeMap[$contentType];

                if (!class_exists($className, true)) {
                    $this->error = "Missing_Content_Parser";
                    return;
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

    protected function validateMethod()    {}
    protected function validateInput()     {}
    protected function validateParameter() {}

    protected function verifyAccess() {
        if ($this->securityHandler &&
            !$this->securityHandler->verifyAccess()) {
            $this->error = "Forbidden";
        }
    }

    private function performOperation() {
        if (!$this->model &&
            !method_exists($this->model, $this->operation))
        {
            $this->error = "Not Implemented";
            return;
        }

        $this->model->setInput($this->inputHandler);

        try {
            $this->error = call_user_func(array($this->model,
                                                $this->operation));
        }
        catch (Exception $err) {
            $this->error = $err->getMessage();
        }
    }

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
                    $this->error = "Missing_Output_Processor";
                    return;
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
                    $this->error = "Missing_Output_Processor";
                    return;
                }

                $this->outputHandler = new $className();
            }
        }
    }

    protected function processResponse() {
        // prepare out put after error handling
        if (!empty($this->error)) {
            $this->outputHandler->setErrorMessage($this->error);
            $this->outputHandler->setTraceback($this->model->getAllErrors());
            if ($this->model->hasData()) {
                $this->outputHandler->addTraceback("data", $this->model->getData());
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
        if (!empty($headers = $this->model->getHeaders())) {
            foreach ($headers as $headername => $headervalue) {
                header($headername . ": " . $headervalue);
            }
        }

        if ($this->model->hasData()) {
            // stream the output
            while ($this->model->hasData()) {
                $this->outputHandler->send($this->model->getData());
            }
        }

        // wrap up the response (or send error messages)
        $this->outputHandler->finish();
    }

    protected function getAllowedMethods() {
        return [];
    }

    private function handleError() {
        if(!$this->outputHandler) {
            $this->outputHandler = new BaseResponder();
        }

        if (!empty($this->error)) {
            switch ($this->error) {
                case "No_Model":
                case "Missing_Output_Processor":
                case "Invalid Body Parameter":
                case "Invalid Path Parameter":
                case "Invalid Query Parameter":
                case "Invalid Header Parameter":
                case "Invalid Cookie Parameter":
                case "Bad Request":
                    $this->responseCode = 400;
                    break;
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
        elseif (!$this->model->hasData()) {
            $this->responseCode = 204;
        }
        else {
            $this->responseCode = 200;
        }
    }
}

?>
