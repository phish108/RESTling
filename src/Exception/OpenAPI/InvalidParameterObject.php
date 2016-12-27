<?php
namespace RESTling\Exception\OpenAPI;

class InvalidParameterObject extends \Exception {
    public function __construct() {
        parent::__construct("Invalid Parameter Object", 1);
    }
}
?>
