<?php
namespace RESTling\Exception\OpenAPI;

class ConfigEmpty extends \Exception {
    public function __construct() {
        parent::__construct("Configuration Empty", 1);
    }
}
?>
