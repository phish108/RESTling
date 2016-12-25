<?php
namespace RESTling\Security;

interface OpenApiInterface {
    public function setScheme($scheme);
    public function has($property, $valueSet = null);
    public function get($property);
}
?>
