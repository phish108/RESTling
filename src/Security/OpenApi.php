<?php

namespace RESTling\Security;

abstract class OpenApi extends \RESTling\Security implements OpenApiInterface {
    private $scheme = [];

    final public function has($cfgName, $values = null) {
        if (array_key_exists($cfgName, $this->scheme) && !empty($this->scheme[$cfgName])) {
            if ($values === null) {
                return true;
            }
            if (is_string($values)) {
                $values = [$values];
            }
            else {
                return false;
            }
            return in_array($this->scheme[$cfgName], $values);
        }
        return false;
    }

    final public function get($cfgName) {
        if ($this->has($cfgName)) {
            return $this->scheme[$cfgName];
        }
        return null;
    }

    final public function setScheme($cfgScheme) {
        if (is_array($cfgScheme) && !empty($cfgScheme)) {
            $this->scheme = $cfgScheme;
            if (!$this->has('type', ['basic', 'apiKey', 'http', 'oauth2', 'openIdConnect'])) {
                throw new \RESTling\Exception\Security\OpenApi\InvalidScheme();
            }

            $this->validateScheme();
        }
    }

    abstract protected function validateScheme();
}

?>
