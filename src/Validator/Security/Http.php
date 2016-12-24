<?php

namespace RESTling\Validator\Security;

class Http extends \RESTling\Validator\Security\OpenAPI {
    private $token;
    private $type;
    private $level; // used by scram authorization

    public function willValidate() {
        if ($this->input->hasParameter("Authorization", "header")) {
            $auth = $this->input->getParameter("Authorization", "header");
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
                return true;
            }
        }
        return false;
    }

    public function validate($model) {
        call_user_func(array($this, 'validate_' . $this->type), $model);
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
}

?>
