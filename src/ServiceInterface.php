<?php
namespace RESTling;

interface ServiceInterface {
    public function setModel($model, $secure = false);
    public function setSecurityModel($model, $secure = false);
    public function addSecurityHandler($securityHandler);
    public function addCORSHost($host, $aMethods);
    public function addInputContentTypeMap($contentType, $handlerType);
    public function addOutputContentTypeMap($contentType, $handlerType);
    public function run();
}
?>
