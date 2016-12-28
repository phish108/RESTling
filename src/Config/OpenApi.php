<?php
namespace RESTling\Config;

class OpenApi {
    private $config = [];
    private $references = [];

    public function __construct() {
    }

    public function getInfo() {
        if (array_key_exists('info',$this->config) &&
            !empty($this->config['info'])) {
            return $this->config['info'];
        }
        return null;
    }

    public function getTags() {
        if (array_key_exists('tags',$this->config) &&
            !empty($this->config['tags'])) {
            return $this->config['tags'];
        }
        return null;
    }

    public function getPaths() {
        if ($this->config && array_key_exists("paths", $this->config)) {
            return $this->config["paths"];
        }
        return null;
    }

    public function getComponent($componentName) {
        $comp = $this->getAllComponents();
        if (!array_key_exists($componentName, $comp)) {
            throw new \RESTling\Exception\OpenAPI\ComponentNotFound();
        }
        if (empty($comp[$componentName])) {
            throw new \RESTling\Exception\OpenAPI\MissingComponentContent();
        }

        return $comp[$componentName];
    }

    public function loadConfigFile($fqfn) {
        if (empty($fqfn)) {
            throw new \RESTling\Exception\OpenAPI\MissingConfigFile();
        }

        if (!file_exists($fqfn)) {
            throw new \RESTling\Exception\OpenAPI\ConfigFileNotFound();
        }

        try {
            $cfgString = file_get_contents($fqfn);
        }
        catch (Exception $e) {
            throw new \RESTling\Exception\OpenAPI\ConfigFileBroken();
        }

        $this->loadConfigString($cfgString);
    }

    public function loadConfigString($cfgStr) {
        if (empty($cfgStr)) {
            throw new \RESTling\Exception\OpenAPI\ConfigEmpty();
        }

        try {
            $o = json_decode($cfgStr, true);
        }
        catch (Exception $e) {
            try {
                $o = yaml_parse($cfgStr);
            }
            catch(Exception $e2) {
                throw new \RESTling\Exception\OpenAPI\ConfigBroken();
            }
        }

        if (empty($o)) {
            throw new \RESTling\Exception\OpenAPI\InvalidConfiguration();
        }

        $this->loadApiObject($o);
    }


    public function loadApiObject($oaiObject) {
        if (!is_array($oaiObject)) {
            throw new \RESTling\Exception\OpenAPI\InvalidConfigurationObject();
        }

        if (empty($oaiObject) ||
        !array_key_exists("openapi", $oaiObject) ||
        empty($oaiObject["openapi"])) {
            throw new \RESTling\Exception\OpenAPI\MissingVersion();
        }

        $version = explode(".",  $oaiObject["openapi"]);
        if (count($version) != 3) {
            throw new \RESTling\Exception\OpenAPI\InvalidVersion();
        }

        if ($version[0] < 3) {
            throw new \RESTling\Exception\OpenAPI\VersionUnsupported();
        }

        if (!array_key_exists("info", $oaiObject)){
            throw new \RESTling\Exception\OpenAPI\MissingInfo();
        }

        if (!array_key_exists("paths", $oaiObject) ||
        empty($oaiObject["paths"])) {
            throw new \RESTling\Exception\OpenAPI\MissingPaths();
        }

        $this->config = $oaiObject;
    }

    /**
     * @private @method $expandedObject = expandObject($object)
     *
     * This method expands an OAI object if necessary.
     */
    public function expandObject($object) {
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
    public function followReference($reference) {
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

        if (!(file_exists($fn) &&
            is_file($fn) &&
            is_readable($fn))) {
            throw new \RESTling\Exception\OpenAPI\FileReferenceNotFound();
        }

        $reffile = file_get_contents($fn);

        try {
            $cfgext = json_decode($reffile, true);
        }
        catch (Exception $e) {
            $cfgext = yaml_parse($reffile);
        }

        if (empty($cfgext)) {
            throw new \RESTling\Exception\OpenAPI\FileReferenceFailed();
        }

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
