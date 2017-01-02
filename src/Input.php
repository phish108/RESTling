<?php

namespace RESTling;

use \League\JsonGuard\Validator as JSONValidator;

class Input implements Interfaces\Input {
    private $input;
    private $query;
    private $queryString;
    private $cookieParameters = [];
    private $pathParameters   = [];
    private $headerParameters = [];
    private $contentType;

    private $activeUser = [];

    private $isMulti = false;

    protected $bodyParameters   = [];

    public function __construct($multi=false) {
        $this->isMulti = $multi;

        if(!$multi) {
            $this->queryString     = $_SERVER["QUERY_STRING"];
            // process the query string because $_GET is faulty for multiple
            // query parameters occurences
            $aQ = explode("&", $this->queryString);
            $oQ = [];
            // don't process if there is no = for a single parameters
            if (count($aQ) > 1 || (count($aQ) == 1 && strstr($aQ[0], "=") !== false)) {
                foreach ($aQ as $qparam) {
                    $aQparam = explode("=", $qparam, 2);
                    if (count($aQparam) == 1) {
                        // this case may happen for multi parameters where one
                        // does not contain an '=' delimitor
                        $aQparam[] = "";
                    }
                    if (array_key_exists($aQparam[0], $oQ)) {
                        if (!is_array($oQ[$aQparam[0]])) {
                            // arrayfy if not an array already
                            $oQ[$aQparam[0]] = [$oQ[$aQparam[0]]];
                        }
                        $oQ[$aQparam[0]][] = $aQparam[1];
                    }
                    else {
                        $oQ[$aQparam[0]] = $aQparam[1];
                    }
                }
            }
            $this->query           = $oQ;
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

    public function hasParameterSchema($pname, $source, $schema) {
        $sources = ["query", "body", "cookie", "path", "header"];

        if (!empty($source) && in_array($source, $sources)) {
            $sources = [$source];
        }

        if ($this->hasParameter($pname, $sources)) {
            $data = $this->getParameter($pname, $sources);

            // some parameters may occur more than once in the query
            // only if explicity query parameters are tested
            if ($source === "query" && is_array($data)) {
                foreach ($data as $dataInstance) {
                    $validator = new JSONValidator($dataInstance, $schema);

                    if ($validator->fails()) {
                        return false;
                    }
                }
            }
            else {
                $validator = new JSONValidator($data, $schema);

                if ($validator->fails()) {
                    return false;
                }
            }
        }
        return true;
    }

    public function verifyBodySchema($schema) {
        if (empty($this->bodyParameters)) {
            throw new Exception\InvalidInputFormat();
        }
        if (!empty($schema)) {
            $data = $this->bodyParameters;
            $validator = new JSONValidator($data, $schema);
            if ($validator->fails()) {
                throw new Exception\InvalidInputFormat();
            }
        }
    }

    public function parse() {
        if ($_SERVER["METHOD"] == "PUT") {
            $data = trim(file_get_contents("php://input"));
            if (empty($data)) {
                throw new Exception\EmptyInputData();
            }

            $p;
            parse_str($data, $p);

            if (empty($p)) {
                throw new Exception\EmptyInputData();
            }

            $this->bodyParameters = $p;
        }
        elseif ($_SERVER["METHOD"] == "POST"){
            if (empty($_POST)) {
                throw new Exception\EmptyInputData();
            }

            $this->bodyParameters = $_POST;
        }
        return "";
    }

    final public function setContentType($ct) {
        $this->contentType = $ct;
    }

    final public function getContentType() {
        return $this->contentType;
    }

    final public function addActiveUser($userid) {
        if ($userid && !in_array($userid, $this->activeUser)) {
            $this->activeUser[] = $userid;
        }
    }

    final public function hasActiveUser($userid) {
        if (in_array($userid, $this->activeUser)) {
            return true;
        }
        return false;
    }

    final public function hasUser() {
        if (!empty($this->activeUser)) {
            return true;
        }
        return false;
    }
}

?>
