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

    // accepts a list of scopes that are acceptable for the security scheme
    public function setScopeRequirements($scopes) {
        if (!is_array($scopes) || empty($scopes)) {
            throw new Exception("Missing Scope Requirements");
        }

        $this->scopes = $scopes;
    }

    // for authorization
    public function willValidate() {
        return false;
    }

    abstract public function validate($model);

    // for access (tests if scopes are accepted)
    public function verify($model) {
        if (!empty($scopes)) {
            if (!$model || !method_exists($model, "verifyScope")) {
                throw new Exception("Scope Verification Not Supported");
            }
            foreach ($this->scopes as $scope) {
                $model->verifyScope($scope);
            }
        }
    }
}

?>
