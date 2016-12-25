<?php

namespace RESTling\Security\OpenApi;

class Oauth2 extends \RESTling\Security\OpenApi {

    public function validate($model) {
        parent::validate($model, $input);
    }

    protected function validateScheme() {
        if (!$this->has("flow")) {
            throw new Exception('Missing Security Flow');
        }

        $flow = $this->get("flow");

        $hasFlow = false;
        if (array_key_exists("implicit", $flow)) {
            $hasFlow = true;
            $fobj = $flow["implicit"];
            if (!array_key_exists('scopes', $fobj) || empty($fobj['scopes'])) {
                throw new Exception('Missing Security OAuth2 Scopes');
            }
            if (!array_key_exists('authorizationUrl', $fobj) || empty($fobj['authorizationUrl'])) {
                throw new Exception('Missing Security OAuth2 authorizationUrl');
            }
            if (array_key_exists('refreshUrl', $fobj) && empty($fobj['refreshUrl'])) {
                throw new Exception('Missing Security OAuth2 refreshUrl');
            }
        }
        if (array_key_exists("password", $flow)) {
            $hasFlow = true;
            $fobj = $flow["password"];
            if (!array_key_exists('scopes', $fobj) || empty($fobj['scopes'])) {
                throw new Exception('Missing Security OAuth2 Scopes');
            }
            if (!array_key_exists('tokenUrl', $fobj) || empty($fobj['tokenUrl'])) {
                throw new Exception('Missing Security OAuth2 tokenUrl');
            }
            if (array_key_exists('refreshUrl', $fobj) && empty($fobj['refreshUrl'])) {
                throw new Exception('Missing Security OAuth2 refreshUrl');
            }
        }
        if (array_key_exists("authorizationCode", $flow)) {
            $hasFlow = true;
            $fobj = $flow["authorizationCode"];
            if (!array_key_exists('scopes', $fobj) || empty($fobj['scopes'])) {
                throw new Exception('Missing Security OAuth2 Scopes');
            }
            if (!array_key_exists('authorizationUrl', $fobj) || empty($fobj['authorizationUrl'])) {
                throw new Exception('Missing Security OAuth2 authorizationUrl');
            }
            if (!array_key_exists('tokenUrl', $fobj) || empty($fobj['tokenUrl'])) {
                throw new Exception('Missing Security OAuth2 tokenUrl');
            }
            if (array_key_exists('refreshUrl', $fobj) && empty($fobj['refreshUrl'])) {
                throw new Exception('Missing Security OAuth2 refreshUrl');
            }
        }
        if (array_key_exists("clientCredentials", $flow)) {
            $hasFlow = true;
            $fobj = $flow["clientCredentials"];
            if (!array_key_exists('scopes', $fobj) || empty($fobj['scopes'])) {
                throw new Exception('Missing Security OAuth2 Scopes');
            }
            if (!array_key_exists('tokenUrl', $fobj) || empty($fobj['tokenUrl'])) {
                throw new Exception('Missing Security OAuth2 tokenUrl');
            }
            if (array_key_exists('refreshUrl', $fobj) && empty($fobj['refreshUrl'])) {
                throw new Exception('Missing Security OAuth2 refreshUrl');
            }
        }
        if (!$hasFlow) {
            throw new Exception('Missing Security Flow Definition');
        }
    }
}

?>
