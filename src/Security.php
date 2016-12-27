<?php
namespace RESTling;

abstract class Security implements Interfaces\Security {
    private $scopes  = []; // the scopes to verify
    private $passed = false;

    public function __construct() {
    }

    // accepts a list of scopes that are acceptable for the security scheme
    public function setScopes($scopes) {
        if (!is_array($scopes) || empty($scopes)) {
            throw new Exception\MissingScopeRequirements();
        }

        $this->scopes = $scopes;
    }

    public function validate($model, $input) {
        $this->passed = false;
        if ($input && !($input instanceof \RESTling\Input)) {
            throw new Exception\InvalidInputType();
        }
    }

    // for access (tests if scopes are accepted)
    public function verify($model, $input) {
        $this->passed = false;

        if (!empty($scopes)) {
            if (!$model || !method_exists($model, "verifyScope")) {
                throw new Exception\ScopeVerificationUnsupported();
            }
            foreach ($this->scopes as $scope) {
                $model->verifyScope($scope);
            }
            $this->success();
        }
    }

    public function passes() {
        return $this->passed;
    }

    protected function success() {
        $this->passed = true;
    }
}

?>
