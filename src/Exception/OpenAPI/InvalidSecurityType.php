<?php
namespace RESTling\Exception\OpenAPI;

class InvalidSecurityType extends \Exception {
    public function __construct() {
        parent::__construct("Invalid Security Type", 1);
    }
}
?>
