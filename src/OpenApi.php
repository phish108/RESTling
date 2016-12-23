<?php

namespace RESTling;

class OpenAPI extends Service {

    /** ***********************************************
     *
     */

    private $orderedPaths = [];
    private $pathMap;
    private $activePath;
    private $activeMethod;
    private $pathParameters = [];

    private $config;

    protected function loadTagModel($taglist) {
        if ($this->model) {
            return;
        }
        
        // naive namespacing
        $modelName = '\\' . join('\\', $taglist);

        if (class_exists($modelName, true)) {
            $this->model = new $modelName();
        }
    }

    protected function loadTitleModel($modelName) {
        if ($this->model) {
            return;
        }

        $fqModelName = '\\' . $modelName;
        if (class_exists($fqModelName, true)) {
            $this->model = new $fqModelName();
        }
    }

    /** ***********************************************
     * Core Run Methods
     */
    protected function hasModel()
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
        parent::hasModel();
    }

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
            $this->error = "Bad Request";
            return;
        }

        // verify method for path
        $m = strtolower($_SERVER['REQUEST_METHOD']);

        if (!array_key_exists($m, $this->activePath)) {
            $this->error = "Not Allowed";
            return;
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
            $p = explode("/", $pathObject["path"]);
            foreach ($p as $nPart) {
                // trim path templating
                $nPart = trim($nPart, '\{\}');
                $op .= ucfirst(strtolower($nPart));
            }
            $this->operation = $op;
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

    protected function verifyAuthorization()
    {}

    protected function parseInput()
    {
        parent::parseInput();
        if ($this->inputHandler) {
            $this->inputHandler->setPathParameters($this->pathParameters);
        }
    }

    protected function verifyAccess()
    {}

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
         if (empty($fqfn)) {
             $this->error = "OpenAPI Config File Missing";
             return;
         }

         if (!file_exists($fqfn)) {
             $this->error = "OpenAPI Config File Not Found";
             return;
         }

         try {
             $cfgString = file_get_contents($fqfn);
         }
         catch (Exception $e) {
             $this->error = "OpenAPI Config File Broken";
             return;
         }

         $this->loadConfigString($cfgString);
     }

     public function loadConfigString($cfgStr) {
         if (empty($cfgStr)) {
             $this->error = "OpenAPI Configuration Empty";
             return;
         }

         try {
             $o = json_decode($cfgStr, true);
         }
         catch (Exception $e) {
             try {
                 $o = yaml_parse($cfgStr);
             }
             catch(Exception $e2) {
                 $this->error = "OpenAPI Configuration Broken";
             }
         }

        if (empty($o)) {
            $this->error = "OpenAPI Configuration Invalid";
            return;
        }

        $this->loadApiObject($o);
     }

     /**
      * @private @method loadApiObject($oaiObj)
      */
      private function loadApiObject($oaiObject) {
          try {
              $this->_loadApiObject($oaiObject);
          }
          catch (Exception $err) {
              $this->error = $err->getMessage();
          }
      }

      private function _loadApiObject($oaiObject) {
          if (!is_array($oaiObject)) {
              throw new Exception("Invalid Configuration Object");
          }

          if (empty($oaiObject) ||
              !array_key_exists("openapi", $oaiObject) ||
              empty($oaiObject["openapi"])) {
              throw new Exception("OpenAPI Verion Missing");
          }

          $version = explode(".",  $oaiObject["openapi"]);
          if (count($version) != 3) {
              throw new Exception("OpenAPI Version Invalid");
          }

          if ($version[0] < 3) {
              throw new Exception("OpenAPI Version Unsupported");
          }

          if (!array_key_exists("info", $oaiObject)){
              throw new Exception("OpenAPI Info Missing");
          }

          if (!array_key_exists("paths", $oaiObject) ||
              empty($oaiObject["paths"])) {
              throw new Exception("OpenAPI Paths Missing");
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

     private function followLocalReference($reference) {
         $ref = preg_replace("/^#/", "", $reference);

         $o = $this->expandReference($ref, $this->cfg);

         if (!empty($o)) {
             $this->references[$reference] = $o;
         }
     }

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

     private function followFileReference($reference) {
         $tarr = explode("#", $reference);

         $fn = $this->basedir . DIRECTORY_SEPARATOR . $tarr[0];

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

     private function followUriReference($reference) {
         // needs to implement caching
         // strip subpath
         // expand subpath
     }
}

?>
