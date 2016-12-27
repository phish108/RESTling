<?php
namespace RESTling;

interface OpenApiInterface extends ServiceInterface {
    public function loadConfigFile($fqfn);
    public function loadConfigString($cfgString);
    public function loadApiObject($oaiObject);
}
?>
