<?php

namespace RESTling;

class OpenAPI extends Service implements Interfaces\OpenApi {

    /** ***********************************************
    * Properties
    */
    private $alternate = "";

    private $pathMap;             ///< helper for operation mapping
    private $activePath;          ///< helper for request handling
    private $activeMethod;        ///< helper for request handling
    private $pathParameters = []; ///< helper to pass path parameters to the inputHandler

    protected $apiConfig;              ///< keeps the OpenAPI configuration

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
        if ($this->noModel() && !empty($taglist)) {

            $modelName = '\\' . join('\\', $taglist);

            if (class_exists($modelName, true)) {
                $this->setModel(new $modelName(), true);
            }
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
        if ($this->noModel() && !empty($modelName)) {
            $fqModelName = '\\' . $modelName;
            if (class_exists($fqModelName, true)) {
                $this->setModel(new $fqModelName(), true);
            }
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
        $tags = $this->apiConfig->getTagModel();
        $info = $this->apiConfig->getInfo();

        $this->loadTagModel($tags);

        // load title model (camel cased title as classname)
        if ($info && array_key_exists('title',$info)) {
            $acn = explode(" ", $info['title']);
            $cn = "";
            foreach ($acn as $part) {
                $cn .= ucfirst(strtolower($part));
            }
            $this->loadTitleModel($cn);
        }

        $this->preprocessPaths();
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

        $this->activePath = null;
        for ($i = 0; $i < count($this->pathMap) && !$this->activePath; $i++ ) {
            $pathObject = $this->pathMap[$i];
            $matches = [];
            // match path
            if (preg_match($pathObject["pattern"], $path, $matches)) {
                // remove the global match
                array_shift($matches);

                $this->activePath = $pathObject["pathitem"];

                // extract path parameters
                $len = count($pathObject["vars"]);
                for ($i = 0; $i < $len; $i++) {
                    $this->pathParameters[$pathObject["vars"][$i]] = array_shift($matches);
                }

                if (count($matches)) {
                    $this->path_info = join("/", $matches);
                }
            }
        }

        if (!$this->activePath) {
            throw new Exception\BadRequest();
        }

        // verify method for path
        $m = strtolower($_SERVER['REQUEST_METHOD']);

        if ($m === "post") {
            if (array_key_exists("method", $_GET)) {
                $m = strtolower($_GET['method']);
                $this->alternate = $m;
            }
        }

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
        if (!empty($this->activeMethod["security"]) &&
            array_key_exists("security", $this->activeMethod)) {
            throw new Exception\OpenAPI\MissingSecurityRequirements();

            foreach ($this->activeMethod['security'] as $sec => $scopes) {
                // note multiple security requirements may exist
                // NONE of these requirements must reject the authorization and access.
                // different security handers may reject either one.
                $securityDefinition = $this->apiConfig->getComponent($sec);

                if (empty($scopes)) {
                    throw new Exception\OpenAPI\MissingSecurityRequirements();
                }

                if (!array_key_exists("type", $securityDefinition)) {
                    throw new Exception\OpenAPI\BadSecurityRequirementsReference();
                }

                $type = $securityDefinition["type"];
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

                $secHandler->setScheme($securityDefinition);
                $secHandler->setScopes($scopes);

                $this->addSecurityHandler($secHandler);
            }
        }

        // filter possible output types
        $v = $this->apiConfig->getApiVersion();
        if ((substr($v, 0, 3 ) === "3.0") &&
            array_key_exists("responses", $this->activeMethod)) {

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
        elseif ($v == "2.0" && array_key_exists("produces", $this->activeMethod)) {
            $this->preferredOutputType = $this->activeMethod["produces"][0];
            foreach ($this->activeMethod["produces"] as $ct) {
                $this->availableOutputTypes[] = $ct;
            }
        }
    }

    protected function parseInput()
    {
        // restrict the content types if the API requests it
        $v = $this->apiConfig->getApiVersion();

        if (substr($v, 0, 3 ) === "3.0") {
            if (array_key_exists("requestBody", $this->activeMethod) &&
                array_key_exists("content", $this->activeMethod["requestBody"]) &&
                !empty($this->activeMethod["requestBody"]["content"])) {

                foreach (array_keys($this->activeMethod["requestBody"]["content"]) as $ct) {
                    $this->allowedContentTypes[] = $ct;
                }
            }
        }
        elseif ($v == "2.0") {
            if (array_key_exists("consumes", $this->activeMethod) &&
                !empty($this->activeMethod["consumes"])) {
                    foreach ($this->activeMethod["consumes"] as $ct) {
                        $this->allowedContentTypes[] = $ct;
                    }
            }
        }

        if (empty($this->alternate)) {
            parent::parseInput();
        }
        else {
            $this->alternateParseInput();
        }
        $this->inputHandler->setPathParameter($this->pathParameters);
    }

    protected function alternateParseInput() {
        $method = $this->alternate;

        if ($method === "put" ||
            $method === "post") {

            $ctHead = explode(";", $_POST['Content-Type'], 2);
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
                $this->inputHandler->parse(urldecode($_POST["content"]));
                $this->inputHandler->setContentType($contentType);
                $this->inputHandler->path_info = $this->path_info;
            }
        }

        if (!$this->inputHandler) {
            $this->inputHandler = new BaseParser();
        }

        $allowedHeaders = [
            "Authorization",
            "Content-Type",
            "X-Experience-API-Version",
            "Content-Length",
            "If-Match",
            "If-Non-Match"
        ];
        $forbiddenParam = [
            "method",
            "content"
        ];
        // populate the query parameters
        $qParams = [];
        foreach ($_POST as $param => $value) {
            if (in_array($param, $forbiddenParam) ||
                in_array($param, $allowedHeaders)) {
                next;
            }
            $qParam[$h] = $value;
        }
        if (!empty($qParams)) {
            $this->inputHandler->setHeaderParameter($qParams);
        }
        // populate the header parameters
        $headers = [];
        foreach ($allowedHeaders as $h) {
            if (array_key_exists($h, $_POST)) {
                $headers[$h] = $_POST[$h];
            }
        }
        if (!empty($headers)) {
            $this->inputHandler->setHeaderParameter($headers);
        }
    }

    protected function validateInput() {
        $this->validateParameters();

        $ct = $this->inputHandler->getContentType();

        if (array_key_exists("requestBody", $this->activeMethod) &&
            array_key_exists("content", $this->activeMethod["requestBody"]) &&
            array_key_exists($ct, $this->activeMethod["requestBody"]["content"]) &&
            array_key_exists("schema", $this->activeMethod["requestBody"]["content"][$ct])) {

            $schema = $this->apiConfig->expandObject($this->activeMethod["requestBody"]["content"][$ct]["schema"]);
            $this->inputHandler->verifyBodySchema($schema);
        }
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

                if ($this->apiConfig->getVersion() == "2.0" && array_key_exists("type", $param)) {
                    // the official swagger editor does not allow the schema property
                    $schema = ["type" => $param["type"]];
                    if (!$this->inputHandler->hasParameterSchema($param["name"], $param["in"], $schema)) {
                        throw new Exception\OpenAPI\InvalidParameterFormat();
                    }
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
        $this->apiConfig = new Config\OpenApi();
        try {
            $this->apiConfig->loadConfigFile($fqfn);
        }
        catch (Exception $err) {
            $this->error = $err->getMessage();
        }
    }

    public function loadConfigString($cfgString) {
        $this->apiConfig = new Config\OpenApi();
        try {
            $this->apiConfig->loadConfigString($cfgString);
        }
        catch (Exception $err) {
            $this->error = $err->getMessage();
        }
    }

    public function loadConfigObject($cfgObject) {
        $this->apiConfig = new Config\OpenApi();
        try {
            $this->apiConfig->loadApiObject($cfgObject);
        }
        catch (Exception $err) {
            $this->error = $err->getMessage();
        }
    }

    public function setApiConfig($oaiConfig) {
        if (!$oaiConfig || !($oaiConfig instanceof \RESTling\Config\OpenApi)) {
            throw new \RESTling\Exception\MissingConfiguration();
        }

        $this->apiConfig = $oaiConfig;
    }

    /**
    * @private @method preprocessPaths()
    *
    * This method transforms the templated paths for PREG path detection
    */
    private function preprocessPaths() {
        $this->pathMap = [];
        $model = $this->getModel();
        if ($model && method_exists($model, "getPathMap")) {
            $this->pathMap = $model->getPathMap();
            return;
        }

        $paths = $this->apiConfig->getPaths();
        $orderMap = [];

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

                $orderMap[] = [
                    "pattern" => $repath,
                    "pathitem" => $pathobj,
                    "vars" => $vnames,
                    "path" => $path
                ];
            }
        }

        usort($orderMap, function ($a,$b){return strlen($b["pattern"]) - strlen($a["pattern"]);});
        $this->pathMap = $orderMap;
    }

    /**
    * @private @method $expandedObject = expandObject($object)
    *
    * This method expands an OAI object if necessary.
    */
    private function expandObject($object) {
        return $this->apiConfig->expandObject($object);
    }
}

?>
