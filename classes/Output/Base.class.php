<?php

namespace RESTling\Output;

class Base {
    protected $contentType = "text/plain";

    protected $traceback;
    protected $statusCode;
    protected $errorMessage;

    public function __construct() {}

    public function getContentType() {
        header('Content-Type: ' . strtolower($this->contentType));

        header('X-UA-Compatible: IE=edge'); // force IE to obey!
        header('Cache-Control: no-cache');  // forbid caching
    }

    public function getStatusCode() {
        http_response_code($this->statusCode);
    }

    public function setStatusCode($code) {
        $this->statusCode = $code;
    }

    public function setContentType($ct) {
        $this->contentType = $ct;
    }

    public function setErrorMessage($message) {
        $this->errorMessage = $message;
    }

    public function setTraceback($traceback) {
        $this->traceback = $traceback;
    }

    public function send($data) {
        if (gettype(data) == "string") {
            echo($data);
        }
    }

    public function finish() {
        // TODO implement the error message formatting
    }

}

?>