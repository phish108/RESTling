<?php

namespace RESTling;

class OpenAPI extends Service implements Interfaces\OpenApi {

    /** ***********************************************
    * Properties
    */

    private $orderedPaths = [];   ///< helper for request path discovery
    private $pathMap;             ///< helper for operation mapping
    private $activePath;          ///< helper for request handling
    private $activeMethod;        ///< helper for request handling
    private $pathParameters = []; ///< helper to pass path parameters to the inputHandler

    private $config;              ///< keeps the OpenAPI configuration

    /**
    * @protected @method loadTagModel($taglist)
    * @parameter array $taglist
    *
    * The $taglist contains all tag names of the service tags. This is used
    * for determinating a namespaced model. The core function implements
    * naïve namespacing and relies on autoloading.
    *
    * This method will not overwrite an existing model.
    *
    * @throws Exception 'Model already set'
    * @throws Exception 'Not a RESTling\\Model'
    */
    protected function loadTagModel($taglist) {
        // naive namespacing
        $modelName = '\\' . join('\\', $taglist);

        if (class_exists($modelName, true)) {
            $this->setModel(new $modelName(), true);
        }
    }

    /**
    * @protected @method loadTitleModel($modelName)
    * @parameter string $modelName
    *
    * The $modelName contains a camel-cased Classname based on the service
    * title found in the info section of the service specification. If the
    * Classname exists, then this function will use the class as a model.
    *
    * This method will not overwrite an existing model.
    *
    * @throws Exception 'Model already set'
    * @throws Exception 'Not a RESTling\\Model'
    */
    protected function loadTitleModel($modelName) {
        $fqModelName = '\\' . $modelName;
        if (class_exists($fqModelName, true)) {
            $this->setModel(new $fqModelName(), true);
        }
    }

    /** ***********************************************
    * Core Run Methods
    */

    /**
    * @protected @method verifyModel()
    *
    * Overridden version that includes dynamic model detection.
    */
    protected function verifyModel()
    {
        // load tag model
        if (array_key_exists('tags',$this->config) &&
        !empty($this->config['tags'])) {

            $tl = [];
            foreach ($this->config["tags"] as $tag) {
                $tl[] = $tag["name"];
            }
            $this->loadTagModel($tl);
        }
        // load title model (camel cased title as classname)
        if (array_key_exists('title',$this->config['info'])) {
            $acn = explode(" ", $this->config['info']['title']);
            $cn = "";
            foreach ($acn as $part) {
                $cn .= ucfirst(strtolower($part));
            }
            $this->loadTitleModel($cn);
        }

        // verify the model is present
        return parent::verifyModel();
    }

    /**
    * @protected @method findOperation()
    *
    * Overridden version that includes path mapping.
    *
    * If a path is matched for a request, this method will verify that
    * the service specifies a function for the current REQUEST_METHOD.
    * For valid requests, this method checks if the service specifies an
    * operationId for the model. In the case that no operationId is present
    * this method will dynamically assume a operationId based on the
    * REQUEST_METHOD and the matched path.
    * The dynamic operationId will start with a lowercased request method
    * that is followed by a camel-cased path. The casing will be at the
    * path boundaries and at '-', '_', and '.' characters.
    *
    * Example: for GET /user/{userid}/product-info the following operationId
    * will be generated: ```getUserUseridProductInfo```.
    */
    protected function findOperation()
    {
        if (array_key_exists("PATH_INFO", $_SERVER)) {
            $path = $_SERVER['PATH_INFO'];
        }
        if (empty($path)) {
            $path = "/";
        }

        foreach ($this->orderedPaths as $pattern) {
            $matches = [];
            // match path
            if (preg_match($pattern, $path, $matches)) {
                // remove the global match
                array_shift($matches);

                $pathObject       = $this->pathMap[$pattern];
                $this->activePath = $pathObject["pathitem"];

                // extract path parameters
                $len = count($pathObject["vars"]);
                for ($i = 0; $i < $len; $i++) {
                    $this->pathParameters[$pathObject["vars"][$i]] = array_shift($matches);
                }

                if (count($matches)) {
                    $this->path_info = join("/", $matches);
                }

                break;
            }
        }

        if (!$this->activePath) {
            throw new Exception\BadRequest();
        }

        // verify method for path
        $m = strtolower($_SERVER['REQUEST_METHOD']);

        if (!array_key_exists($m, $this->activePath)) {
            throw new Exception\NotAllowed();
        }

        $this->activeMethod = $this->expandObject($this->activePath[$m]);

        // check for an operationId
        if (array_key_exists("operationId", $this->activeMethod)) {
            $this->operation = $this->activeMethod["operationId"];
        }
        else {
            // generate a generic Operation name
            // will generate a operation id as 'getFooBarBaz'
            $op = $m;
            $p = explode("/-_.", $pathObject["path"]);
            foreach ($p as $nPart) {
                // trim path templating
                $nPart = trim($nPart, '\{\}');
                $op .= ucfirst(strtolower($nPart));
            }
            $this->operation = $op;
        }

        // filter the security requirements for the method
        if (array_key_exists("security", $this->activeMethod) &&
            empty($this->activeMethod["security"])) {
            throw new Exception\OpenAPI\MissingSecurityRequirements();
        }

        if (!array_key_exists("components", $this->config)) {
            throw new Exception\OpenAPI\MissingSecurityDefinitions();
        }

        foreach ($this->activeMethod['security'] as $sec => $scopes) {
            // note multiple security requirements may exist
            // NONE of these requirements must reject the authorization and access.
            // different security handers may reject either one.

            if (!array_key_exists($sec, $this->config["components"])) {
                throw new Exception\OpenAPI\MissingSecurityDefinitions();
            }

            if (empty($scopes)) {
                throw new Exception\OpenAPI\MissingSecurityRequirements();
            }

            if (!array_key_exists("type", $this->config["components"][$sec])) {
                throw new Exception\OpenAPI\BadSecurityRequirementsReference();
            }

            $type = $this->config["components"][$sec]["type"];
            if (!$type || !is_string($type) || !in_array($type, ["apiKey", "http", "oauth2", "openIdConnect"])) {
                throw new Exception\OpenAPI\InvalidSecurityDefinitionType();
            }

            $type = "\\RESTling\\Security\\OpenApi\\" . ucfirst($type);

            if (!class_exists($type, true)) {
                throw new Exception\OpenAPI\SecurityHandlerNotFound();
            }

            try {
                $secHandler = new $type();
            }
            catch (Exception $err) {
                throw new Exception\OpenAPI\SecurityHandlerBroken();
            }

            $secHandler->setScheme($this->config["components"][$sec]);
            $secHandler->setScopes($scopes);

            $this->addSecurityHandler($secHandler);
        }

        // filter possible output types
        $this->activeMethod["responses"] = $this->expandObject($this->activeMethod["responses"]);

        foreach ($this->activeMethod["responses"] as $k => $v) {
            if (array_key_exists("content", $v)) {
                foreach ($v["content"] as $ct => $schema) {
                    if (!in_array($ct,$this->availableOutputTypes)) {
                        $this->availableOutputTypes[] = $ct;
                    }
                }
            }
        }
    }

