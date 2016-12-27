<?php
namespace RESTling\Exception\OpenAPI;

class MissingSecurityRequirements extends \Exception {
    public function __construct() {
        parent::__construct("Missing Security Requirements", 1);
    }
}
?>
