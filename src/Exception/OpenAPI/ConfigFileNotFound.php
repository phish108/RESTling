<?php
namespace RESTling\Exception\OpenAPI;

class ConfigFileNotFound extends \Exception {
    public function __construct() {
        parent::__construct("Configuration File Not Found", 1);
    }
}
?>