    protected function parseInput()
    {
        parent::parseInput();
        if ($this->inputHandler) {
            $this->inputHandler->setPathParameters($this->pathParameters);
            if (!empty($this->securityHandler)) {
                foreach ($this->securityHandler as $handler) {
                    $handler->setInput($this->inputHandler);
                }
            }
        }
    }

    protected function validateInput() {
        $this->validateParameters();
    }

    private function validateParameters() {
        if (array_key_exists("parameters", $this->activeMethod)) {
            $params = $this->expandObject($this->activeMethod["parameters"]);

            foreach ($params as $param) {
                if (!(array_key_exists("name", $param) || array_key_exists("in", $param))) {
                    throw new Exception\OpenAPI\InvalidParameterObject();
                }
                if (array_key_exists("required", $param) &&
                    !$this->inputHandler->hasParameter($param["name"], $param["in"])) {

                    throw new Exception\OpenAPI\MissingRequiredParameter();
                }

                if (array_key_exists("schema", $param) &&
                    !$this->inputHandler->hasParameterSchema($param["name"], $param["in"], $param["schema"])) {
                    throw new Exception\OpenAPI\InvalidParameterFormat();
                }
            }
        }
    }

    protected function getAllowedMethods()
    {
        if ($this->activePath) {
            $retval = [];
            foreach ($this->activePath as $key => $v) {
                switch ($key) {
                    case "servers":
                    case "summary":
                    case "description":
                    case "parameters":
                        next;
                        break;
                    default:
                        $retval[] = strtoupper($key);
                        break;
                }
            }
            return $retval;
        }
        return parent::getAllowedMethods();
    }

    /** ***********************************************
    * Open API functions
    */

    public function loadConfigFile($fqfn) {
        try {
            $this->_loadConfigFile($fqfn);
        }
        catch (Exception $err) {
            $this->error = $err->getMessage();
        }
    }

    public function loadConfigString($cfgString) {
        try {
            $this->_loadConfigString($cfgString);
        }
        catch (Exception $err) {
            $this->error = $err->getMessage();
        }
    }

    public function loadApiObject($oaiObject) {
        try {
            $this->_loadApiObject($oaiObject);
        }
        catch (Exception $err) {
            $this->error = $err->getMessage();
        }
    }

    private function _loadConfigFile($fqfn) {
        if (empty($fqfn)) {
            throw new Exception\OpenAPI\MissingConfigFile();
        }

        if (!file_exists($fqfn)) {
            throw new Exception\OpenAPI\ConfigFileNotFound();
        }

        try {
            $cfgString = file_get_contents($fqfn);
        }
        catch (Exception $e) {
            throw new Exception\OpenAPI\ConfigFileBroken();
        }

        $this->_loadConfigString($cfgString);
    }

    private function _loadConfigString($cfgStr) {
        if (empty($cfgStr)) {
            throw new Exception\OpenAPI\ConfigEmpty();
        }

        try {
            $o = json_decode($cfgStr, true);
        }
        catch (Exception $e) {
            try {
                $o = yaml_parse($cfgStr);
            }
            catch(Exception $e2) {
                throw new Exception\OpenAPI\ConfigBroken();
            }
        }

        if (empty($o)) {
            throw new Exception\OpenAPI\InvalidConfiguration();
        }

        $this->_loadApiObject($o);
    }


