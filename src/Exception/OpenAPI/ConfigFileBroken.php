<?php
namespace RESTling\Exception\OpenAPI;

class ConfigFileBroken extends \Exception {
    public function __construct() {
        parent::__construct("Configuration File Broken", 1);
    }
}
?>
