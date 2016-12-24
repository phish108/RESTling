<?php

namespace RESTling\Validator\Security;

class OpenIdConnect extends \RESTling\Validator\Security\OpenAPI {
    public function willValidate() {
        return false;
    }

    public function validate() {

    }
}

?>