    private function _loadApiObject($oaiObject) {
        if (!is_array($oaiObject)) {
            throw new Exception\OpenAPI\InvalidConfigurationObject();
        }

        if (empty($oaiObject) ||
        !array_key_exists("openapi", $oaiObject) ||
        empty($oaiObject["openapi"])) {
            throw new Exception\OpenAPI\MissingVersion();
        }

        $version = explode(".",  $oaiObject["openapi"]);
        if (count($version) != 3) {
            throw new Exception\OpenAPI\InvalidVersion();
        }

        if ($version[0] < 3) {
            throw new Exception\OpenAPI\VersionUnsupported();
        }

        if (!array_key_exists("info", $oaiObject)){
            throw new Exception\OpenAPI\MissingInfo();
        }

        if (!array_key_exists("paths", $oaiObject) ||
        empty($oaiObject["paths"])) {
            throw new Exception\OpenAPI\MissingPaths();
        }

        $this->config = $oaiObject;
        $this->preprocessPaths($this->config["paths"]);
    }

    /**
    * @private @method preprocessPaths()
    *
    * This method transforms the templated paths for PREG path detection
    */
    private function preprocessPaths($paths) {
        $this->paths = [];

        foreach ($paths as $path => $pathobj) {
            // translate the path into a regex, and filternames
            $apath  = explode("/", $path);
            $rpath  = [];
            $vnames = [];

            $pathobj = $this->expandObject($pathobj);

            if (!empty($pathobj)) {
                foreach ($apath as $pe) {
                    $aVarname = [];
                    if (preg_match("/^\{(.+)\}$/", $pe, $aVarname)) {
                        $vnames[] = $aVarname[1];
                        $rpath[]  = '([^\/]+)';
                    }
                    else {
                        $rpath[] = $pe;
                    }
                }

                $repath = '/^' . implode('\\/', $rpath) . '(?:\\/(.+))?$/';

                $this->pathMap[$repath] = [
                    "pathitem" => $pathobj,
                    "vars" => $vnames,
                    "path" => $path
                ];
            }
        }

        // speed up the matching
        $array = [];
        foreach ($this->pathMap as $pattern => $value) {
            $array[] = $pattern;
        }

        usort($array, function ($a,$b){return strlen($b) - strlen($a);});
        $this->orderedPaths = $array;
    }

    /**
    * @private @method $expandedObject = expandObject($object)
    *
    * This method expands an OAI object if necessary.
    */
    private function expandObject($object) {
        if (array_key_exists('$ref', $object)) {
            return $this->followReference($object['$ref']);
        }
        return $object;
    }

    /**
    * followReference($reference)
    *
    * finds the object that a $ref points to
    */
    private function followReference($reference) {
        if (!array_key_exists($reference, $this->references)) {
            if (preg_match("/^#/", $reference) == 1) {
                $this->followLocalReference($reference);
            }
            else if (preg_match("/^https?:\/\//") == 1) {
                $this->followUriReference($reference);
            }
            else {
                $this->followFileReference($reference);
            }
        }
        return $this->references[$reference];
    }

    /**
    *
    */
    private function followLocalReference($reference) {
        $ref = preg_replace("/^#/", "", $reference);

        $this->references[$reference] = [];

        $o = $this->expandReference($ref, $this->cfg);

        if (!empty($o)) {
            $this->references[$reference] = $o;
        }
    }

    /**
    *
    */
    private function expandReference($ref, $refobj) {
        $refList = explode($ref, "/");
        $refObject = null;

        if (empty($refList[0])) {
            array_shift($refList);
        }

        while ($refobj &&
        !empty($refList) &&
        array_key_exists($refList[0], $refobj)) {

            $refobj = $refobj[$refList[0]];
            array_shift($refList);
        }

        if (empty($refList)) {
            $refObject = $refobj;
        }

        return $refObject;
    }

    /**
    *
    */
    private function followFileReference($reference) {
        $tarr = explode("#", $reference);

        $fn = $this->basedir . DIRECTORY_SEPARATOR . $tarr[0];

        $this->references[$reference] = [];

        if (file_exists($fn) &&
        is_file($fn) &&
        is_readable($fn)) {

            $reffile = file_get_contents($fn);

            try {
                $cfgext = json_decode($reffile, true);
            }
            catch (Exception $e) {
                $cfgext = yaml_parse($reffile);
            }

            if (!empty($cfgext)) {
                // expand subpath
                if (empty($tarr[1])) {
                    $this->references[$reference] = $cfgext;
                }
                else {
                    $o = $this->expandReference($tarr[1], $cfgext);
                    if (!empty($o)) {
                        $this->references[$reference] = $o;
                    }
                }
            }
        }
    }

    /**
    *
    */
    private function followUriReference($reference) {
        // needs to implement caching
        // strip subpath
        // expand subpath
        $this->references[$reference] = [];
    }
}

?>
