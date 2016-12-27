<?php
namespace RESTling\Exception\OpenAPI;

class ConfigBroken extends \Exception {
    public function __construct() {
        parent::__construct("Configuration Broken", 1);
    }
}
?>
