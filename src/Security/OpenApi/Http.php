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
            throw new \RESTling\Exception\Security\OpenApi\InvalidScheme();
        }

        $type = $this->get("scheme");
        if ($type == "Bearer" && $this->has("bearerFormat")) {
            if ($this->has("bearerFormat", ["bearer", "Bearer", "jwt", "JWT"])) {
                throw new \RESTling\Exception\Security\OpenApi\InvalidBearerHint();
            }
            $type = ucfirst(strtolower($this->get("bearerFormat")));
        }

        $classname = "\\RESTling\\Security\\" . $type;
        if (!class_exists($classname, true)) {
            throw new \RESTling\Exception\NotImplemented();
        }

        try {
            $this->handler = new $classname();
        }
        catch (Exception $err) {
            throw new \RESTling\Exception\Security\OpenApi\SchemeHandlerBroken();
        }
    }
}

?>
