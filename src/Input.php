<?php

namespace RESTling;

class Input {
    private $input;
    private $query;
    private $queryString;
    private $cookieParameters = [];
    private $pathParameters   = [];
    private $headerParameters = [];

    private $isMulti = false;

    protected $bodyParameters   = [];

    public function __construct($multi=false) {
        $this->isMulti = $multi;

        if(!$multi) {
            $this->queryString     = $_SERVER["QUERY_STRING"];
            $this->query           = $_GET;
            $this->cookieParameter = $_COOKIE;
            $this->headerParameter = getallheaders();
        }
    }

    public function setPathParameters($paramlist) {
        if (gettype($paramlist) == "array") {
            $this->pathParameters = $paramlist;
        }
    }

    // returns the raw query string
    public function getQueryString() {
        return $this->queryString;
    }

    public function getParameter($pname, $source = "") {
        $sources = ["query", "body", "cookie", "path", "header"];
        if ($this->isMulti) {
            $sources = ["body"];
        }

        if (!empty($source)) {
            if (in_array($source, $sources)) {
                $sources = [$source];
            }
            else {
                return null;
            }
        }

        foreach ($sources as $s) {
            switch ($s) {
                case "query":
                    if (array_key_exists($pname, $this->query)) {
                        return $this->query[$pname];
                    }
                    break;
                case "body":
                    if (array_key_exists($pname, $this->bodyParameters)) {
                        return $this->bodyParameters[$pname];
                    }
                    break;
                case "cookie":
                    if (array_key_exists($pname, $this->cookieParameters)) {
                        return $this->cookieParameters[$pname];
                    }
                    break;
                case "path":
                    if (array_key_exists($pname, $this->pathParameters)) {
                        return $this->pathParameters[$pname];
                    }
                    break;
                case "header":
                    if (array_key_exists($pname, $this->headerParameters)) {
                        return $this->headerParameters[$pname];
                    }
                    break;
                default:
                    break;
            }
        }
        return null;
    }

    public function hasParameter($pname, $source = "") {
        $sources = ["query", "body", "cookie", "path", "header"];

        if (!empty($source) && in_array($source, $sources)) {
            $sources = [$source];
        }

        foreach ($sources as $s) {
            switch ($s) {
                case "query":
                    if (array_key_exists($pname, $this->query)) {
                        return true;
                    }
                    break;
                case "body":
                    if (array_key_exists($pname, $this->bodyParameters)) {
                        return true;
                    }
                    break;
                case "cookie":
                    if (array_key_exists($pname, $this->cookieParameters)) {
                        return true;
                    }
                    break;
                case "path":
                    if (array_key_exists($pname, $this->pathParameters)) {
                        return true;
                    }
                    break;
                case "header":
                    if (array_key_exists($pname, $this->headerParameters)) {
                        return true;
                    }
                    break;
                default:
                    break;
            }
        }
        return false;
    }

    public function parse() {
        if ($_SERVER["METHOD"] == "PUT") {
            $data = trim(file_get_contents("php://input"));
            if (empty($data)) {
                throw new Exception("Empty_Input_Data");
            }

            $p;
            parse_str($data, $p);

            if (empty($p)) {
                throw new Exception("Empty_Input_Data");
            }

            $this->bodyParameters = $p;
        }
        else {
            if (empty($_POST)) {
                throw new Exception("Empty_Input_Data");
            }

            $this->bodyParameters = $_POST;
        }
        return "";
    }
}

?>
