<?php

namespace RESTling\Security\OpenApi;

class ApiKey extends \RESTling\Security\OpenApi {
    private $token;

    public function validate($model, $input) {
        parent::validate($model, $input);

        $in = $this->get("in");
        $name = $this->get("name");
        $this->token = $input->get($name, $in);

        if (!empty($this->token)) {
            // call the model
            if (!$model || !method_exists($model, "validateKey")) {
                throw new Exception("ApiKey Validation Not Supported");
            }

            $model->validateKey($this->token, $in);

            // if no errors occured, yet, then the token is validated
            $this->success();
        }
        // report no success and no error, if no validation took place
    }

    protected function validateScheme() {
        if (!$this->has("name")) {
            throw new Exception('Missing Security Parameter Name');
        }

        if (!$this->has("in", ["header", "query", "cookie"])) {
            throw new Exception('Missing Security Source');
        }
    }
}

?>
