<?php

namespace RESTling;

use \League\JsonGuard\Validator as JSONValidator;

class Input implements Interfaces\Input {
    public  $path_info = "";
    private $input;
    private $queryParameters;
    private $queryString;
    private $cookieParameters = [];
    private $pathParameters   = [];
    private $headerParameters = [];
    private $contentType;

    private $activeUser = [];

    private $isMulti = false;

    protected $bodyParameters   = [];

    protected $outputContentType = "";

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

                    $aQparam[0] = urldecode($aQparam[0]);
                    $aQparam[1] = urldecode($aQparam[1]);

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
            $this->queryParameters  = $oQ;
            $this->cookieParameters = $_COOKIE;
            $this->headerParameters = getallheaders();
        }
    }

    public function setQueryParameter($queryParameter) {
        $this->queryParameters = $queryParameter;
    }

    public function setHeaderParameter($headers) {
        foreach ($headers as $h => $c) {
            $this->headerParameters[$h] = $c;
        }
    }

    public function setPathParameter($paramlist) {
        if (gettype($paramlist) == "array") {
            $this->pathParameters = $paramlist;
        }
    }

    public function setResponseType($contentType) {
        $this->setOutputContentType($contentType);
    }

    public function setResponseContentType($contentType) {
        if (!empty($contentType)) {
            $this->outputContentType = $contentType;
        }
    }

    public function getResponseContentType() {
        return $this->outputContentType;
    }

    // returns the raw query string
    public function getQueryString() {
        return $this->queryString;
    }

    final public function get($pname, $source = "") {
            return $this->getParameter($pname, $source);
    }

    public function getParameter($pname, $source = "") {
        $sources = ["query", "body", "cookie", "path", "header"];
        if ($this->isMulti) {
            $sources = ["body"];
        }
        if ($source == "formData") {
            $source = "body";
        }

        if (!empty($source)) {
            if (in_array($source, $sources)) {
                $sources = [$source];
            }
            else {
                return null;
            }
        }

        if (empty($pname)) {
            return null;
        }

        $retval = null;
        if (is_array($pname)) {
            $retval = [];
        }

        foreach ($sources as $s) {
            $sname = $s . 'Parameters';
            $arr = $this->$sname;
            if (is_array($pname)) {
                foreach ($pname as $name) {
                    if (!empty($name) && array_key_exists($name, $arr)) {
                        $retval[$name] = $arr[$name];
                    }
                }
                return $retval;
            }
            elseif (array_key_exists($pname, $arr)) {
                return $arr[$pname];
            }
        }

        return $retval;
    }

    final public function has($pname, $source = "") {
            return $this->hasParameter($pname, $source);
    }

    public function hasParameter($pname, $source = "") {
        $sources = ["query", "body", "cookie", "path", "header"];

        if ($source == "formData") {
            $source = "body";
        }

        if (!empty($source) && in_array($source, $sources)) {
            $sources = [$source];
        }

        if (empty($pname)) {
            return false;
        }

        foreach ($sources as $s) {
            $sname = $s . 'Parameters';
            if (array_key_exists($pname, $this->$sname)) {
                return true;
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

    public function parse($data="") {
        if (!empty($data)) {
            $p;
            parse_str($data, $p);

            if (empty($p)) {
                throw new Exception\EmptyInputData();
            }

            $this->bodyParameters = $p;
        }
        elseif ($_SERVER["METHOD"] == "PUT") {
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

    final public function getUser() {
        return $this->activeUser;
    }

    final public function hasUser() {
        if (!empty($this->activeUser)) {
            return true;
        }
        return false;
    }

    final public function getBody() {
        return $this->bodyParameters;
    }
}

?>
