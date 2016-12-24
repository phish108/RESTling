<?php

namespace RESTling\Validator\Security;

class Oauth2 extends \RESTling\Validator\Security\OpenAPI {
    public function willValidate() {
        return false;
    }

    public function validate() {

    }
}

?>
