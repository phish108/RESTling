<?php

namespace RESTling\Security\OpenApi;

class Http extends \RESTling\Security\OpenApi {
    private $token;
    private $type;
    private $level; // used by scram authorization

    public function validate($model, $input) {
        parent::validate($model, $input);

        if ($input->hasParameter("Authorization", "header")) {
            $auth = $input->getParameter("Authorization", "header");
            $aAuth = explode(" ", $auth, 2);
            if ($this->has("scheme", [$aAuth[0]])) {
                $this->token = $aAuth[1];
                $this->type  = strtolower($aAuth[0]);

                if ($this->type == "bearer" && $this->has("bearerFormat")) {
                    $this->type = strtolower($this->get("bearerFormat"));
                }
                elseif (in_array($this->type, ["scram-sha1", "scram-sha256"])) {
                    $a = explode('-', $this->type);
                    $this->type = $a[0];
                    $this->level = $a[1];
                }

                call_user_func(array($this, 'validate_' . $this->type), $model);
                $this->sucess();
            }
        }
    }

    protected function validate_bearer($model) {
        if (!empty($this->token)) {
            // call the model
            if (!$model || !method_exists($model, "validateToken")) {
                throw new Exception("Token Validation Not Supported");
            }

            $model->validateToken($this->token);
        }
    }

    protected function validate_jwt($model) {
        throw new Exception("Not Implemented");
        // 1  verify that the token is a jwt
        $loader = new \Jose\Loader();
        try {
            $jwt = $loader->load($this->token);
        }
        catch (Exception $err) {
            throw new Exception("Invalid JWT");
        }
        // 2  check whether it is a JWS or a JWE based on JOSE alg header
        if (!$jwt || !(($jwt instanceof \Jose\Object\JWS) || !($jwt instanceof \Jost\Object\JWE))) {
            throw new Exception("Invalid JWT");
        }
        // 3  load kid or jku from JOSE header if present
        // 3a ask JOSE Key Context (for $kid or $jku) from model
        // 4  if JWE find service privateKey($alg) and decrypt payload
        // 5  if JWE check if payload contains a JWS
        // 6a if embedded JWS use non-compact serialisation of JWS
        // 6b load kid or jku from payload JOSE header
        // 6c update $jwt hold the embedded JWS

        // 8  if JWS verify signature

        // 9  verify iss claim with key context
        // 10 verify that aud points to service URL
        // 11 verify extra claims based on RFC
        // 12 verify extra claims based on key context
        // 13 send payload and signature to update the key context
        if (method_exists($model, "updateKeyContext")) {
            $model->updateKeyContext($kid, $payload, $signature);
        }
    }

    protected function validate_basic($model) {
        throw new Exception("Not Implemented");
    }

    protected function validate_digest($model) {
        throw new Exception("Not Implemented");
    }

    protected function validate_hoba($model) {
        throw new Exception("Not Implemented");
    }

    protected function validate_scram($model) {
        throw new Exception("Not Implemented");
    }

    protected function validateScheme() {
        if (!$this->has("scheme", ["Basic", "Bearer", "Digest", "HOBA", "SCRAM"])) {
            throw new Exception('Invalid Security Authorization Scheme');
        }
        if ($this->has("scheme", "Bearer") && $this->has("bearerFormat")) {
            if ($this->has("bearerFormat", ["bearer", "Bearer", "jwt", "JWT"])) {
                throw new Exception('Invalid Security Bearer Format Hint');
            }
        }
    }
}

?>
