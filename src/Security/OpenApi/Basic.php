<?php

/**
 * Swagger Basic Security Type.
 * This class is no longer valid for OpenApi 3
 */
namespace RESTling\Security\OpenApi;

class Basic extends Http {
    protected function validateScheme() {
        $classname = "\\RESTling\\Security\\Basic";
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
