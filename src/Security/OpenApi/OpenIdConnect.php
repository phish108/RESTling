<?php

namespace RESTling\Security\OpenApi;

class OpenIdConnect extends \RESTling\Security\OpenApi\Oauth2 {

    public function validate($model) {
        parent::validate($model, $input);
    }

    protected function validateScheme() {
        parent::validateScheme();
        if (!$this->has("openIdConnectUrl")) {
            throw new \RESTling\Exception\Security\OpenApi\MissingOidcUrl();;
        }
    }
}

?>
