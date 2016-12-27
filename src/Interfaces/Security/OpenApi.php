<?php
namespace RESTling\Interfaces\Security;

interface OpenApi extends \RESTling\Interfaces\Security {
    public function setScheme($scheme);
    public function has($property, $valueSet = null);
    public function get($property);
}
?>
