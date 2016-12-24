<?php
namespace RESTling\Validator;

abstract class Security implements \RESTling\Validator {
    protected $input;
    private $scheme = [];
    private $scopes  = []; // the scopes to verify

    public function __construct($cfgScheme) {
        if (is_array($cfgScheme) && !empty($cfgScheme)) {
            $this->scheme = $cfgScheme;
            $this->validateScheme();
        }
    }

    public function setInput($input) {
        if ($input !== null && !($input instanceof \RESTling\Input\Base)) {
            throw new Exception('Invalid Input Type');
        }
        $this->input = $input;
    }

    protected function validateScheme() {
    }

    public function has($cfgName, $values = null) {
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

    public function get($cfgName) {
        if ($this->has($cfgName)) {
            return $this->scheme[$cfgName];
        }
        return null;
    }

    public function setScopeRequirements($scopes) {
        if (!is_array($scopes) || empty($scopes)) {
            throw new Exception("Missing Scope Requirements");
        }

        $this->scopes = $scopes;
    }

    // for authorization
    abstract public function willValidate();
    abstract public function validate();

    // for access (scope)
    abstract public function verify();
}

?>
