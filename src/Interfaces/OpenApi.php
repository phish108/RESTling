<?php
namespace RESTling\Interfaces;

interface OpenApi extends Service {
    public function loadConfigFile($fqfn);
    public function loadConfigString($cfgString);
    public function loadApiObject($oaiObject);
}
?>