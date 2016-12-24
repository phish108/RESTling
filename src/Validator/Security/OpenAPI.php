<?php

namespace RESTling\Validator\Security;

abstract class OpenAPI extends \RESTling\Validator\Security {
    protected function validateScheme() {
        // enforces OpenAPI security scheme validation
        if (!$this->has('type', ['apiKey', 'http', 'oauth2', 'openIdConnect'])) {
            throw new Exception('Invalid Security Scheme Type');
        }

        switch ($this->get("type")) {
            case "http":
                if (!$this->has("scheme", ["Basic", "Bearer", "Digest", "HOBA", "SCRAM"])) {
                    throw new Exception('Invalid Security Authorization Scheme');
                }
                if ($this->has("scheme", "Bearer") && $this->has("bearerFormat")) {
                    if ($this->has("bearerFormat", ["bearer", "Bearer", "jwt", "JWT"])) {
                        throw new Exception('Invalid Security Bearer Format Hint');
                    }
                }
                break;
            case "apiKey":
                if (!$this->has("name")) {
                    throw new Exception('Missing Security Parameter Name');
                }

                if (!$this->has("in", ["header", "query", "cookie"])) {
                    throw new Exception('Missing Security Source');
                }
                break;
            case "openIdConnect":
                if (!$this->has("openIdConnectUrl")) {
                    throw new Exception('Missing Security OIDC discovery variables');
                }
                // break; // also consider all oauth2 flags for OIDC
            case "oauth2":
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
                break;
            default:
                break;
        }
    }
}

?>
