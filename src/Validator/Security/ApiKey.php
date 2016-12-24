<?php

namespace RESTling\Validator\Security;

class ApiKey extends \RESTling\Validator\Security\OpenAPI {
    private $token;
    public function willValidate() {
        $in = $this->get("in");
        $name = $this->get("name");
        if ($this->input->hasParameter($name, $in)) {
            return true;
        }
        return false;
    }

    public function validate($model) {
        $in = $this->get("in");
        $name = $this->get("name");
        $this->token = $this->input->get($name, $in);

        if (!empty($this->token)) {
            // call the model
            if (!$model || !method_exists($model, "validateKey")) {
                throw new Exception("ApiKey Validation Not Supported");
            }

            $model->validateKey($this->token, $in);
        }
    }
}

?>
