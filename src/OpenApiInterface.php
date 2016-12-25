<?php
namespace RESTling;

interface OpenApiInterface {
    public function loadConfigFile($fqfn);
    public function loadConfigString($cfgString);
    public function loadApiObject($oaiObject);
}
?>
