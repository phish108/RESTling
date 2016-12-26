<?php

namespace RESTling\Security\OpenApi;

class Http extends \RESTling\Security\OpenApi {
    private $handler;

    public function validate($model, $input) {
        if (!$this->handler) {
            throw new Exception('HTTP Authorization Not Configured');
        }
        $this->handler->validate($model, $input);
        if ($this->handler->passes()) {
            $this->success();
        }
    }

    protected function validateScheme() {
        if (!$this->has("scheme", ["Basic", "Bearer", "Digest", "HOBA", "SCRAM"])) {
            throw new Exception('Invalid Security Authorization Scheme');
        }

        $type = $this->get("scheme");
        if ($type == "Bearer" && $this->has("bearerFormat")) {
            if ($this->has("bearerFormat", ["bearer", "Bearer", "jwt", "JWT"])) {
                throw new Exception('Invalid Security Bearer Format Hint');
            }
            $type = ucfirst(strtolower($this->get("bearerFormat")));
        }

        $classname = "\\RESTling\\Security\\" . $type;
        if (!class_exists($classname, true)) {
            throw new Exception("Not Implemented");
        }

        try {
            $this->handler = new $classname();
        }
        catch (Exception $err) {
            throw new Exception("Security Scheme $type Broken");
        }
    }
}

?>
